<?php
declare(strict_types=1);

namespace Wiz\Core;

/**
 * Daily-rotated file logger.
 *
 * Writes to storage/logs/app-YYYY-MM-DD.log. Never logs credentials: callers
 * pass a context array and it is scrubbed before writing.
 */
final class Logger
{
    public function __construct(private readonly string $logPath)
    {
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0755, true);
        }

        $file = $this->logPath . '/app-' . date('Y-m-d') . '.log';
        $entry = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . json_encode($this->scrub($context), JSON_UNESCAPED_SLASHES),
        );

        @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function scrub(array $context): array
    {
        $secrets = ['password', 'password_confirmation', 'token', '_token', 'secret', 'authorization'];

        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $secrets, true)) {
                $context[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $context[$key] = $this->scrub($value);
            }
        }

        return $context;
    }
}
