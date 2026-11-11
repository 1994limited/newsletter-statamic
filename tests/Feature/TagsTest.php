<?php

namespace NineteenNinetyFour\Newsletter\Tests\Feature;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use NineteenNinetyFour\Newsletter\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TagsTest extends TestCase
{
    #[Test]
    public function the_ready_made_form(): void
    {
        $html = $this->antlers('{{ newsletter button="Sign me up" redirect="/thanks" }}');

        $this->assertStringContainsString('action="http://localhost/!/newsletter/subscribe"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('name="consent"', $html);
        $this->assertStringContainsString('Email me the newsletter. I can unsubscribe at any time.', $html);
        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('name="_redirect" value="/thanks"', $html);
        $this->assertStringContainsString('Sign me up', $html);
        $this->assertStringNotContainsString('name="name"', $html);

        config(['newsletter.ask_name' => true, 'newsletter.consent' => false]);
        $html = $this->antlers('{{ newsletter:form }}');
        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringNotContainsString('name="consent"', $html);
    }

    #[Test]
    public function your_own_fields(): void
    {
        $html = $this->antlers('{{ newsletter }}<input name="email" value="{{ email }}"><label><input type="checkbox" name="consent"> {{ consent_text }}</label>{{ /newsletter }}');

        $this->assertStringContainsString('<input name="email" value="">', $html);
        $this->assertStringContainsString('Email me the newsletter.', $html);
        $this->assertStringContainsString('name="website"', $html);
    }

    #[Test]
    public function mistakes_are_shown_and_what_was_typed_kept_escaped(): void
    {
        session()->flash('errors', (new ViewErrorBag)->put('newsletter', new MessageBag(['email' => 'Please enter a valid email address.'])));
        session()->flash('_old_input', ['email' => '"><script>alert(1)</script>']);

        $html = $this->antlers('{{ newsletter }}');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Please enter a valid email address.', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);

        $this->assertStringContainsString('Please enter a valid email address.', $this->antlers('{{ newsletter:errors }}{{ value }}{{ /newsletter:errors }}'));
        $this->assertStringContainsString('Please enter a valid email address.', $this->antlers('{{ newsletter }}{{ error:email }}{{ /newsletter }}'));
    }

    #[Test]
    public function the_success_message(): void
    {
        session()->flash('newsletter_success', 'Thanks!');

        $this->assertStringContainsString('Thanks!', $this->antlers('{{ newsletter }}'));
        $this->assertSame('Thanks!', $this->antlers('{{ newsletter:success }}'));
    }

    #[Test]
    public function the_count(): void
    {
        $this->subscribed('ada@example.com');
        $this->pending('waiting@example.com');

        $this->assertSame('1', $this->antlers('{{ newsletter:count }}'));
    }
}
