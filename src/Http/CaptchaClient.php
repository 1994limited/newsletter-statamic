<?php

namespace NineteenNinetyFour\Newsletter\Http;

use Illuminate\Support\Facades\Http;
use NineteenNinetyFour\Captcha\Http\Client;
use Throwable;

/**
 * The captcha check's one request, made with Laravel's HTTP client.
 */
class CaptchaClient implements Client
{
    public function postForm(string $url, array $form): ?array
    {
        try {
            $response = Http::asForm()->timeout(10)->post($url, $form);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }
}
