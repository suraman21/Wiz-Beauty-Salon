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
use Wiz\Services\Uploader;
use Wiz\Support\MediaUsage;
use Wiz\Support\Slug;

/**
 * Categories group the services list ("Hair", "Nails", "Skin").
 *
 * The add and edit form lives on the list page itself — one screen, one job,
 * nothing to navigate.
 */
final class CategoryController
{
    public function __construct(
        private readonly View $view,
        private readonly CategoryRepository $categories,
        private readonly Uploader $uploader,
        private readonly MediaUsage $usage,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $editing = null;
        $editId = (int) $request->queryString('edit');

        if ($editId > 0) {
            $editing = $this->categories->find($editId);
        }

        return $this->view->render('admin/categories/index', [
            'title' => 'Categories',
            'heading' => 'Service categories',
            'subheading' => 'How the services list is grouped on the website.',
            'active' => 'services',
            'categories' => $this->categories->allWithCounts(),
            'editing' => $editing,
            'uploads' => $this->uploader,
            'error' => $this->session->flash('error'),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $data = $this->validate($request);

        if (is_string($data)) {
            $this->session->flash('error', $data);

            throw new RedirectException('/admin/categories');
        }

        $base = Slug::make($data['name'], 'category');
        $image = $this->storeImage($request);

        if ($image['ok'] !== true) {
            $this->session->flash('error', (string) $image['error']);

            throw new RedirectException('/admin/categories');
        }

        $id = $this->categories->create(
            $data['name'],
            Slug::unique($base, fn (string $slug): bool => $this->categories->slugExists($slug)),
            $data['description'],
            $data['is_active'],
            $data['amharic'],
        );

        if (is_string($image['path']) && $image['path'] !== '') {
            $this->categories->setImage($id, $image['path']);
        }

        $this->session->flash('success', sprintf('“%s” was added.', $data['name']));

        throw new RedirectException('/admin/categories');
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        if ($this->categories->find($id) === null) {
            throw HttpException::notFound('That category no longer exists.');
        }

        $data = $this->validate($request);

        if (is_string($data)) {
            $this->session->flash('error', $data);

            throw new RedirectException('/admin/categories?edit=' . $id);
        }

        $previous = $this->categories->find($id);
        $image = $this->storeImage($request);

        if ($image['ok'] !== true) {
            $this->session->flash('error', (string) $image['error']);

            throw new RedirectException('/admin/categories?edit=' . $id);
        }

        $this->categories->update($id, $data['name'], $data['description'], $data['is_active'], $data['amharic']);

        if (is_string($image['path']) && $image['path'] !== '') {
            $this->categories->setImage($id, $image['path']);

            $old = is_string($previous['image_path'] ?? null) ? (string) $previous['image_path'] : '';

            if ($old !== '' && !$this->usage->usedElsewhere($old, 'service_categories', $id)) {
                $this->uploader->delete($old);
            }
        }

        $this->session->flash('success', sprintf('“%s” was saved.', $data['name']));

        throw new RedirectException('/admin/categories');
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $category = $this->categories->find($id);

        if ($category === null) {
            throw HttpException::notFound('That category no longer exists.');
        }

        $this->categories->delete($id);

        $this->session->flash(
            'success',
            sprintf('“%s” was deleted. Its services are still there, now uncategorised.', (string) $category['name']),
        );

        throw new RedirectException('/admin/categories');
    }

    /**
     * @param array<string, string> $params
     */
    public function move(Request $request, array $params = []): Response
    {
        $this->categories->move((int) ($params['id'] ?? 0), $request->string('direction') === 'down' ? 'down' : 'up');

        throw new RedirectException('/admin/categories');
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

        return ['ok' => true, 'path' => (string) ($result['path'] ?? ''), 'error' => null];
    }

    /**
     * @return array<string, mixed>|string
     */
    private function validate(Request $request): array|string
    {
        $validator = new Validator(
            [
                'name' => $request->string('name'),
                'amharic' => $request->string('amharic'),
                'description' => $request->string('description'),
            ],
            [
                'name' => 'required|max:120',
                'amharic' => 'max:120',
                'description' => 'max:255',
            ],
            [
                'name' => 'Category name',
                'amharic' => 'Amharic name',
                'description' => 'Description',
            ],
        );

        if (!$validator->validate()) {
            return (string) $validator->firstError();
        }

        $input = $validator->validated();
        $description = trim((string) ($input['description'] ?? ''));

        $amharic = trim((string) ($input['amharic'] ?? ''));

        return [
            'name' => (string) $input['name'],
            'amharic' => $amharic === '' ? null : $amharic,
            'description' => $description === '' ? null : $description,
            'is_active' => $request->input('is_active') !== null,
        ];
    }
}
