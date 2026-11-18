<?php
declare(strict_types=1);

namespace Wiz\Support;

/**
 * The contact form asks for "email or phone" in one box, because that is what
 * people expect to type. The database keeps the two apart so the salon can
 * click a mail link or a call link depending on what was given.
 */
final class Contact
{
    /**
     * @return array{email: string|null, phone: string|null}
     */
    public static function split(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return ['email' => null, 'phone' => null];
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return ['email' => $value, 'phone' => null];
        }

        return ['email' => null, 'phone' => $value];
    }
}
