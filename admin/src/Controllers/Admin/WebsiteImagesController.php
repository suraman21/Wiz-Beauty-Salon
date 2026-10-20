<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\HttpException;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\View;
use Wiz\Repositories\SiteImageRepository;
use Wiz\Services\Uploader;
use Wiz\Support\MediaUsage;

/**
 * The pictures in the website's layout.
 *
 * Each row is a place the design shows a photograph — the home hero, the
 * editorial portrait, the before-and-after pair — and staff replace the file
 * behind it. The layout itself is not editable here; only what fills it.
 */
final class WebsiteImagesController
{
    public function __construct(
        private readonly View $view,
        private readonly SiteImageRepository $images,
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
        return $this->view->render('admin/images/index', [
            'title' => 'Website images',
            'heading' => 'Website images',
            'subheading' => 'The photographs the website shows. Replace one and it changes on the next visit.',
            'active' => 'images',
            'images' => $this->images->all(),
            'uploads' => $this->uploader,
            'empty' => $this->images->countEmpty(),
            'error' => $this->session->flash('error'),
            'success' => $this->session->flash('success'),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $image = $this->images->find($id);

        if ($image === null) {
            throw HttpException::notFound('That picture no longer exists.');
        }

        $alt = trim($request->string('alt'));

        if (mb_strlen($alt) > 190) {
            $this->session->flash('error', 'The description is too long. Keep it under 190 characters.');

            throw new RedirectException('/admin/images');
        }

        $file = $request->file('image');
        $replaced = $file !== [] && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($replaced) {
            $result = $this->uploader->store($file);

            if ($result['ok'] !== true) {
                $this->session->flash('error', (string) ($result['error'] ?? 'The image could not be saved.'));

                throw new RedirectException('/admin/images');
            }

            $newPath = (string) ($result['path'] ?? '');
            $oldPath = (string) ($image['image_path'] ?? '');

            $this->images->setImage($id, $newPath, $alt === '' ? null : $alt);

            // The same file is often shared — the gallery photos are also the
            // category illustrations — so only remove it when nothing else
            // points at it.
            if ($oldPath !== '' && !$this->usage->usedElsewhere($oldPath, 'site_images', $id)) {
                $this->uploader->delete($oldPath);
            }

            $this->session->flash('success', sprintf('“%s” was replaced.', (string) $image['label']));

            throw new RedirectException('/admin/images');
        }

        $this->images->setImage($id, (string) ($image['image_path'] ?? ''), $alt === '' ? null : $alt);

        $this->session->flash('success', sprintf('The description for “%s” was saved.', (string) $image['label']));

        throw new RedirectException('/admin/images');
    }
}
