<?php

namespace Unipay\BD\Tests\Feature;

use Unipay\BD\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_artisan_unipay_install_command_executes()
    {
        $this->artisan('unipay:install')
            ->expectsOutput('Installing UniPay BD Package...')
            ->assertExitCode(0);
    }
}
