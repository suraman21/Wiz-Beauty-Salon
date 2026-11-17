<?php
declare(strict_types=1);

namespace Wiz\Support;

use DateTimeImmutable;
use DateTimeZone;
use Wiz\Repositories\SettingsRepository;

/**
 * How late a customer may still move or cancel their own appointment.
 *
 * The rule used to be a sentence typed into three templates: "free to change or
 * cancel until 24 hours before". A salon that needs 48 hours — because a bridal
 * booking holds half a day, or because the stylist has to order in — had no way
 * to say so, and the sentence went on saying 24. Now it is one setting, read
 * here, and this class is the only place that answers the question, so the
 * customer page, the confirmation email, the panel and the website all agree.
 *
 * `hours()` returns 0 for "any time, right up to the appointment".
 */
final class ChangePolicy
{
    public const SETTING = 'booking.change_window_hours';

    /** A day, unless the salon says otherwise: the rule the site shipped with. */
    public const DEFAULT_HOURS = 24;

    /**
     * Two weeks. Beyond that the "window" is not a window — it is the booking
     * horizon, and a customer who cannot change a booking they made two months
     * out would rightly complain.
     */
    public const MAX_HOURS = 336;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly string $timezone = 'Africa/Addis_Ababa',
    ) {
    }

    /**
     * The window in hours. 0 means "no cut-off: change any time before the
     * appointment starts".
     */
    public function hours(): int
    {
        $stored = $this->settings->get(self::SETTING, '');

        if ($stored === '' || $stored === null) {
            return self::DEFAULT_HOURS;
        }

        $hours = (int) $stored;

        if ($hours < 0) {
            return 0;
        }

        return min($hours, self::MAX_HOURS);
    }

    public function hasCutOff(): bool
    {
        return $this->hours() > 0;
    }

    /** "24 hours", "1 hour", "no cut-off" — for a sentence, not for a form. */
    public function hoursLabel(): string
    {
        $hours = $this->hours();

        if ($hours === 0) {
            return 'no cut-off';
        }

        return $hours === 1 ? '1 hour' : $hours . ' hours';
    }

    /**
     * The sentence the customer reads. One string, one source, so the website,
     * the manage page and the email cannot drift apart.
     */
    public function summary(): string
    {
        if (!$this->hasCutOff()) {
            return 'You can change or cancel this appointment at any time before it starts.';
        }

        return 'Free to change or cancel up to ' . $this->hoursLabel() . ' before your appointment.';
    }

    /**
     * The window as a noun phrase, for sentences that need one: "the 24-hour
     * change window". Keeps the grammar out of the controllers.
     */
    public function windowPhrase(): string
    {
        if (!$this->hasCutOff()) {
            return 'the booking change window (no cut-off)';
        }

        return 'the ' . $this->hours() . '-hour change window';
    }

    /** The same rule, shorter, for buttons and badges. */
    public function chip(): string
    {
        return $this->hasCutOff()
            ? 'Changes close ' . $this->hoursLabel() . ' before'
            : 'Change any time before';
    }

    /**
     * The moment the window closes for one booking.
     *
     * @param array<string, mixed> $booking
     */
    public function deadline(array $booking): DateTimeImmutable
    {
        $start = $this->startOf($booking);

        return $start->modify('-' . $this->hours() . ' hours');
    }

    /**
     * Everything a page needs to say about one booking's window.
     *
     * @param array<string, mixed> $booking
     * @return array{
     *     hours: int,
     *     has_cut_off: bool,
     *     allowed: bool,
     *     can_manage: bool,
     *     deadline: string,
     *     deadline_label: string,
     *     deadline_iso: string,
     *     summary: string,
     *     reason: string
     * }
     */
    public function state(array $booking, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', $this->zone());
        $status = (string) ($booking['status'] ?? '');
        $canManage = in_array($status, ['requested', 'confirmed'], true);
        $deadline = $this->deadline($booking);
        $open = $this->hasCutOff() ? $now < $deadline : $now < $this->startOf($booking);

        $reason = '';

        if (!$canManage) {
            $reason = 'This appointment is ' . ($status === '' ? 'closed' : $status) . ' — there is nothing left to change.';
        } elseif (!$open) {
            $reason = $this->hasCutOff()
                ? 'The window for changing this appointment closed on '
                    . $this->formatDeadline($deadline) . '. Please call the salon.'
                : 'This appointment has already started. Please call the salon.';
        }

        return [
            'hours' => $this->hours(),
            'has_cut_off' => $this->hasCutOff(),
            'allowed' => $canManage && $open,
            'can_manage' => $canManage,
            'deadline' => $this->formatDeadline($deadline),
            'deadline_label' => $this->formatDeadline($deadline),
            'deadline_iso' => $deadline->format('c'),
            'summary' => $this->summary(),
            'reason' => $reason,
        ];
    }

    /**
     * Can this booking still be changed by the customer? The one question the
     * controller asks before it touches anything.
     *
     * @param array<string, mixed> $booking
     */
    public function allows(array $booking, ?DateTimeImmutable $now = null): bool
    {
        return $this->state($booking, $now)['allowed'];
    }

    /**
     * The window as the customer reads time in this salon: the Ethiopian clock
     * with the Gregorian date beside it.
     */
    public function formatDeadline(DateTimeImmutable $moment): string
    {
        $local = $moment->setTimezone($this->zone());
        $date = $local->format('Y-m-d');
        $clock = ethiopian_clock($local->format('H:i'));

        return ethiopian_date($date, 'long') . ' · ' . $local->format('j M Y') . ' at ' . $clock;
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function startOf(array $booking): DateTimeImmutable
    {
        $date = (string) ($booking['booking_date'] ?? '');
        $time = substr((string) ($booking['start_time'] ?? '00:00'), 0, 5);

        $start = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            $date . ' ' . $time,
            $this->zone(),
        );

        // A booking with a broken date is treated as "already started": refusing
        // a change is the safe side of that mistake.
        return $start !== false ? $start : new DateTimeImmutable('2000-01-01 00:00', $this->zone());
    }

    private function zone(): DateTimeZone
    {
        return new DateTimeZone($this->timezone);
    }
}
