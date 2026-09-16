<?php

namespace Unipay\BD\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'unipay:install';

    protected $description = 'Install the UniPay BD package configuration and database migration';

    public function handle(): int
    {
        $this->info('Installing UniPay BD Package...');

        $this->info('Publishing configuration file...');
        $this->call('vendor:publish', [
            '--provider' => 'Unipay\BD\UnipayServiceProvider',
            '--tag' => 'unipay-config',
        ]);

        $this->info('Publishing database migrations...');
        $this->call('vendor:publish', [
            '--provider' => 'Unipay\BD\UnipayServiceProvider',
            '--tag' => 'unipay-migrations',
        ]);

        $this->info('UniPay BD installed successfully! Don\'t forget to run php artisan migrate.');

        return self::SUCCESS;
    }
}
