<?php

namespace App\Mail;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Every e-mail of Kekeli, in French, sober, with its design (readable on a phone, dark mode) and a text version: a
 * title, short paragraphs, sometimes a big code, a details table, one button, and the contact at the bottom. Never a
 * password nor a key in an e-mail.
 */
class KekeliMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<string>  $paragraphs
     * @param  array<string, string>  $details  label => value
     * @param  array{label: string, url: string}|null  $button
     * @param  array{disk: string, path: string, name: string}|null  $file
     */
    public function __construct(
        public string $subjectLine,
        public string $title,
        public array $paragraphs = [],
        public ?string $code = null,
        public array $details = [],
        public ?array $button = null,
        public ?string $note = null,
        public ?array $file = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(html: 'emails.message', text: 'emails.message-text', with: ['footer' => self::footer()]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return $this->file ? [Attachment::fromStorageDisk($this->file['disk'], $this->file['path'])->as($this->file['name'])->withMime('application/pdf')] : [];
    }

    /**
     * The bottom of every e-mail: who sends it and how to reach a person (Administration → Réglages).
     *
     * @return array{name: string, address: string, phones: list<string>, email: string}
     */
    public static function footer(): array
    {
        $settings = app(Settings::class);
        $issuer = (array) $settings->get('receipt_issuer');

        return [
            'name' => (string) ($issuer['name'] ?? 'Lomé IA School'),
            'address' => (string) ($issuer['address'] ?? ''),
            'phones' => array_values(array_filter(array_map('trim', (array) $settings->get('contact_phones')))),
            'email' => (string) (($issuer['email'] ?? '') ?: config('mail.from.address')),
        ];
    }
}
