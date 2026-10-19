<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\HttpException;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\Validator;
use Wiz\Core\View;
use Wiz\Repositories\CategoryRepository;
use Wiz\Repositories\ServiceRepository;
use Wiz\Services\Uploader;
use Wiz\Support\Slug;

/**
 * The services list: what the salon offers, its price and how long it takes.
 *
 * Staff never see the word "slug" — it is generated from the name when the
 * service is first saved and then left alone, so links to a service keep
 * working after someone fixes a typo in its title.
 */
final class ServiceController
{
    public function __construct(
        private readonly View $view,
        private readonly ServiceRepository $services,
        private readonly CategoryRepository $categories,
        private readonly Uploader $uploader,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        return $this->view->render('admin/services/index', [
            'title' => 'Services',
            'heading' => 'Services',
            'subheading' => 'What customers can book, and what it costs.',
            'active' => 'services',
            'services' => $this->services->allWithCategory(),
            'categories' => $this->categories->allWithCounts(),
            'uploads' => $this->uploader,
            // Set after a new service is saved, so the fresh row is obvious.
            'highlight' => (int) $request->queryString('highlight'),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params = []): Response
    {
        return $this->form(null, $request);
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params = []): Response
    {
        $service = $this->services->find((int) ($params['id'] ?? 0));

        if ($service === null) {
            throw HttpException::notFound('That service no longer exists.');
        }

        return $this->form($service, $request);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $data = $this->validate($request);

        if (is_string($data)) {
            $this->session->flash('error', $data);

            throw new RedirectException('/admin/services/new');
        }

        $upload = $this->storeImage($request);

        if ($upload['ok'] === false) {
            $this->session->flash('error', (string) $upload['error']);

            throw new RedirectException('/admin/services/new');
        }

        $base = Slug::make($data['name'], 'service');

        try {
            $id = $this->services->create([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'slug' => Slug::unique($base, fn (string $slug): bool => $this->services->slugExists($slug)),
                'short_description' => $data['short_description'],
                'description' => $data['description'],
                'price_cents' => $data['price_cents'],
                'duration_minutes' => $data['duration_minutes'],
                'image_path' => $upload['path'],
                'is_active' => $data['is_active'],
                'is_featured' => $data['is_featured'],
            ]);
        } catch (\Throwable $e) {
            // The photo is already on disk; without this it would stay there
            // forever, referenced by nothing.
            $this->uploader->delete($upload['path']);

            throw $e;
        }

        $this->session->flash('success', sprintf('“%s” was added to the services list.', $data['name']));

        throw new RedirectException('/admin/services?highlight=' . $id);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $service = $this->services->find($id);

        if ($service === null) {
            throw HttpException::notFound('That service no longer exists.');
        }

        $data = $this->validate($request);

        if (is_string($data)) {
            $this->session->flash('error', $data);

            throw new RedirectException('/admin/services/' . $id . '/edit');
        }

        $upload = $this->storeImage($request);

        if ($upload['ok'] === false) {
            $this->session->flash('error', (string) $upload['error']);

            throw new RedirectException('/admin/services/' . $id . '/edit');
        }

        $payload = [
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'short_description' => $data['short_description'],
            'description' => $data['description'],
            'price_cents' => $data['price_cents'],
            'duration_minutes' => $data['duration_minutes'],
            'is_active' => $data['is_active'],
            'is_featured' => $data['is_featured'],
        ];

        if ($upload['path'] !== null) {
            $payload['image_path'] = $upload['path'];

            // Only after the row is updated: if the update fails the old photo
            // is still there.
            $this->uploader->delete(is_string($service['image_path'] ?? null) ? $service['image_path'] : null);
        }

        try {
            $this->services->update($id, $payload);
        } catch (\Throwable $e) {
            if ($upload['path'] !== null) {
                $this->uploader->delete($upload['path']);
            }

            throw $e;
        }
        $this->session->flash('success', sprintf('“%s” was saved.', $data['name']));

        throw new RedirectException('/admin/services');
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $service = $this->services->find($id);

        if ($service === null) {
            throw HttpException::notFound('That service no longer exists.');
        }

        $this->services->delete($id);
        $this->uploader->delete(is_string($service['image_path'] ?? null) ? $service['image_path'] : null);

        $this->session->flash('success', sprintf('“%s” was deleted.', (string) $service['name']));

        throw new RedirectException('/admin/services');
    }

    /**
     * @param array<string, string> $params
     */
    public function toggle(Request $request, array $params = []): Response
    {
        $service = $this->services->find((int) ($params['id'] ?? 0));

        if ($service === null) {
            throw HttpException::notFound('That service no longer exists.');
        }

        $active = (int) ($service['is_active'] ?? 0) !== 1;
        $this->services->setActive((int) $service['id'], $active);

        $this->session->flash(
            'success',
            sprintf('“%s” is now %s.', (string) $service['name'], $active ? 'visible on the website' : 'hidden from the website'),
        );

        throw new RedirectException('/admin/services');
    }

    /**
     * @param array<string, string> $params
     */
    public function move(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $direction = $request->string('direction') === 'down' ? 'down' : 'up';

        $this->services->move($id, $direction);

        throw new RedirectException('/admin/services');
    }

    /**
     * @param array<string, mixed>|null $service
     */
    private function form(?array $service, Request $request): Response
    {
        return $this->view->render('admin/services/form', [
            'title' => $service === null ? 'New service' : 'Edit service',
            'heading' => $service === null ? 'Add a service' : 'Edit service',
            'subheading' => $service === null
                ? 'Customers see this on the services page.'
                : (string) $service['name'],
            'active' => 'services',
            'service' => $service,
            'categories' => $this->categories->allWithCounts(),
            'uploads' => $this->uploader,
            'error' => $this->session->flash('error'),
        ]);
    }

    /**
     * @return array<string, mixed>|string validated data, or the first error
     */
    private function validate(Request $request): array|string
    {
        $validator = new Validator(
            [
                'name' => $request->string('name'),
                'category_id' => $request->string('category_id'),
                'short_description' => $request->string('short_description'),
                'description' => $request->string('description'),
                'price' => $request->string('price'),
                'duration' => $request->string('duration'),
            ],
            [
                'name' => 'required|max:160',
                'category_id' => 'integer',
                'short_description' => 'max:255',
                'description' => 'max:4000',
                'price' => 'numeric',
                'duration' => 'integer',
            ],
            [
                'name' => 'Service name',
                'short_description' => 'Short description',
                'description' => 'Description',
                'price' => 'Price',
                'duration' => 'Duration',
            ],
        );

        if (!$validator->validate()) {
            return (string) $validator->firstError();
        }

        $input = $validator->validated();

        $price = $input['price'] ?? '';
        $duration = $input['duration'] ?? '';
        $category = $input['category_id'] ?? '';

        return [
            'name' => (string) $input['name'],
            'category_id' => $category === '' || (int) $category <= 0 ? null : (int) $category,
            'short_description' => $this->nullable($input['short_description'] ?? ''),
            'description' => $this->nullable($input['description'] ?? ''),
            'price_cents' => $price === '' ? null : (int) round(((float) $price) * 100),
            'duration_minutes' => $duration === '' || (int) $duration <= 0 ? null : (int) $duration,
            'is_active' => $request->input('is_active') !== null,
            'is_featured' => $request->input('is_featured') !== null,
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $string = is_scalar($value) ? trim((string) $value) : '';

        return $string === '' ? null : $string;
    }

    /**
     * @return array{ok: bool, path: ?string, error: ?string}
     */
    private function storeImage(Request $request): array
    {
        $file = $request->file('image');

        if ($file === [] || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'path' => null, 'error' => null];
        }

        $result = $this->uploader->store($file);

        if ($result['ok'] !== true) {
            return ['ok' => false, 'path' => null, 'error' => (string) ($result['error'] ?? 'The image could not be saved.')];
        }

        return ['ok' => true, 'path' => $result['path'] ?? null, 'error' => null];
    }
}
