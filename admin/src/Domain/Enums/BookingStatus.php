<?php
declare(strict_types=1);

namespace Wiz\Domain\Enums;

/**
 * Lifecycle of a booking request.
 *
 * `requested` and `confirmed` occupy the slot; the others release it.
 */
enum BookingStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'New request',
            self::Confirmed => 'Confirmed',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::NoShow => 'No-show',
        };
    }

    /**
     * Whether this status keeps the appointment in the schedule.
     */
    public function occupiesSlot(): bool
    {
        return match ($this) {
            self::Requested, self::Confirmed => true,
            default => false,
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Requested => false,
            default => true,
        };
    }

    /**
     * Allowed next statuses, used to build the admin action buttons and to
     * validate transitions on the server.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Confirmed, self::Declined, self::Cancelled],
            self::Confirmed => [self::Completed, self::NoShow, self::Cancelled],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Requested => 'badge badge--amber',
            self::Confirmed => 'badge badge--green',
            self::Completed => 'badge badge--blue',
            self::Declined, self::Cancelled, self::NoShow => 'badge badge--grey',
        };
    }
}
