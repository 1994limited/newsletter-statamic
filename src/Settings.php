<?php

namespace NineteenNinetyFour\Newsletter;

use Statamic\Facades\Addon;
use Statamic\Facades\Site;
use Throwable;

/**
 * The addon's settings, edited in the Control Panel (Tools → Addons →
 * Newsletter → Settings). Each falls back to config/newsletter.php, or to
 * the wording in lang/, so an empty setting means "the default".
 * Placeholders are written :like_this.
 */
class Settings
{
    public const ADDON = '1994/newsletter-statamic';

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            // Only what's been saved: the blueprint's defaults come from the config.
            $value = Addon::get(self::ADDON)?->settings()->raw()[$key] ?? null;
        } catch (Throwable) {
            $value = null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' || $value === null ? $default : $value;
    }

    public static function consent(): bool
    {
        return (bool) self::get('consent', config('newsletter.consent', true));
    }

    public static function askName(): bool
    {
        return (bool) self::get('ask_name', config('newsletter.ask_name', false));
    }

    public static function requireName(): bool
    {
        return self::askName() && (bool) self::get('require_name', config('newsletter.require_name', false));
    }

    /**
     * @return array<int, string>
     */
    public static function notify(): array
    {
        $value = self::get('notify', config('newsletter.email.notify', []));
        $value = is_string($value) ? preg_split('/[\s,;]+/', $value) : (array) $value;

        return array_values(array_filter(array_map('trim', array_filter($value, 'is_string')), fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
    }

    /**
     * A piece of wording, with its placeholders filled in. In the site's
     * language when the setting is left empty.
     *
     * @param  array<string, string|null>  $replace
     */
    public static function text(string $key, array $replace = [], ?string $locale = null): string
    {
        $text = self::get($key) ?? ($key === 'consent_text' ? config('newsletter.consent_text') : null) ?? (string) __('newsletter::messages.defaults.'.$key, [], $locale);

        $replace += ['site' => Site::current()?->name() ?? config('app.name')];

        // Longest first, so :site_name wouldn't be read as :site.
        uksort($replace, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($replace as $name => $value) {
            $text = str_replace(':'.$name, (string) $value, $text);
        }

        return $text;
    }

    /**
     * The brand colour, as a CSS hex colour.
     */
    public static function colour(): string
    {
        $colour = self::get('accent_colour');

        return is_string($colour) && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $colour) ? $colour : '#1f2937';
    }

    /**
     * Dark or white text, whichever reads better on the brand colour.
     */
    public static function onColour(): string
    {
        $hex = ltrim(self::colour(), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        [$r, $g, $b] = array_map(function (string $pair) {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.0607) ? '#ffffff' : '#111827';
    }
}
