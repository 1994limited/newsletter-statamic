<?php

namespace NineteenNinetyFour\Newsletter\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use NineteenNinetyFour\Newsletter\Events\Confirmed;
use NineteenNinetyFour\Newsletter\Events\Subscribing;
use NineteenNinetyFour\Newsletter\Mail\ConfirmMail;
use NineteenNinetyFour\Newsletter\Mail\NewSubscriberMail;
use NineteenNinetyFour\Newsletter\Models\Subscriber;
use NineteenNinetyFour\Newsletter\SubscriberStatus;
use NineteenNinetyFour\Newsletter\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Addon;

class SubscribeTest extends TestCase
{
    #[Test]
    public function signing_up_waits_for_the_link_in_the_email(): void
    {
        Mail::fake();

        $this->from('/newsletter')
            ->post('/!/newsletter/subscribe', ['email' => ' Reader@Example.com ', 'consent' => '1'])
            ->assertRedirect('/newsletter')
            ->assertSessionHas('newsletter_success', 'Thanks! Check your inbox for an email to confirm your subscription.');

        $subscriber = Subscriber::sole();
        $this->assertSame('reader@example.com', $subscriber->email);
        $this->assertSame(SubscriberStatus::Pending, $subscriber->status);
        $this->assertSame('default', $subscriber->site);
        $this->assertSame('Email me the newsletter. I can unsubscribe at any time.', $subscriber->consent);
        $this->assertSame('/newsletter', $subscriber->source_url);
        $this->assertNotNull($subscriber->confirmation_sent_at);
        $this->assertSame(0, Subscriber::query()->subscribed()->count());

        Mail::assertSent(ConfirmMail::class, fn (ConfirmMail $mail) => $mail->hasTo('reader@example.com'));
    }

    #[Test]
    public function the_email_has_the_link(): void
    {
        $subscriber = $this->pending();

        $html = (new ConfirmMail($subscriber))->render();

        $this->assertStringContainsString('Please confirm you’d like the Dog-ear Books newsletter at reader@example.com.', $html);
        $this->assertStringContainsString('Yes, subscribe me', $html);
        $this->assertStringContainsString('/!/newsletter/confirm/'.$subscriber->id, $html);
        $this->assertSame('Please confirm your subscription', (new ConfirmMail($subscriber))->envelope()->subject);
    }

    #[Test]
    public function the_link_opens_a_page_whose_button_confirms(): void
    {
        $subscriber = $this->pending();

        // Opening it (as a mail app's link checker does) changes nothing.
        $this->get($subscriber->confirmUrl())
            ->assertOk()
            ->assertSee('Send the newsletter to reader@example.com?')
            ->assertSee('Yes, subscribe me');
        $this->assertSame(SubscriberStatus::Pending, $this->statusOf($subscriber));

        // The button does, without a CSRF token: the signature stands in for it.
        $this->withMiddleware()->post($subscriber->confirmUrl())->assertOk()->assertSee('You’re subscribed');

        $this->assertSame(SubscriberStatus::Subscribed, $this->statusOf($subscriber));
        $this->assertNotNull($subscriber->confirmed_at);
        $this->get($subscriber->confirmUrl())->assertSee('That’s already confirmed');
    }

    #[Test]
    public function a_changed_or_expired_link_does_not_confirm(): void
    {
        $subscriber = $this->pending();

        $this->post($subscriber->confirmUrl().'x')->assertForbidden();
        $this->post('/!/newsletter/confirm/'.$subscriber->id)->assertForbidden();

        $url = $subscriber->confirmUrl();
        $this->travel(73)->hours();
        $this->get($url)->assertForbidden()->assertSee('This link has expired');
        $this->post($url)->assertForbidden();

        $this->assertSame(SubscriberStatus::Pending, $this->statusOf($subscriber));
    }

    #[Test]
    public function confirming_can_land_on_a_page_of_the_site(): void
    {
        config(['newsletter.pages.confirmed' => '/newsletter/thanks']);
        $subscriber = $this->pending();

        $this->post($subscriber->confirmUrl())
            ->assertRedirect('/newsletter/thanks')
            ->assertSessionHas('newsletter_success', 'Thanks for confirming. reader@example.com is on the list.');
    }

