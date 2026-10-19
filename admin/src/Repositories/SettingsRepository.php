<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * Key/value settings.
 *
 * Defaults live in code, not in the table: a missing row means "use the
 * sensible value", so a fresh install behaves correctly before anything has
 * been saved. The settings screen (Phase 5) will write into this table.
 */
final class SettingsRepository
{
    /** @var array<string, string> */
    private const DEFAULTS = [
        'booking.slot_minutes' => '30',
        'booking.lead_minutes' => '120',
        'booking.max_advance_days' => '60',
    ];

    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(private readonly Database $db)
    {
    }

    public function get(string $key, ?string $default = null): string
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $fallback = $default ?? self::DEFAULTS[$key] ?? '';

        try {
            $value = $this->db->scalar(
                'SELECT setting_value FROM settings WHERE setting_key = :key',
                [':key' => $key],
            );
        } catch (\Throwable) {
            return $fallback;
        }

        $resolved = is_string($value) && $value !== '' ? $value : $fallback;

        return $this->cache[$key] = $resolved;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === '' ? $default : (int) $value;
    }

    public function put(string $key, string $value): void
    {
        $this->db->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [':key' => $key, ':value' => $value],
        );

        $this->cache[$key] = $value;
    }
}
