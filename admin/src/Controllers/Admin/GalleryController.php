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
use Wiz\Repositories\GalleryRepository;
use Wiz\Services\Uploader;
use Wiz\Support\MediaUsage;

/**
 * The photo gallery.
 *
 * Uploads are one image at a time: a multi-file picker looks simpler but makes
 * it impossible to give each photo its own title, and staff end up with a grid
 * of untitled pictures. One photo, one caption, in order.
 */
final class GalleryController
{
    public function __construct(
        private readonly View $view,
        private readonly GalleryRepository $gallery,
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
        return $this->view->render('admin/gallery/index', [
            'title' => 'Gallery',
            'heading' => 'Gallery',
            'subheading' => 'Photos shown on the website. Only published photos are visible.',
            'active' => 'gallery',
            'items' => $this->gallery->all(),
            'categories' => $this->categories->active(),
            'uploads' => $this->uploader,
            'error' => $this->session->flash('error'),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $file = $request->file('image');
        $result = $this->uploader->store($file);

        if ($result['ok'] !== true) {
            $this->session->flash('error', (string) ($result['error'] ?? 'The image could not be saved.'));

            throw new RedirectException('/admin/gallery');
        }

        $validator = new Validator(
            [
                'title' => $request->string('title'),
                'caption' => $request->string('caption'),
            ],
            ['title' => 'max:160', 'caption' => 'max:255'],
            ['title' => 'Title', 'caption' => 'Caption'],
        );

        if (!$validator->validate()) {
            // The bytes are already on disk; remove them rather than leave an
            // orphaned file behind.
            $this->uploader->delete((string) ($result['path'] ?? ''));

            $this->session->flash('error', (string) $validator->firstError());

            throw new RedirectException('/admin/gallery');
        }

        $input = $validator->validated();

        $this->gallery->create(
            (string) ($result['path'] ?? ''),
            $this->nullable($input['title'] ?? ''),
            $this->nullable($input['caption'] ?? ''),
            $request->input('is_published') !== null,
        );

        $this->session->flash('success', 'Photo uploaded.');

        throw new RedirectException('/admin/gallery');
    }

    /**
     * @param array<string, string> $params
     */
    public function toggle(Request $request, array $params = []): Response
    {
        $item = $this->gallery->find((int) ($params['id'] ?? 0));

        if ($item === null) {
            throw HttpException::notFound('That photo no longer exists.');
        }

        $published = (int) ($item['is_published'] ?? 0) !== 1;
        $this->gallery->setPublished((int) $item['id'], $published);

        $this->session->flash(
            'success',
            $published ? 'Photo published.' : 'Photo hidden from the website.',
        );

        throw new RedirectException('/admin/gallery');
    }

    /**
     * Tick a photo for the home page's "A visual mood" section — the three
     * ticked photos lead the section, in the order the arrows set.
     *
     * @param array<string, string> $params
     */
    public function home(Request $request, array $params = []): Response
    {
        $item = $this->gallery->find((int) ($params['id'] ?? 0));

        if ($item === null) {
            throw HttpException::notFound('That photo no longer exists.');
        }

        $show = (int) ($item['show_on_home'] ?? 0) !== 1;
        $this->gallery->setShowOnHome((int) $item['id'], $show);

        $this->session->flash(
            'success',
            $show ? 'Photo will lead the home page.' : 'Photo removed from the home page.',
        );

        throw new RedirectException('/admin/gallery');
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $item = $this->gallery->find((int) ($params['id'] ?? 0));

        if ($item === null) {
            throw HttpException::notFound('That photo no longer exists.');
        }

        $path = is_string($item['image_path'] ?? null) ? $item['image_path'] : '';

        $this->gallery->delete((int) $item['id']);

        // The same file may also be a category illustration or a hero image;
        // delete the bytes only when nothing else points at them.
        if ($path !== '' && !$this->usage->usedElsewhere($path)) {
            $this->uploader->delete($path);
        }

        $this->session->flash('success', 'Photo deleted.');

        throw new RedirectException('/admin/gallery');
    }

    /**
     * Change which service category a photo belongs to — this is what the
     * filter on the public gallery uses.
     *
     * @param array<string, string> $params
     */
    public function setCategory(Request $request, array $params = []): Response
    {
        $item = $this->gallery->find((int) ($params['id'] ?? 0));

        if ($item === null) {
            throw HttpException::notFound('That photo no longer exists.');
        }

        $this->gallery->setCategory((int) $item['id'], $this->category($request));

        $this->session->flash('success', 'Photo category saved.');

        throw new RedirectException('/admin/gallery');
    }

    /**
     * @param array<string, string> $params
     */
    public function move(Request $request, array $params = []): Response
    {
        $this->gallery->move((int) ($params['id'] ?? 0), $request->string('direction') === 'down' ? 'down' : 'up');

        throw new RedirectException('/admin/gallery');
    }

    private function category(Request $request): ?int
    {
        $id = (int) $request->string('category_id');

        if ($id <= 0) {
            return null;
        }

        foreach ($this->categories->active() as $category) {
            if ((int) $category['id'] === $id) {
                return $id;
            }
        }

        return null;
    }

    private function nullable(mixed $value): ?string
    {
        $string = is_scalar($value) ? trim((string) $value) : '';

        return $string === '' ? null : $string;
    }
}
