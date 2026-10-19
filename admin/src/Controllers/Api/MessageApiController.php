<?php
declare(strict_types=1);

namespace Wiz\Controllers\Api;

use Throwable;
use Wiz\Core\HttpException;
use Wiz\Core\Mailer;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Validator;
use Wiz\Repositories\MessageRepository;
use Wiz\Repositories\RateLimitRepository;
use Wiz\Support\Contact;
use Wiz\Support\Origin;

/**
 * The contact form.
 *
 * One endpoint, three defences: a honeypot field real people never fill in, a
 * per-IP limit, and the same Origin check the booking endpoint uses. The note
 * is stored first and emailed second, so a mail server having a bad morning
 * never loses an enquiry.
 */
final class MessageApiController
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly RateLimitRepository $limits,
        private readonly Mailer $mailer,
        private readonly string $appOrigin = '',
        private readonly string $salonEmail = '',
    ) {
    }

    /**
     * POST /api/v1/messages
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        Origin::assertSame($request, $this->appOrigin);

        $ip = $request->ip();

        if (!$this->limits->attempt('message:' . $ip, 5, 60)) {
            throw new HttpException(429, 'Too many messages from this connection. Please try again later.');
        }

        if ($request->string('website') !== '') {
            throw HttpException::validation('The message could not be accepted.');
        }

        $validator = new Validator(
            [
                'name' => $request->string('name'),
                'contact' => $request->string('contact'),
                'message' => $request->string('message'),
            ],
            [
                'name' => 'required|max:120',
                'contact' => 'required|max:190',
                'message' => 'required|max:2000',
            ],
            [
                'name' => 'Name',
                'contact' => 'Email or phone',
                'message' => 'Message',
            ],
        );

        if (!$validator->validate()) {
            throw HttpException::validation((string) $validator->firstError());
        }

        $input = $validator->validated();
        $contact = trim((string) $input['contact']);
        $split = Contact::split($contact);

        $email = $split['email'];
        $phone = $split['phone'];

        if ($phone !== null && mb_strlen($phone) > 40) {
            throw HttpException::validation('Please write an email address or a phone number we can reply to.');
        }

        $this->messages->create(
            (string) $input['name'],
            $email,
            $phone,
            (string) $input['message'],
            $ip,
        );

        $this->tellTheSalon((string) $input['name'], $contact, (string) $input['message'], $email);

        return Response::json([
            'ok' => true,
            'message' => 'Thank you. The salon will get back to you soon.',
        ], 201);
    }

    /**
     * Best-effort: the note is already saved, so a failure here is a nudge, not
     * an error the visitor should see.
     */
    private function tellTheSalon(string $name, string $contact, string $message, ?string $replyTo = null): void
    {
        if (trim($this->salonEmail) === '') {
            return;
        }

        try {
            $subject = 'New message from ' . $name;
            $text = "From: {$name}\nReply to: {$contact}\n\n{$message}\n";

            $this->mailer->send($this->salonEmail, $subject, $text, '', $replyTo);
        } catch (Throwable) {
            // Logged inside Mailer; nothing else to do.
        }
    }
}
