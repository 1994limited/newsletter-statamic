<?php

namespace NineteenNinetyFour\Newsletter;

use InvalidArgumentException;
use NineteenNinetyFour\Captcha\Captcha;
use NineteenNinetyFour\Captcha\Captchas;
use NineteenNinetyFour\Newsletter\Http\CaptchaClient;

/**
 * The captcha on the sign-up form, from config('newsletter.captcha'): one
 * of Turnstile, hCaptcha or reCAPTCHA (v2 or v3), or a class of your own
 * implementing NineteenNinetyFour\Captcha\Captcha. None until a driver and
 * both keys are set.
 */
class FormCaptcha
{
    public const ACTION = 'newsletter';

    public static function make(): ?Captcha
    {
        $driver = config('newsletter.captcha.driver');

        if (! is_string($driver) || trim($driver) === '') {
            return null;
        }

        $driver = trim($driver);

        if (Captchas::builtIn($driver)) {
            return Captchas::make(
                $driver,
                self::string('site_key'),
                self::string('secret_key'),
                app(CaptchaClient::class),
                ['action' => self::ACTION, 'score' => config('newsletter.captcha.score', 0.5)],
            );
        }

        if (class_exists($driver) && is_subclass_of($driver, Captcha::class)) {
            return app($driver);
        }

        throw new InvalidArgumentException("Unknown newsletter captcha [{$driver}]. Use turnstile, hcaptcha, recaptcha, recaptcha_v3, or a class implementing ".Captcha::class.'.');
    }

    protected static function string(string $key): ?string
    {
        $value = config("newsletter.captcha.{$key}");

        return is_string($value) ? $value : null;
    }
}
