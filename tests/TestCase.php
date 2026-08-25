<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use TreeshaInfotech\Vapi\VapiServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            VapiServiceProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('vapi.api_key', 'test-key');
    }
}
