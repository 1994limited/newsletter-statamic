<?php

namespace NineteenNinetyFour\Newsletter\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use NineteenNinetyFour\Newsletter\Http\ReadsText;
use NineteenNinetyFour\Newsletter\Models\Subscriber;
use NineteenNinetyFour\Newsletter\Newsletter;
use NineteenNinetyFour\Newsletter\Settings;
use NineteenNinetyFour\Newsletter\SubscriberStatus;
use Statamic\Facades\Site;

/**
 * The front-end actions, at /!/newsletter/... The form answers JSON to
 * fetch() (Accept: application/json) and redirects back to a plain form
 * post, with the message flashed as "newsletter_success", or errors in the
 * "newsletter" error bag.
 */
class ActionsController
{
    use ReadsText;

    public function __construct(protected Newsletter $newsletter) {}

    public function subscribe(Request $request): JsonResponse|RedirectResponse
    {
        // The same answer whether the address was new, already on the list,
        // or the form looked like a bot's: nothing in it says who's
        // subscribed.
        $thanks = __('newsletter::messages.subscribed');

        if ($this->text($request, (string) config('newsletter.honeypot', 'website')) !== null) {
            return $this->done($request, $thanks);
        }

        $email = $this->text($request, 'email', 254);
        $name = Settings::askName() ? $this->text($request, 'name', 255) : null;
        $errors = [];

        if ($email === null || ! $this->newsletter->isValidEmail(Subscriber::normaliseEmail($email))) {
            $errors['email'] = __('newsletter::messages.errors.email');
        }

        if (Settings::requireName() && ($name === null || trim($name) === '')) {
            $errors['name'] = __('newsletter::messages.errors.name');
        }

        if (Settings::consent() && ! $request->boolean('consent')) {
            $errors['consent'] = __('newsletter::messages.errors.consent');
        }

        if ($errors) {
            return $this->invalid($request, $errors);
        }

        $this->newsletter->subscribe(
            $email,
            $name,
            Site::current(),
            Settings::consent() ? Settings::text('consent_text') : null,
            $request->headers->get('referer'),
        );

        return $this->done($request, $thanks);
    }

    /**
     * The link in the confirmation email: a page with a button, so a link
     * checker opening it doesn't confirm anything.
     */
    public function confirmPage(Request $request, string $subscriber): Response
    {
        $subscriber = Subscriber::find($subscriber);

        if (! $subscriber || ! $request->hasValidSignature() || $subscriber->status === SubscriberStatus::Unsubscribed) {
            return $this->page('expired_title', __('newsletter::messages.errors.expired'), $subscriber, status: 403);
        }

        if ($subscriber->isSubscribed()) {
            return $this->page('confirmed_title', __('newsletter::messages.already_confirmed', ['email' => $subscriber->email]), $subscriber);
        }

        return $this->page('confirm_title', __('newsletter::messages.page.confirm_text', ['email' => $subscriber->email]), $subscriber, [
            'action' => $request->fullUrl(),
            'button' => Settings::text('confirm_button', [], $subscriber->locale),
        ]);
    }

    public function confirm(Request $request, string $subscriber): Response|RedirectResponse
    {
        $subscriber = Subscriber::find($subscriber);

        if (! $subscriber || ! $request->hasValidSignature() || $subscriber->status === SubscriberStatus::Unsubscribed) {
            return $this->page('expired_title', __('newsletter::messages.errors.expired'), $subscriber, status: 403);
        }

        $this->newsletter->confirm($subscriber);

        return $this->landing('confirmed', 'confirmed_title', __('newsletter::messages.confirmed', ['email' => $subscriber->email]), $subscriber);
    }

    /**
     * The unsubscribe link opens a page with a button, so link checkers
     * that open links in emails don't unsubscribe anyone.
     */
    public function unsubscribePage(string $subscriber): Response
    {
        $subscriber = Subscriber::find($subscriber);

        if (! $subscriber) {
            return $this->page('unsubscribed_title', __('newsletter::messages.already_unsubscribed'));
        }

        if ($subscriber->status === SubscriberStatus::Unsubscribed) {
            return $this->page('unsubscribed_title', __('newsletter::messages.unsubscribed', ['email' => $subscriber->email]), $subscriber);
        }

        return $this->page('unsubscribe_title', __('newsletter::messages.page.unsubscribe_text', ['email' => $subscriber->email]), $subscriber, [
            'action' => URL::signedRoute('statamic.newsletter.unsubscribe.post', ['subscriber' => $subscriber->id]),
            'button' => __('newsletter::messages.page.unsubscribe_button'),
        ]);
    }

    public function unsubscribe(Request $request, string $subscriber): Response|RedirectResponse
    {
        $subscriber = Subscriber::find($subscriber);

        // Deleted since the email: nothing left to stop.
        if (! $subscriber) {
            return $request->input('List-Unsubscribe') === 'One-Click'
                ? response('', 200)
                : $this->page('unsubscribed_title', __('newsletter::messages.already_unsubscribed'));
        }

        $this->newsletter->unsubscribe($subscriber);

        // Mail apps' one-click unsubscribe just needs a 200.
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('', 200);
        }

        return $this->landing('unsubscribed', 'unsubscribed_title', __('newsletter::messages.unsubscribed', ['email' => $subscriber->email]), $subscriber);
    }

    /**
     * After confirming or unsubscribing: the site's own page, if one's set
     * in config('newsletter.pages'), or the addon's.
     */
    protected function landing(string $page, string $title, string $message, Subscriber $subscriber): Response|RedirectResponse
    {
        $to = config("newsletter.pages.{$page}");

        if (is_string($to) && $to !== '') {
            return redirect($to)->with('newsletter_success', $message);
        }

        return $this->page($title, $message, $subscriber);
    }

    /**
     * @param  array<string, string>|null  $form
     */
    protected function page(string $title, string $message, ?Subscriber $subscriber = null, ?array $form = null, int $status = 200): Response
    {
        $site = $subscriber?->siteObject() ?? Site::current();

        app()->setLocale($subscriber?->locale ?? $site->lang());

        return response()->view(config('newsletter.views.page', 'newsletter::page'), [
            'title' => __('newsletter::messages.page.'.$title),
            'message' => $message,
            'form' => $form,
            'siteName' => $site->name(),
            'siteUrl' => $site->absoluteUrl(),
            'locale' => str_replace('_', '-', $site->locale()),
            'colour' => Settings::colour(),
            'onColour' => Settings::onColour(),
        ], $status)->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }

    protected function done(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return $this->back($request, '_redirect')->with('newsletter_success', $message);
    }

    /**
     * @param  array<string, string>  $errors
     */
    protected function invalid(Request $request, array $errors, int $status = 422): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => reset($errors), 'errors' => $errors], $status);
        }

        return $this->back($request, '_error_redirect')
            ->withErrors($errors, 'newsletter')
            ->withInput($request->only(['email', 'name', 'consent']));
    }

    /**
     * Back to where the form was, or to a "_redirect" on this site.
     */
    protected function back(Request $request, string $field): RedirectResponse
    {
        $to = (string) $this->text($request, $field);
        $home = rtrim(url('/'), '/').'/';

        // A path on this site, or a full URL that starts with this site's.
        // Nothing with a backslash or control characters, which browsers
        // read differently from PHP.
        $safe = $to !== '' && ! preg_match('/[\\\\\x00-\x1F]/', $to);
        $local = $safe && ((str_starts_with($to, '/') && ! str_starts_with($to, '//')) || str_starts_with($to, $home));

        return $local ? redirect($to) : back();
    }
}
