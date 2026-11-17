<?php
declare(strict_types=1);

namespace Wiz\Controllers\Api;

use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Repositories\BusinessHoursRepository;
use Wiz\Repositories\ClosureRepository;
use Wiz\Repositories\GalleryRepository;
use Wiz\Repositories\ReviewRepository;
use Wiz\Repositories\SiteImageRepository;
use Wiz\Support\SalonDetails;

/**
 * What the salon has chosen to show.
 *
 * Read-only endpoints, all answered from rows staff control in the panel: the
 * gallery shows published photos only, reviews show approved ones only, and the
 * salon endpoint carries the contact details and opening hours that the Contact
 * page prints. Nothing here can be written to, and nothing unpublished leaks.
 */
final class ContentApiController
{
    public function __construct(
        private readonly GalleryRepository $gallery,
        private readonly ReviewRepository $reviews,
        private readonly SiteImageRepository $images,
        private readonly SalonDetails $details,
        private readonly BusinessHoursRepository $hours,
        private readonly ClosureRepository $closures,
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
                'category_id' => $item['category_id'] === null ? null : (int) $item['category_id'],
                'category' => $item['category_name'] === null ? null : (string) $item['category_name'],
                'category_slug' => $item['category_slug'] === null ? null : (string) $item['category_slug'],
                'show_on_home' => (int) ($item['show_on_home'] ?? 0) === 1,
            ];
        }

        // Only categories that have a published photo: the filter can never
        // offer a choice that comes back empty.
        $categories = [];

        foreach ($this->gallery->publishedCategories() as $row) {
            $categories[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'amharic' => (string) ($row['amharic'] ?? ''),
                'photo_count' => (int) $row['photo_count'],
            ];
        }

        return Response::json(['photos' => $out, 'categories' => $categories])
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

    /**
     * GET /api/v1/images
     *
     * Every picture slot the layout uses, keyed by the name the design knows it
     * by ("hero", "before", "after"…). The site replaces its bundled image with
     * whatever is returned here, and keeps the bundle for any slot the salon has
     * not filled.
     *
     * @param array<string, string> $params
     */
    public function images(Request $request, array $params = []): Response
    {
        $base = rtrim($this->uploadUrl, '/');
        $out = [];

        foreach ($this->images->all() as $image) {
            $file = (string) ($image['image_path'] ?? '');

            if ($file === '') {
                continue;
            }

            $out[(string) $image['slot']] = [
                'url' => $base . '/' . rawurlencode($file),
                'alt' => (string) ($image['alt'] ?? ''),
            ];
        }

        return Response::json(['images' => $out])
            ->withHeader('Cache-Control', 'public, max-age=60');
    }

    /**
     * GET /api/v1/salon
     *
     * Contact details, the weekly hours and any closures still to come. The
     * Contact page prints exactly this, so a change in the panel is visible on
     * the site at the next load — there is no second copy to keep in step.
     *
     * Cached for a minute: long enough to matter on a busy day, short enough
     * that a corrected phone number is not stale for long.
     *
     * @param array<string, string> $params
     */
    public function salon(Request $request, array $params = []): Response
    {
        $hours = [];

        foreach ($this->hours->all() as $row) {
            $hours[] = [
                'day' => (int) $row['day_of_week'],
                'opens_at' => $row['is_closed'] === null || (int) $row['is_closed'] === 1
                    ? null
                    : substr((string) $row['opens_at'], 0, 5),
                'closes_at' => $row['is_closed'] === null || (int) $row['is_closed'] === 1
                    ? null
                    : substr((string) $row['closes_at'], 0, 5),
                'is_closed' => (int) ($row['is_closed'] ?? 0) === 1,
            ];
        }

        $closures = [];

        foreach ($this->closures->upcoming() as $row) {
            $closures[] = [
                'from' => (string) $row['start_date'],
                'to' => (string) $row['end_date'],
                'reason' => (string) ($row['reason'] ?? ''),
            ];
        }

        return Response::json([
            'contact' => $this->details->all(),
            'hours' => $hours,
            'closures' => $closures,
        ])->withHeader('Cache-Control', 'public, max-age=60');
    }
}
