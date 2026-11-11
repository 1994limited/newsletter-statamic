<?php

namespace NineteenNinetyFour\Newsletter\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use NineteenNinetyFour\Newsletter\Models\Subscriber;

/**
 * To the site's team: someone confirmed their sign-up.
 */
class NewSubscriberMail extends Mailable
{
    public function __construct(public Subscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('newsletter::messages.notify.subject', ['email' => $this->subscriber->email]));
    }

    public function content(): Content
    {
        return new Content(
            text: config('newsletter.views.notify_text', 'newsletter::emails.notify-text'),
            with: [
                'subscriber' => $this->subscriber,
                'count' => Subscriber::query()->subscribed()->count(),
                'listUrl' => cp_route('newsletter.index'),
            ],
        );
    }
}
