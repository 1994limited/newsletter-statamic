<?php

namespace NineteenNinetyFour\Newsletter\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use NineteenNinetyFour\Captcha\Captcha;
use NineteenNinetyFour\Newsletter\Models\Subscriber;
use NineteenNinetyFour\Newsletter\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['newsletter.captcha' => ['driver' => 'turnstile', 'site_key' => 'site-key', 'secret_key' => 'secret-key', 'score' => 0.5]]);
    }

    #[Test]
    public function the_form_has_the_widget(): void
    {
        $html = $this->antlers('{{ newsletter }}');

        $this->assertStringContainsString('class="cf-turnstile" data-captcha data-sitekey="site-key"', $html);
        $this->assertStringContainsString('challenges.cloudflare.com/turnstile/v0/api.js', $html);
        // Before the button, not after it.
        $this->assertLessThan(strpos($html, '<button'), strpos($html, 'cf-turnstile'));
    }

    #[Test]
    public function your_own_fields_place_it_or_get_it_at_the_end(): void
    {
        $placed = $this->antlers('{{ newsletter }}<input name="email">{{ captcha }}<button>Go</button>{{ /newsletter }}');
        $this->assertSame(1, substr_count($placed, 'class="cf-turnstile"'));
        $this->assertLessThan(strpos($placed, '<button'), strpos($placed, 'cf-turnstile'));

        $left_out = $this->antlers('{{ newsletter }}<input name="email"><button>Go</button>{{ /newsletter }}');
        $this->assertSame(1, substr_count($left_out, 'class="cf-turnstile"'));
    }

    #[Test]
    public function without_keys_there_is_no_captcha(): void
    {
        config(['newsletter.captcha.secret_key' => null]);
        Http::fake();

        $this->assertStringNotContainsString('data-captcha', $this->antlers('{{ newsletter }}'));
        $this->post('/!/newsletter/subscribe', ['email' => 'reader@example.com', 'consent' => '1'])->assertRedirect();

        $this->assertSame(1, Subscriber::count());
        Http::assertNothingSent();
    }

    #[Test]
    public function a_passed_check_signs_them_up(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post('/!/newsletter/subscribe', ['email' => 'reader@example.com', 'consent' => '1', 'cf-turnstile-response' => 'token'], ['REMOTE_ADDR' => '203.0.113.9'])
            ->assertSessionHas('newsletter_success');

        $this->assertSame(1, Subscriber::count());
        Http::assertSent(fn (Request $request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'secret-key' && $request['response'] === 'token' && $request['remoteip'] === '203.0.113.9');
    }

    #[Test]
    public function a_failed_missing_or_unreachable_check_does_not(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => false])
            ->push('Bad gateway', 502)]);

        foreach (['token', 'token', null] as $token) {
            $this->postJson('/!/newsletter/subscribe', array_filter(['email' => 'reader@example.com', 'consent' => '1', 'cf-turnstile-response' => $token]))
                ->assertStatus(422)
                ->assertJsonPath('errors.captcha', 'We couldn’t check you’re not a robot. Please try again.');
        }

        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingSent();
        // The one without a token never reached the provider.
        Http::assertSentCount(2);
    }

    #[Test]
    public function a_form_with_mistakes_is_not_checked(): void
    {
        Http::fake();

        $this->postJson('/!/newsletter/subscribe', ['email' => 'not an email', 'cf-turnstile-response' => 'token'])
            ->assertStatus(422)
            ->assertJsonMissingPath('errors.captcha');

        Http::assertNothingSent();
    }

    #[Test]
    public function the_error_is_shown_by_the_widget(): void
    {
        session()->flash('errors', (new ViewErrorBag)->put('newsletter', new MessageBag(['captcha' => 'We couldn’t check you’re not a robot. Please try again.'])));

        $this->assertStringContainsString('We couldn’t check you’re not a robot.', $this->antlers('{{ newsletter }}'));
    }

    #[Test]
    public function recaptcha_v3_tokens_must_be_for_this_form(): void
    {
        config(['newsletter.captcha.driver' => 'recaptcha_v3']);
        Http::fake(['www.google.com/*' => Http::sequence()
            ->push(['success' => true, 'score' => 0.9, 'action' => 'login'])
            ->push(['success' => true, 'score' => 0.9, 'action' => 'newsletter'])]);

        $this->assertStringContainsString('{action:"newsletter"}', $this->antlers('{{ newsletter }}'));

        $this->postJson('/!/newsletter/subscribe', ['email' => 'reader@example.com', 'consent' => '1', 'g-recaptcha-response' => 'a'])->assertStatus(422);
        $this->postJson('/!/newsletter/subscribe', ['email' => 'reader@example.com', 'consent' => '1', 'g-recaptcha-response' => 'b'])->assertOk();

        $this->assertSame(1, Subscriber::count());
    }

    #[Test]
    public function a_captcha_of_your_own(): void
    {
        config(['newsletter.captcha.driver' => OwnCaptcha::class]);

        $this->assertStringContainsString('<span data-captcha>own</span>', $this->antlers('{{ newsletter }}'));
        $this->postJson('/!/newsletter/subscribe', ['email' => 'reader@example.com', 'consent' => '1', 'own' => 'no'])->assertStatus(422);
        $this->postJson('/!/newsletter/subscribe', ['email' => 'reader@example.com', 'consent' => '1', 'own' => 'yes'])->assertOk();

        $this->assertSame(1, Subscriber::count());
    }
}

class OwnCaptcha implements Captcha
{
    public function widget(): string
    {
        return '<span data-captcha>own</span>';
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        return $token === 'yes';
    }

    public function field(): string
    {
        return 'own';
    }
}
