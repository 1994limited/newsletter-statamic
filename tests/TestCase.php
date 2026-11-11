<?php

namespace NineteenNinetyFour\Newsletter\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use NineteenNinetyFour\Newsletter\Models\Subscriber;
use NineteenNinetyFour\Newsletter\Newsletter;
use NineteenNinetyFour\Newsletter\ServiceProvider;
use NineteenNinetyFour\Newsletter\SubscriberStatus;
use Statamic\Facades\Antlers;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\FakesRoles;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use FakesRoles, PreventsSavingStacheItemsToDisk, RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.editions.pro', true);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('view.paths', [__DIR__.'/__fixtures__/views']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Site::setSites([
            'default' => ['name' => 'Dog-ear Books', 'url' => 'http://localhost/', 'locale' => 'en_GB'],
        ])->save();
    }

    protected function tearDown(): void
    {
        // Addon settings saved by a test are written to the test app.
        (new Filesystem)->deleteDirectory(resource_path('addons'));

        parent::tearDown();
    }

    protected function newsletter(): Newsletter
    {
        return app(Newsletter::class);
    }

    /**
     * Someone who has signed up but not confirmed yet.
     */
    protected function pending(string $email = 'reader@example.com', ?string $name = null): Subscriber
    {
        RateLimiter::clear('newsletter:confirm:'.hash('sha256', $email));

        return $this->newsletter()->subscribe($email, $name, consent: 'Email me the newsletter.');
    }

    /**
     * Someone on the list.
     */
    protected function subscribed(string $email = 'reader@example.com', ?string $name = null): Subscriber
    {
        $subscriber = $this->pending($email, $name);
        $this->newsletter()->confirm($subscriber);

        return $subscriber->refresh();
    }

    protected function statusOf(Subscriber $subscriber): SubscriberStatus
    {
        return $subscriber->refresh()->status;
    }

    protected function user(array $permissions = [], bool $super = false): \Statamic\Contracts\Auth\User
    {
        if ($permissions) {
            $this->setTestRoles(['tester' => ['access cp', ...$permissions]]);
        }

        $user = User::make()->email('editor'.random_int(1000, 9999).'@example.com')->id('editor'.random_int(1000, 9999));

        if ($permissions) {
            $user->assignRole('tester');
        }

        if ($super) {
            $user->makeSuper();
        }

        $user->save();

        return $user;
    }

    /**
     * Render Antlers as a site template would (trusted, so tags run).
     */
    protected function antlers(string $template, array $data = []): string
    {
        return (string) Antlers::parse($template, $data, true);
    }
}
