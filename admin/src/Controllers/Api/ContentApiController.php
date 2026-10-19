<?php
declare(strict_types=1);

namespace Wiz\Controllers\Api;

use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Repositories\GalleryRepository;
use Wiz\Repositories\ReviewRepository;

/**
 * What the salon has chosen to show.
 *
 * Two read-only endpoints, both answered from rows staff control in the panel:
 * the gallery shows published photos only, and reviews show approved ones
 * only. Nothing here can be written to, and nothing unpublished can leak.
 */
final class ContentApiController
{
    public function __construct(
        private readonly GalleryRepository $gallery,
        private readonly ReviewRepository $reviews,
        private readonly string $uploadUrl = '/uploads',
    ) {
    }

    /**
     * GET /api/v1/gallery
     *
     * @param array<string, string> $params
     */
    public function gallery(Request $request, array $params = []): Response
    {
        $base = rtrim($this->uploadUrl, '/');
        $out = [];

        foreach ($this->gallery->published() as $item) {
            $file = (string) ($item['image_path'] ?? '');

            if ($file === '') {
                continue;
            }

            $out[] = [
                'id' => (int) $item['id'],
                'title' => (string) ($item['title'] ?? ''),
                'caption' => (string) ($item['caption'] ?? ''),
                'url' => $base . '/' . rawurlencode($file),
            ];
        }

        return Response::json(['photos' => $out])
            ->withHeader('Cache-Control', 'public, max-age=300');
    }

    /**
     * GET /api/v1/reviews
     *
     * @param array<string, string> $params
     */
    public function reviews(Request $request, array $params = []): Response
    {
        $out = [];

        foreach ($this->reviews->approved(12) as $review) {
            $created = strtotime((string) ($review['created_at'] ?? ''));

            $out[] = [
                'id' => (int) $review['id'],
                'name' => (string) $review['customer_name'],
                'rating' => (int) $review['rating'],
                'body' => (string) $review['body'],
                'service' => $review['service_name'] === null ? null : (string) $review['service_name'],
                'date' => $created === false ? null : date('Y-m-d', $created),
            ];
        }

        return Response::json(['reviews' => $out])
            ->withHeader('Cache-Control', 'public, max-age=300');
    }
}
