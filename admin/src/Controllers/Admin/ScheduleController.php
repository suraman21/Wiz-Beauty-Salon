<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use Wiz\Core\HttpException;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\View;
use Wiz\Repositories\BusinessHoursRepository;
use Wiz\Repositories\ClosureRepository;
use Wiz\Services\AvailabilityService;

/**
 * Opening hours and holidays.
 *
 * One screen, because they answer the same question — "can someone book that
 * day?" — and staff should not have to remember which of the two to edit.
 */
final class ScheduleController
{
    private const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function __construct(
        private readonly View $view,
        private readonly BusinessHoursRepository $hours,
        private readonly ClosureRepository $closures,
        private readonly AvailabilityService $availability,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        return $this->view->render('admin/schedule/index', [
            'title' => 'Opening hours',
            'heading' => 'Opening hours',
            'subheading' => 'The weekly pattern, plus any days the salon is shut.',
            'active' => 'schedule',
            'hours' => $this->hours->all(),
            'days' => self::DAYS,
            'closures' => $this->closures->all(),
            'grid' => $this->availability->gridMinutes(),
            'lead' => $this->availability->leadMinutes(),
            'horizon' => $this->availability->maxAdvanceDays(),
            'error' => $this->session->flash('error'),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function saveHours(Request $request, array $params = []): Response
    {
        foreach (self::DAYS as $day => $label) {
            $closed = $request->input('closed_' . $day) !== null;

            $opens = $this->time($request->string('opens_' . $day));
            $closes = $this->time($request->string('closes_' . $day));

            if (!$closed && ($opens === null || $closes === null)) {
                $this->session->flash('error', 'Fill in both times for ' . $label . ', or mark it closed.');

                throw new RedirectException('/admin/schedule');
            }

            if (!$closed && $opens !== null && $closes !== null && $closes <= $opens) {
                $this->session->flash('error', $label . ' closes before it opens. Please check the times.');

                throw new RedirectException('/admin/schedule');
            }

            $this->hours->save($day, $closed ? null : $opens, $closed ? null : $closes, $closed);
        }

        $this->session->flash('success', 'Opening hours saved.');

        throw new RedirectException('/admin/schedule');
    }

    /**
     * @param array<string, string> $params
     */
    public function storeClosure(Request $request, array $params = []): Response
    {
        $start = $request->string('start_date');
        $end = $request->string('end_date', $start);
        $reason = trim($request->string('reason'));

        if (!$this->isDate($start) || !$this->isDate($end)) {
            $this->session->flash('error', 'Choose a valid date.');

            throw new RedirectException('/admin/schedule');
        }

        if ($end < $start) {
            $this->session->flash('error', 'The last day cannot be before the first.');

            throw new RedirectException('/admin/schedule');
        }

        $this->closures->create($start, $end, $reason === '' ? null : $reason);

        $this->session->flash('success', 'Closed from ' . $start . ' to ' . $end . '. Nobody can book those days.');

        throw new RedirectException('/admin/schedule');
    }

    /**
     * @param array<string, string> $params
     */
    public function destroyClosure(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            throw HttpException::notFound('That closure no longer exists.');
        }

        $this->closures->delete($id);
        $this->session->flash('success', 'Closure removed. Those days are bookable again.');

        throw new RedirectException('/admin/schedule');
    }

    private function time(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
            return null;
        }

        return $value . ':00';
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}
