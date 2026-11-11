<?php

namespace NineteenNinetyFour\Newsletter\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use NineteenNinetyFour\Newsletter\Models\Subscriber;
use NineteenNinetyFour\Newsletter\Settings;

/**
 * "Please confirm your subscription": the link that puts someone on the
 * list. The wording comes from the settings; the views can be published.
 */
class ConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Settings::text('confirm_subject', $this->replacements(), $this->locale));
    }

    public function content(): Content
    {
        $site = $this->subscriber->siteObject();
        $replace = $this->replacements();

        return new Content(
            html: config('newsletter.views.confirm', 'newsletter::emails.confirm'),
            text: config('newsletter.views.confirm_text', 'newsletter::emails.confirm-text'),
            with: [
                'subscriber' => $this->subscriber,
                'locale' => str_replace('_', '-', $site->locale()),
                'siteName' => $site->name(),
                'siteUrl' => $site->absoluteUrl(),
                'colour' => Settings::colour(),
                'onColour' => Settings::onColour(),
                'heading' => Settings::text('confirm_heading', $replace, $this->locale),
                'intro' => Settings::text('confirm_intro', $replace, $this->locale),
                'button' => Settings::text('confirm_button', $replace, $this->locale),
                'confirmUrl' => $this->subscriber->confirmUrl(),
                'hours' => max(1, (int) config('newsletter.confirm_hours', 72)),
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(): array
    {
        return [
            'site' => $this->subscriber->siteObject()->name(),
            'email' => $this->subscriber->email,
            'name' => (string) $this->subscriber->name,
        ];
    }
}
