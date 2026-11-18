<?php
declare(strict_types=1);

namespace Wiz\Domain\Booking;

/**
 * Thrown inside a transaction when the slot rows cannot be claimed, so the
 * booking insert is rolled back with them.
 */
final class SlotUnavailable extends \RuntimeException
{
}
