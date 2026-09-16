<?php

namespace Unipay\BD;

use Illuminate\Support\ServiceProvider;
use Unipay\BD\Console\Commands\InstallCommand;

class UnipayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/unipay.php', 'unipay');

        $this->app->singleton('unipay', function ($app) {
            return new PaymentManager($app);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/unipay.php' => config_path('unipay.php'),
            ], 'unipay-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'unipay-migrations');

            $this->commands([
                InstallCommand::class,
            ]);
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }
}
