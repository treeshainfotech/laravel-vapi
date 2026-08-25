<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TreeshaInfotech\Vapi\VapiServiceProvider;

it('does not register the webhook route by default', function (): void {
    expect(Route::has('vapi.webhook'))->toBeFalse();
});

it('merges the default configuration', function (): void {
    expect(config('vapi.base_url'))->toBe('https://api.vapi.ai')
        ->and(config('vapi.max_retries'))->toBe(2)
        ->and(config('vapi.webhook.enabled'))->toBeFalse()
        ->and(config('vapi.webhook.path'))->toBe('webhooks/vapi');
});

it('publishes the configuration file', function (): void {
    expect(VapiServiceProvider::pathsToPublish(
        VapiServiceProvider::class,
        'vapi-config',
    ))->not->toBeEmpty();
});
