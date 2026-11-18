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
use Wiz\Repositories\ReviewRepository;
use Wiz\Repositories\ServiceRepository;
use Wiz\Support\Paginator;

/**
 * Reviews waiting for a decision, and the ones already published.
 *
 * New reviews are not shown on the site until someone approves them here. A
 * staff member can also type in a review the salon received in person — that is
 * how most of them arrive in the first place.
 */
final class ReviewController
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly View $view,
        private readonly ReviewRepository $reviews,
        private readonly ServiceRepository $services,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $paginator = Paginator::of(
            max(1, (int) $request->queryString('page', '1')),
            self::PER_PAGE,
            $this->reviews->count(),
        );

        return $this->view->render('admin/reviews/index', [
            'title' => 'Reviews',
            'heading' => 'Reviews',
            'subheading' => 'Approve what appears on the website.',
            'active' => 'reviews',
            'reviews' => $this->reviews->paginateWithService($paginator->perPage, $paginator->offset()),
            'pending' => $this->reviews->countPending(),
            'services' => $this->services->activeWithCategory(),
            'error' => $this->session->flash('error'),
            'paginator' => $paginator,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $validator = new Validator(
            [
                'customer_name' => $request->string('customer_name'),
                'rating' => $request->string('rating', '5'),
                'body' => $request->string('body'),
                'service_id' => $request->string('service_id'),
            ],
            [
                'customer_name' => 'required|max:120',
                'rating' => 'required|integer|in:1,2,3,4,5',
                'body' => 'required|max:1000',
                'service_id' => 'integer',
            ],
            [
                'customer_name' => 'Customer name',
                'rating' => 'Rating',
                'body' => 'Review',
            ],
        );

        if (!$validator->validate()) {
            $this->session->flash('error', (string) $validator->firstError());

            throw new RedirectException('/admin/reviews');
        }

        $input = $validator->validated();
        $serviceId = (int) ($input['service_id'] ?? 0);

        $this->reviews->create(
            (string) $input['customer_name'],
            (int) $input['rating'],
            (string) $input['body'],
            $serviceId > 0 ? $serviceId : null,
            false,
        );

        $this->session->flash('success', 'Review saved. Approve it when you are ready to publish.');

        throw new RedirectException('/admin/reviews');
    }

    /**
     * @param array<string, string> $params
     */
    public function approve(Request $request, array $params = []): Response
    {
        $review = $this->reviews->find((int) ($params['id'] ?? 0));

        if ($review === null) {
            throw HttpException::notFound('That review no longer exists.');
        }

        $approved = (int) ($review['is_approved'] ?? 0) !== 1;
        $this->reviews->setApproved((int) $review['id'], $approved);

        $this->session->flash(
            'success',
            $approved
                ? sprintf('Review from %s is now on the website.', (string) $review['customer_name'])
                : sprintf('Review from %s is hidden again.', (string) $review['customer_name']),
        );

        throw new RedirectException('/admin/reviews');
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $review = $this->reviews->find((int) ($params['id'] ?? 0));

        if ($review === null) {
            throw HttpException::notFound('That review no longer exists.');
        }

        $this->reviews->delete((int) $review['id']);
        $this->session->flash('success', 'Review deleted.');

        throw new RedirectException('/admin/reviews');
    }
}
