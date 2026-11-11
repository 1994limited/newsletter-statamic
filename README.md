# Newsletter for Statamic

Collect newsletter subscribers on your own Statamic site. Add `{{ newsletter }}` to a template; people who sign up are emailed a link to confirm (double opt-in), and only then join the list. The list is in the Control Panel, ready to download as a CSV for Mailchimp, Brevo, Campaign Monitor or whatever you send with.

Newsletter doesn't send newsletters. It keeps a clean, consented list, and hands it over.

## Features

- **A form in one tag.** `{{ newsletter }}` gives you an accessible sign-up form that works without JavaScript; `{{ newsletter }}…{{ /newsletter }}` wraps your own fields. JSON too, for `fetch()`.
- **Double opt-in.** Each sign-up is emailed a signed link that lasts 72 hours. The link opens a page with a button, so mail scanners that open links don't confirm anyone by themselves.
- **A record of consent.** The wording next to the tick box is saved with each sign-up, with when they signed up, when they confirmed and the page the form was on.
- **The list in the Control Panel** (Tools → Newsletter): counts, search, a filter by status, and delete.
- **CSV download.** The confirmed list, or whatever the page is showing. Each row carries the subscriber's own unsubscribe link, to merge into the emails you send. Cells can't be read as spreadsheet formulas.
- **Unsubscribe links** that work with mail apps' one-click unsubscribe.
- **No leaks, little spam.** The form says the same thing whether an address is new or already subscribed. A hidden honeypot field, a limit on sign-ups per visitor and a limit on confirmation emails per address, and a captcha if you want one: Cloudflare Turnstile, hCaptcha, reCAPTCHA v2 or v3, or your own.
- **Tidy.** Sign-ups nobody confirmed are deleted after 30 days.
- **Your wording.** The confirmation email's subject, heading, text, button and colour in the addon's settings, in each site's language when left empty. Anyone can be told about new subscribers by email.

## Requirements

Statamic 6, PHP 8.3 or later, a database (subscribers are kept in a table, whichever way Statamic keeps its content) and Laravel's mail set up.

## Installation

```bash
composer require 1994/newsletter-statamic
php artisan migrate
```

Then add `{{ newsletter }}` to a template.

## The form

The ready-made form:

```antlers
{{ newsletter }}

{{ newsletter button="Sign me up" redirect="/newsletter/thanks" }}
```

To change it: `php artisan vendor:publish --tag=newsletter-views` and edit `resources/views/vendor/newsletter/form.blade.php`.

Or your own fields, inside the form (the CSRF token, the honeypot and the action are added for you):

```antlers
{{ newsletter }}
    {{ if success }}<p>{{ success }}</p>{{ /if }}

    <input type="email" name="email" value="{{ email }}" required>
    {{ if error:email }}<p>{{ error:email }}</p>{{ /if }}

    {{ if consent }}
        <label><input type="checkbox" name="consent" value="1" required> {{ consent_text }}</label>
        {{ if error:consent }}<p>{{ error:consent }}</p>{{ /if }}
    {{ /if }}

    <button>Subscribe</button>
{{ /newsletter }}
```

Inside: `email`, `name`, `ask_name`, `require_name`, `consent`, `consent_text`, `consent_checked`, `captcha`, `success`, `errors` and `error:email`, `error:name`, `error:consent`, `error:captcha`. Everything a visitor typed is escaped. Parameters: `redirect`, `error_redirect`, `button`, and any HTML attributes (`class="…"`).

Also `{{ newsletter:success }}`, `{{ newsletter:errors }}{{ value }}{{ /newsletter:errors }}` and `{{ newsletter:count }}` (confirmed subscribers).

With `Accept: application/json`, `/!/newsletter/subscribe` answers `{"success": true, "message": "…"}`, or `{"success": false, "errors": {…}}` with a 422 (429 when a visitor signs up too often).

On a statically cached page, put the form inside `{{ nocache }}`: its CSRF token and messages are different for everyone.

## Confirming and unsubscribing

The confirmation link and each subscriber's unsubscribe link open a small page of the addon's own, with a button. To send people to pages of your own instead, set `pages` in `config/newsletter.php`; the message is in `{{ newsletter:success }}`.

The unsubscribe link is in the CSV (**Unsubscribe link**). Put it in the emails you send, and in a `List-Unsubscribe` header for one-click unsubscribe.

## The list

Tools → Newsletter. **Download subscribed (CSV)** gets the confirmed list; with a filter or a search, the button downloads what's shown. The columns: Email, Name, Status, Site, Signed up, Confirmed, Unsubscribed, Agreed to, Form page and Unsubscribe link.

Permissions: **See the newsletter list**, and under it **Download it as CSV** and **Delete subscribers**.

## Settings

Tools → Addons → Newsletter → Settings: the tick box and its wording, asking for a name, who's told about new subscribers, and the confirmation email's wording and colour.

Everything else is in the config: `php artisan vendor:publish --tag=newsletter-config`.

```php
'confirm_hours' => 72,
'pages' => ['confirmed' => null, 'unsubscribed' => null],
'email' => ['queue' => null, 'notify' => []],  // a queue name sends the confirmation from your queue
'prune_days' => 30,     // null keeps unconfirmed sign-ups
'throttle' => 10,       // sign-ups a minute per visitor
'per_email' => 3,       // confirmation emails an hour per address
'honeypot' => 'website',
```

Unconfirmed sign-ups are deleted by `php please newsletter:prune`, run daily by Laravel's scheduler (`'schedule' => false` to run it yourself).

## Captcha

The honeypot and limits stop most bots. For more, add a captcha in `.env`:

```dotenv
NEWSLETTER_CAPTCHA=turnstile
NEWSLETTER_CAPTCHA_SITE_KEY=...
NEWSLETTER_CAPTCHA_SECRET_KEY=...
```

`turnstile` (Cloudflare), `hcaptcha`, `recaptcha` (Google's v2 checkbox) or `recaptcha_v3` (scored, nothing to tick; `captcha.score` in the config is the lowest score let through). It's off until the driver and both keys are set. Captchas need JavaScript, so with one on, the form no longer works without it.

The ready-made form shows the widget above its button. In your own fields, put `{{ captcha }}` where it should go, and `{{ error:captcha }}` for its message; left out, it's added at the end of the form. A failed check answers `errors.captcha` in JSON.

Your own: set `NEWSLETTER_CAPTCHA` to a class implementing `NineteenNinetyFour\Captcha\Captcha` (from [1994/captcha](https://github.com/1994limited/captcha)), resolved from the container.

## Events

```php
use Illuminate\Support\Facades\Event;
use NineteenNinetyFour\Newsletter\Events\Confirmed;

// Send confirmed subscribers on to your email platform.
Event::listen(Confirmed::class, function (Confirmed $event) {
    // $event->subscriber->email, ->name
});
```

Also `Subscribing` (return `false` to turn a sign-up away; the visitor is still told it worked) and `Unsubscribed`.

## Personal data

Newsletter keeps each address, the name if you ask for one, the site, the form's page, the consent wording and the dates. No IP addresses: Laravel's rate limiter counts sign-ups per visitor in your cache for a minute. Delete a subscriber in the Control Panel to forget them.

## Support

Email [hello@1994.co.uk](mailto:hello@1994.co.uk), or open an issue on GitHub.
