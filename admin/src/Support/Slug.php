<?php
declare(strict_types=1);

namespace Wiz\Support;

/**
 * URL-safe slugs.
 *
 * Slugs are generated once, when an item is created, and then left alone: a
 * public URL that changes because someone corrected a typo in a title is a
 * broken link. Editing a name therefore does not move the page.
 */
final class Slug
{
    public static function make(string $value, string $fallback = 'item'): string
    {
        $value = mb_strtolower(trim($value));

        // Common accented characters transliterate cleanly; anything else
        // outside a-z0-9 becomes a separator.
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss', '&' => ' and ',
        ]);

        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '') {
            $value = $fallback;
        }

        return substr($value, 0, 150);
    }

    /**
     * Append -2, -3 … until the candidate is free.
     *
     * @param callable(string): bool $taken
     */
    public static function unique(string $base, callable $taken): string
    {
        $candidate = $base;
        $suffix = 1;

        while ($taken($candidate)) {
            $suffix++;
            $candidate = substr($base, 0, 145) . '-' . $suffix;
        }

        return $candidate;
    }
}
