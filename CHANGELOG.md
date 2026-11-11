# Changelog

## Unreleased

### Added
- A captcha on the form, if you want one: Cloudflare Turnstile, hCaptcha, reCAPTCHA v2 or v3 (set `NEWSLETTER_CAPTCHA` and its keys), or your own. `{{ captcha }}` and `{{ error:captcha }}` for your own fields.

## 1.0.0 - 2026-11-11

### Added
- A sign-up form: `{{ newsletter }}`, or your own fields inside `{{ newsletter }}…{{ /newsletter }}`, posting to `/!/newsletter/subscribe` (JSON too).
- Double opt-in: a signed confirmation link, good for 72 hours, opening a page with a button.
- Tools → Newsletter: counts, search, a status filter and delete.
- CSV download of the confirmed list, or what's filtered, with each subscriber's unsubscribe link.
- Unsubscribe links, with one-click unsubscribe (RFC 8058).
- The consent wording, the form's page and the dates kept with each sign-up.
- A honeypot, a limit on sign-ups per visitor and on confirmation emails per address.
- `php please newsletter:prune`, run daily, deletes unconfirmed sign-ups after 30 days.
- Settings for the form and the confirmation email, and an email to the team about new subscribers.
- `{{ newsletter:count }}`, `{{ newsletter:success }}`, `{{ newsletter:errors }}`, and `Subscribing`, `Confirmed` and `Unsubscribed` events.