    #[Test]
    public function signing_up_again_once_subscribed_sends_nothing_and_says_the_same(): void
    {
        $this->subscribed();
        Mail::fake();

        $this->postJson('/!/newsletter/subscribe', ['email' => 'READER@example.com', 'consent' => '1'])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Thanks! Check your inbox for an email to confirm your subscription.']);

        Mail::assertNothingSent();
        $this->assertSame(1, Subscriber::count());
        $this->assertSame(SubscriberStatus::Subscribed, Subscriber::sole()->status);
    }

    #[Test]
    public function the_form_s_mistakes_are_handed_back(): void
    {
        Mail::fake();

        $this->postJson('/!/newsletter/subscribe', ['email' => 'not an email', 'consent' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email', 'Please enter a valid email address.');

        $this->postJson('/!/newsletter/subscribe', ['email' => 'reader@example.com'])
            ->assertStatus(422)
            ->assertJsonPath('errors.consent', 'Please tick the box to say you’d like the newsletter.');

        $this->postJson('/!/newsletter/subscribe', ['email' => ['reader@example.com'], 'consent' => '1'])->assertStatus(422);

        // Without JavaScript: back, with the errors and what was typed.
        $this->from('/newsletter')
            ->post('/!/newsletter/subscribe', ['email' => 'nope'])
            ->assertRedirect('/newsletter')
            ->assertSessionHasErrors(['email', 'consent'], errorBag: 'newsletter')
            ->assertSessionHasInput('email', 'nope');

        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function no_tick_box_when_consent_is_turned_off(): void
    {
        config(['newsletter.consent' => false]);

        $this->postJson('/!/newsletter/subscribe', ['email' => 'reader@example.com'])->assertOk();

        $this->assertNull(Subscriber::sole()->consent);
    }

    #[Test]
    public function a_name_can_be_asked_for_and_required(): void
    {
        $this->postJson('/!/newsletter/subscribe', ['email' => 'reader@example.com', 'name' => 'Ada', 'consent' => '1'])->assertOk();
        $this->assertNull(Subscriber::sole()->name, 'Not kept unless asked for.');

        config(['newsletter.ask_name' => true]);
        $this->postJson('/!/newsletter/subscribe', ['email' => 'other@example.com', 'name' => '  Ada Reader ', 'consent' => '1'])->assertOk();
        $this->assertSame('Ada Reader', Subscriber::where('email', 'other@example.com')->sole()->name);

        config(['newsletter.require_name' => true]);
        $this->postJson('/!/newsletter/subscribe', ['email' => 'third@example.com', 'consent' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('errors.name', 'Please enter your name.');
    }

    #[Test]
    public function bots_that_fill_in_the_hidden_field_are_told_it_worked(): void
    {
        Mail::fake();

        $this->postJson('/!/newsletter/subscribe', ['email' => 'bot@example.com', 'consent' => '1', 'website' => 'https://spam.example'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function one_visitor_can_only_sign_up_so_often(): void
    {
        config(['newsletter.throttle' => 2]);

        $this->postJson('/!/newsletter/subscribe', ['email' => 'one@example.com', 'consent' => '1'])->assertOk();
        $this->postJson('/!/newsletter/subscribe', ['email' => 'two@example.com', 'consent' => '1'])->assertOk();
        $this->postJson('/!/newsletter/subscribe', ['email' => 'three@example.com', 'consent' => '1'])->assertStatus(429);

        $this->assertSame(2, Subscriber::count());
    }

    #[Test]
    public function one_address_gets_only_so_many_confirmation_emails(): void
    {
        Mail::fake();

        foreach (range(1, 5) as $i) {
            $this->postJson('/!/newsletter/subscribe', ['email' => 'reader@example.com', 'consent' => '1'])->assertOk();
        }

        Mail::assertSentCount(3);
    }

    #[Test]
    public function unsubscribing_from_the_link(): void
    {
        $subscriber = $this->subscribed();

        $this->get($subscriber->unsubscribeUrl())
            ->assertOk()
            ->assertSee('Stop sending the newsletter to reader@example.com?');
        $this->assertSame(SubscriberStatus::Subscribed, $this->statusOf($subscriber));

        $this->withMiddleware()->post($subscriber->unsubscribeUrl())->assertOk()->assertSee('You’re unsubscribed');

        $this->assertSame(SubscriberStatus::Unsubscribed, $this->statusOf($subscriber));
        $this->assertNotNull($subscriber->unsubscribed_at);
        $this->assertSame(0, Subscriber::query()->subscribed()->count());

        $this->get('/!/newsletter/unsubscribe/'.$subscriber->id)->assertForbidden();
    }

    #[Test]
    public function mail_apps_one_click_unsubscribe(): void
    {
        $subscriber = $this->subscribed();

        $this->withMiddleware()->post($subscriber->unsubscribeUrl(), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk()
            ->assertContent('');

        $this->assertSame(SubscriberStatus::Unsubscribed, $this->statusOf($subscriber));
    }

    #[Test]
    public function signing_up_again_after_unsubscribing_needs_confirming_again(): void
    {
        $subscriber = $this->subscribed();
        $this->newsletter()->unsubscribe($subscriber);
        Mail::fake();

        $this->postJson('/!/newsletter/subscribe', ['email' => 'reader@example.com', 'consent' => '1'])->assertOk();

        $this->assertSame(SubscriberStatus::Pending, $this->statusOf($subscriber));
        Mail::assertSent(ConfirmMail::class);

        // An unsubscribe link from before doesn't stop them confirming…
        $this->newsletter()->confirm($subscriber->refresh());
        $this->assertNull($subscriber->refresh()->unsubscribed_at);
        $this->assertSame(1, Subscriber::query()->subscribed()->count());
    }

    #[Test]
    public function the_team_can_be_told_about_new_subscribers(): void
    {
        config(['newsletter.email.notify' => ['team@dogear.test']]);
        Mail::fake();

        $this->subscribed('reader@example.com', 'Ada Reader');

        Mail::assertSent(NewSubscriberMail::class, fn (NewSubscriberMail $mail) => $mail->hasTo('team@dogear.test'));
        $this->assertStringContainsString('reader@example.com confirmed their newsletter sign-up.', (new NewSubscriberMail(Subscriber::sole()))->render());
    }

    #[Test]
    public function a_sign_up_can_be_turned_away_by_a_listener(): void
    {
        Mail::fake();
        Event::listen(Subscribing::class, fn (Subscribing $event) => str_ends_with($event->subscriber->email, '@blocked.example') ? false : null);

        $this->postJson('/!/newsletter/subscribe', ['email' => 'someone@blocked.example', 'consent' => '1'])->assertOk();

        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function confirming_is_an_event(): void
    {
        Event::fake([Confirmed::class]);

        $this->subscribed();

        Event::assertDispatched(Confirmed::class, fn (Confirmed $event) => $event->subscriber->email === 'reader@example.com');
    }

    #[Test]
    public function old_unconfirmed_sign_ups_are_deleted(): void
    {
        $old = $this->pending('old@example.com');
        $this->pending('recent@example.com');
        $kept = $this->subscribed('kept@example.com');

        Subscriber::whereIn('id', [$old->id, $kept->id])->update(['created_at' => now()->subDays(40)]);

        $this->artisan('newsletter:prune')->expectsOutput('1 unconfirmed sign-up deleted.')->assertSuccessful();

        $this->assertSame(['kept@example.com', 'recent@example.com'], Subscriber::orderBy('email')->pluck('email')->all());

        config(['newsletter.prune_days' => null]);
        $this->assertSame(0, $this->newsletter()->prune());
    }

    #[Test]
    public function settings_saved_in_the_control_panel_beat_the_config(): void
    {
        Addon::get('1994/newsletter-statamic')->settings()->set('consent', false)->set('consent_text', 'Yes please.')->save();

        $this->postJson('/!/newsletter/subscribe', ['email' => 'reader@example.com'])->assertOk();
        $this->assertNull(Subscriber::sole()->consent);

        Addon::get('1994/newsletter-statamic')->settings()->set('consent', true)->save();
        $this->postJson('/!/newsletter/subscribe', ['email' => 'other@example.com', 'consent' => '1'])->assertOk();
        $this->assertSame('Yes please.', Subscriber::where('email', 'other@example.com')->sole()->consent);
    }
}
