<?php
declare(strict_types=1);

namespace Wiz\Tests\Support;

use Wiz\Contracts\MailTransport;

/**
 * Captures what would have been sent, so tests can assert on recipients,
 * subjects and bodies without touching a mail server.
 */
final class RecordingTransport implements MailTransport
{
    /** @var list<array{to: string, subject: string, html: string, text: string, replyTo: string|null}> */
    public array $sent = [];

    public function send(string $to, string $subject, string $html, string $text = '', ?string $replyTo = null): array
    {
        $this->sent[] = [
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
            'replyTo' => $replyTo,
        ];

        return ['ok' => true, 'transport' => 'test'];
    }

    /**
     * @return list<array{to: string, subject: string, html: string, text: string, replyTo: string|null}>
     */
    public function to(string $address): array
    {
        return array_values(array_filter($this->sent, static fn (array $m): bool => $m['to'] === $address));
    }
}
