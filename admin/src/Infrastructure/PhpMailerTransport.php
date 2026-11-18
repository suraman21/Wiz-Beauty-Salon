<?php
declare(strict_types=1);

namespace Wiz\Infrastructure;

use Wiz\Contracts\MailTransport;
use Wiz\Core\Mailer;

/**
 * The only production transport: the PHPMailer adapter.
 */
final class PhpMailerTransport implements MailTransport
{
    public function __construct(private readonly Mailer $mailer)
    {
    }

    public function send(string $to, string $subject, string $html, string $text = '', ?string $replyTo = null): array
    {
        return $this->mailer->send($to, $subject, $html, $text, false, $replyTo);
    }
}
