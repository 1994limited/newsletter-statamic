<?php

namespace NineteenNinetyFour\Newsletter;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use NineteenNinetyFour\Newsletter\Events\Confirmed;
use NineteenNinetyFour\Newsletter\Events\Subscribing;
use NineteenNinetyFour\Newsletter\Events\Unsubscribed;
use NineteenNinetyFour\Newsletter\Mail\ConfirmMail;
use NineteenNinetyFour\Newsletter\Mail\NewSubscriberMail;
use NineteenNinetyFour\Newsletter\Models\Subscriber;
use Statamic\Facades\Site;
use Throwable;

/**
 * Signing up, confirming and unsubscribing, and the list as rows for a
 * spreadsheet.
 */
class Newsletter
{
    /**
     * Someone asking to be on the list. A new address (or one that had
     * unsubscribed) waits to confirm, and is sent the link; an address
     * that's already subscribed is left as it is and sent nothing.
     */
    public function subscribe(string $email, ?string $name = null, ?\Statamic\Sites\Site $site = null, ?string $consent = null, ?string $sourceUrl = null): Subscriber
    {
        $email = Subscriber::normaliseEmail($email);
        $name = $name !== null && trim($name) !== '' ? trim($name) : null;
        $site ??= Site::current();

        $subscriber = Subscriber::query()->where('email', $email)->first() ?? new Subscriber(['email' => $email]);

        if ($subscriber->exists && $subscriber->isSubscribed()) {
            if ($name !== null && ! $subscriber->name) {
                $subscriber->update(['name' => $name]);
            }

            return $subscriber;
        }

        $subscriber->fill([
            'status' => SubscriberStatus::Pending,
            'name' => $name ?? $subscriber->name,
            'site' => $site->handle(),
            'locale' => $site->lang(),
            'consent' => $consent,
            'source_url' => $sourceUrl !== null ? mb_substr($sourceUrl, 0, 500) : null,
        ]);

        // A listener can turn a sign-up away by returning false.
        if (event(new Subscribing($subscriber), halt: true) === false) {
            return $subscriber;
        }

        $subscriber->save();

        $this->sendConfirmation($subscriber);

        return $subscriber;
    }

    /**
     * Email the confirmation link, unless the address has had too many
     * lately (so the form can't be used to flood someone's inbox).
     */
    public function sendConfirmation(Subscriber $subscriber): bool
    {
        if (! $subscriber->isPending()) {
            return false;
        }

        $key = 'newsletter:confirm:'.hash('sha256', $subscriber->email);

        if (RateLimiter::tooManyAttempts($key, max(1, (int) config('newsletter.per_email', 3)))) {
            return false;
        }

        RateLimiter::hit($key, 3600);

        $mail = (new ConfirmMail($subscriber))->locale($subscriber->locale);
        $queue = config('newsletter.email.queue');

        try {
            is_string($queue) && $queue !== ''
                ? Mail::to($subscriber->email)->queue($mail->onQueue($queue))
                : Mail::to($subscriber->email)->send($mail);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $subscriber->forceFill(['confirmation_sent_at' => now()])->save();

        return true;
    }

    /**
     * Following the confirmation link. False if there was nothing to
     * confirm (already subscribed, or unsubscribed since).
     */
    public function confirm(Subscriber $subscriber): bool
    {
        if (! $subscriber->isPending()) {
            return false;
        }

        $subscriber->forceFill([
            'status' => SubscriberStatus::Subscribed,
            'confirmed_at' => now(),
            'unsubscribed_at' => null,
        ])->save();

        event(new Confirmed($subscriber));

        if ($notify = Settings::notify()) {
            try {
                Mail::to($notify)->send(new NewSubscriberMail($subscriber));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return true;
    }

    /**
     * False if they weren't on the list.
     */
    public function unsubscribe(Subscriber $subscriber): bool
    {
        if ($subscriber->status === SubscriberStatus::Unsubscribed) {
            return false;
        }

        $subscriber->forceFill([
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ])->save();

        event(new Unsubscribed($subscriber));

        return true;
    }

    /**
     * Delete sign-ups that were never confirmed, after the days in the
     * config. Null keeps them.
     */
    public function prune(): int
    {
        $days = config('newsletter.prune_days', 30);

        if ($days === null || (int) $days < 1) {
            return 0;
        }

        return Subscriber::query()
            ->where('status', SubscriberStatus::Pending->value)
            ->where('created_at', '<', now()->subDays((int) $days))
            ->delete();
    }

    public function isValidEmail(string $email): bool
    {
        return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    // The spreadsheet.

    /**
     * @return array<int, string>
     */
    public function csvHeadings(): array
    {
        return ['Email', 'Name', 'Status', 'Site', 'Signed up', 'Confirmed', 'Unsubscribed', 'Agreed to', 'Form page', 'Unsubscribe link'];
    }

    /**
     * @return array<int, string>
     */
    public function csvRow(Subscriber $subscriber): array
    {
        return array_map([self::class, 'csvCell'], [
            $subscriber->email,
            $subscriber->name,
            $subscriber->status->value,
            $subscriber->site,
            $subscriber->created_at?->toDateTimeString(),
            $subscriber->confirmed_at?->toDateTimeString(),
            $subscriber->unsubscribed_at?->toDateTimeString(),
            $subscriber->consent,
            $subscriber->source_url,
            $subscriber->unsubscribeUrl(),
        ]);
    }

    /**
     * A spreadsheet cell that can't be read as a formula.
     */
    public static function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
