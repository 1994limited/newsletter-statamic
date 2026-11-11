<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The form
    |--------------------------------------------------------------------------
    |
    | These can also be changed in the Control Panel: Tools → Addons →
    | Newsletter → Settings.
    |
    | consent: a tick box people must tick, with consent_text next to it
    | (null for the wording in lang/). The wording is saved with each
    | sign-up, as a record of what they agreed to. False for no tick box:
    | the confirmation email is their consent.
    |
    */

    'consent' => true,

    'consent_text' => null,

    'ask_name' => false,

    'require_name' => false,

    /*
    |--------------------------------------------------------------------------
    | Confirming
    |--------------------------------------------------------------------------
    |
    | Every sign-up is confirmed from a link emailed to the address given,
    | so nobody can sign someone else up. Links last this many hours.
    |
    | pages: where people land after confirming or unsubscribing. Null shows
    | the addon's own page (publish the views to restyle it). A URL on your
    | site, such as '/newsletter/thanks', redirects there instead, with the
    | message in {{ newsletter:success }}.
    |
    */

    'confirm_hours' => 72,

    'pages' => [
        'confirmed' => null,
        'unsubscribed' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Emails
    |--------------------------------------------------------------------------
    |
    | queue: null sends the confirmation email straight away, while the
    | visitor waits. A queue name ('default') sends it from your queue.
    |
    | notify: email addresses told about each confirmed sign-up.
    |
    */

    'email' => [
        'queue' => null,
        'notify' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Keeping data
    |--------------------------------------------------------------------------
    |
    | Sign-ups nobody confirmed are deleted after this many days by `php
    | please newsletter:prune`, which runs daily with Laravel's scheduler.
    | Null keeps them.
    |
    */

    'prune_days' => 30,

    'schedule' => true,

    /*
    |--------------------------------------------------------------------------
    | Spam
    |--------------------------------------------------------------------------
    |
    | throttle: sign-ups a minute from one visitor (by IP address).
    | per_email: confirmation emails an hour to one address, so the form
    | can't be used to flood someone's inbox.
    | honeypot: a hidden field people can't see; forms that fill it in are
    | quietly thrown away. Change it if it clashes with a field of yours.
    |
    */

    'throttle' => 10,

    'per_email' => 3,

    'honeypot' => 'website',

    /*
    |--------------------------------------------------------------------------
    | Captcha
    |--------------------------------------------------------------------------
    |
    | A captcha on the form, as well as the honeypot and limits above:
    | "turnstile" (Cloudflare), "hcaptcha", "recaptcha" (Google's v2
    | checkbox), "recaptcha_v3" (scored, nothing to tick), or the class of
    | your own (implementing NineteenNinetyFour\Captcha\Captcha). Off until a
    | driver and both keys are set. Captchas need JavaScript, so with one on,
    | the form no longer works without it.
    |
    | score: reCAPTCHA v3 only, the lowest score taken as a person.
    |
    */

    'captcha' => [
        'driver' => env('NEWSLETTER_CAPTCHA'),
        'site_key' => env('NEWSLETTER_CAPTCHA_SITE_KEY'),
        'secret_key' => env('NEWSLETTER_CAPTCHA_SECRET_KEY'),
        'score' => 0.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | To change a little, publish the addon's views (php artisan
    | vendor:publish --tag=newsletter-views) and edit them. Or point these at
    | your own views.
    |
    */

    'views' => [
        'form' => 'newsletter::form',
        'confirm' => 'newsletter::emails.confirm',
        'confirm_text' => 'newsletter::emails.confirm-text',
        'notify_text' => 'newsletter::emails.notify-text',
        'page' => 'newsletter::page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Subscribers are kept in a database table, whichever way Statamic keeps
    | its own content. Null uses the default connection.
    |
    */

    'database' => [
        'connection' => null,
    ],

];
