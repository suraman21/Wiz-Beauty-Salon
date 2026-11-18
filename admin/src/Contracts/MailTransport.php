<?php
declare(strict_types=1);

namespace Wiz\Contracts;

/**
 * The seam every notification goes through.
 *
 * Callers depend on this, not on PHPMailer, so a test can record what would
 * have been sent and a future provider can be swapped in one class.
 */
interface MailTransport
{
    /**
     * @return array{ok: bool, error?: string, transport?: string}
     */
    public function send(string $to, string $subject, string $html, string $text = '', ?string $replyTo = null): array;
}
