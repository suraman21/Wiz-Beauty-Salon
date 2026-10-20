<?php
declare(strict_types=1);

namespace Wiz\Support;

use Wiz\Repositories\SettingsRepository;

/**
 * The salon's own contact details.
 *
 * These live in the `settings` table so the panel — not the source code — owns
 * what the website prints. Before this existed the site carried placeholders
 * such as "[Phone number — to be added]", which could only be replaced by a
 * developer.
 *
 * A blank value means "not published yet": the site hides that channel rather
 * than showing an empty row or a made-up number.
 */
final class SalonDetails
{
    public const PHONE = 'salon.phone';
    public const WHATSAPP = 'salon.whatsapp';
    public const EMAIL = 'salon.email';
    public const INSTAGRAM = 'salon.instagram';
    public const TIKTOK = 'salon.tiktok';
    public const ADDRESS = 'salon.address';

    /** key => maximum stored length. Handles are stored without the @. */
    private const LIMITS = [
        self::PHONE => 32,
        self::WHATSAPP => 32,
        self::EMAIL => 190,
        self::INSTAGRAM => 60,
        self::TIKTOK => 60,
        self::ADDRESS => 190,
    ];

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /**
     * @return array{phone: string, whatsapp: string, email: string, instagram: string, tiktok: string, address: string}
     */
    public function all(): array
    {
        return [
            'phone' => $this->read(self::PHONE),
            'whatsapp' => $this->read(self::WHATSAPP),
            'email' => $this->read(self::EMAIL),
            'instagram' => $this->read(self::INSTAGRAM),
            'tiktok' => $this->read(self::TIKTOK),
            'address' => $this->read(self::ADDRESS),
        ];
    }

    /**
     * Store what the administrator typed, cleaned but not reformatted: a phone
     * number keeps its spaces and a handle loses only the @ and any URL prefix,
     * so what the salon printed elsewhere still looks the same here.
     *
     * @param array<string, string> $values
     */
    public function save(array $values): void
    {
        foreach (self::LIMITS as $key => $limit) {
            if (!array_key_exists($key, $values)) {
                continue;
            }

            $this->settings->put($key, $this->clean($key, $values[$key], $limit));
        }
    }

    /**
     * True when nothing has been published yet, so the site can say so once
     * instead of printing five empty rows.
     */
    public function isEmpty(): bool
    {
        foreach ($this->all() as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    private function read(string $key): string
    {
        return trim((string) $this->settings->get($key, ''));
    }

    private function clean(string $key, string $value, int $limit): string
    {
        // Strip control characters, collapse runs of whitespace, then cap the
        // length — the column is a setting, not free-form HTML.
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($value));
        $value = (string) preg_replace('/\s{2,}/u', ' ', $value);

        if ($key === self::INSTAGRAM || $key === self::TIKTOK) {
            $value = $this->handle($value);
        }

        if ($limit > 0 && mb_strlen($value) > $limit) {
            $value = mb_substr($value, 0, $limit);
        }

        return $value;
    }

    /**
     * "https://instagram.com/wizbeauty/", "@wizbeauty" and "wizbeauty" all mean
     * the same account; store the handle so one shape goes out to the site.
     */
    private function handle(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $value) === 1) {
            $path = (string) parse_url($value, PHP_URL_PATH);
            $value = trim($path, '/');
        }

        $value = ltrim($value, '@');

        return (string) preg_replace('/[^A-Za-z0-9._]/', '', $value);
    }
}
