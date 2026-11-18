<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\View;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Support\Paginator;

/**
 * The audit trail, readable by administrators.
 *
 * Every sign-in, booking change, team change and settings change is already
 * recorded; this screen is what makes the record useful when somebody asks
 * "who cancelled this?" a week later.
 */
final class ActivityController
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly View $view,
        private readonly ActivityLogRepository $activity,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $page = max(1, (int) $request->queryString('page', '1'));
        $total = $this->activity->count();
        $paginator = Paginator::of($page, self::PER_PAGE, $total);

        return $this->view->render('admin/activity/index', [
            'title' => 'Activity',
            'heading' => 'Activity',
            'subheading' => 'Every change made in this panel, newest first.',
            'active' => 'activity',
            'entries' => $this->activity->paginate($paginator->perPage, $paginator->offset()),
            'paginator' => $paginator,
        ]);
    }
}
