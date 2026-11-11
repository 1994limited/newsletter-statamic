<?php

namespace NineteenNinetyFour\Newsletter;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\Newsletter\Commands\Prune;
use NineteenNinetyFour\Newsletter\Tags\NewsletterTags;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\YAML;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    /** The Control Panel nav icon (resources/brand/nav-icon.svg). */
    public const ICON = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path d="M1.75 3.25H12.25V10.75H1.75Z" stroke="currentColor" stroke-width="1" stroke-linejoin="round"></path><path d="M2.25 3.75L7 7.5L11.75 3.75" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

    protected $tags = [
        NewsletterTags::class,
    ];

    protected $commands = [
        Prune::class,
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    /**
     * The package is 1994/newsletter-statamic; its config, views and
     * translations are read as "newsletter", so they're loaded by hand.
     * Routes too: actions live at /!/newsletter.
     */
    protected $config = false;

    protected $translations = false;

    protected $routes = [
        'web' => null,
        'cp' => __DIR__.'/../routes/cp.php',
        'actions' => null,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/newsletter.php', 'newsletter');

        $this->app->singleton(Newsletter::class);
    }

    public function bootAddon(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'newsletter');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'newsletter');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([__DIR__.'/../config/newsletter.php' => config_path('newsletter.php')], 'newsletter-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/newsletter')], 'newsletter-views');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/newsletter')], 'newsletter-translations');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'newsletter-migrations');

        $this->registerFrontendRoutes();
        $this->registerSchedule();
        $this->registerSettings();

        Permission::extend(function () {
            Permission::group('newsletter', __('newsletter::messages.permissions.group'), function () {
                Permission::register('view newsletter', function ($permission) {
                    $permission->label(__('newsletter::messages.permissions.view'))->children([
                        Permission::make('export newsletter')->label(__('newsletter::messages.permissions.export')),
                        Permission::make('manage newsletter')->label(__('newsletter::messages.permissions.manage')),
                    ]);
                });
            });
        });

        Nav::extend(function ($nav) {
            $nav->create(__('newsletter::messages.cp.title'))
                ->section('Tools')
                ->route('newsletter.index')
                ->icon(self::ICON)
                ->can('view newsletter');
        });
    }

    /**
     * Tools → Addons → Newsletter → Settings (resources/settings.yaml). The
     * toggles start as config/newsletter.php has them, so they show what's
     * really happening, and saving the page doesn't change it.
     */
    protected function registerSettings(): void
    {
        $this->registerSettingsBlueprint(function () {
            $blueprint = YAML::file(__DIR__.'/../resources/settings.yaml')->parse();

            foreach ($blueprint['tabs']['form']['sections'][0]['fields'] as &$field) {
                if (in_array($field['handle'], ['consent', 'ask_name', 'require_name'], true)) {
                    $field['field']['default'] = (bool) config('newsletter.'.$field['handle'], $field['handle'] === 'consent');
                }
            }

            return $blueprint;
        });
    }

    protected function registerFrontendRoutes(): void
    {
        RateLimiter::for('newsletter', fn (Request $request) => Limit::perMinute(max(1, (int) config('newsletter.throttle', 10)))->by($request->ip()));

        // The links in the emails post without a CSRF token (mail apps'
        // one-click unsubscribe can't send one); their signature is checked
        // instead. VerifyCsrfToken's list is shared by Laravel 12's
        // ValidateCsrfToken and Laravel 13's PreventRequestForgery, and by
        // a site's own subclass.
        $actions = trim((string) config('statamic.routes.action', '!'), '/');
        VerifyCsrfToken::except([
            $actions.'/newsletter/confirm/*',
            $actions.'/newsletter/unsubscribe/*',
        ]);

        Statamic::pushActionRoutes(function () {
            Route::prefix('newsletter')->name('newsletter.')->group(__DIR__.'/../routes/actions.php');
        });
    }

    protected function registerSchedule(): void
    {
        if (! config('newsletter.schedule', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('newsletter:prune')->daily()->withoutOverlapping();
        });
    }
}
