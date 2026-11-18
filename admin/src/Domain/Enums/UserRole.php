<?php
declare(strict_types=1);

namespace Wiz\Domain\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Staff => 'Staff',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access, including settings and user accounts.',
            self::Staff => 'Bookings, messages and content. No settings or users.',
        };
    }
}
