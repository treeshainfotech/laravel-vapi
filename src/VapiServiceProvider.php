<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface;
use TreeshaInfotech\Vapi\Exceptions\MissingApiKey;
use TreeshaInfotech\Vapi\Webhooks\WebhookRoute;
use Vapi\Core\Client\RawClient;
use Vapi\VapiClient;

final class VapiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/vapi.php', 'vapi');

        $this->app->singleton(VapiManager::class, fn (): VapiManager => new VapiManager(
            $this->createClient(),
            $this->createRawClient(),
        ));
        $this->app->alias(VapiManager::class, 'vapi');
    }

    public function boot(): void
    {
        Route::macro('vapiWebhook', function (?string $path = null, string $name = 'vapi.webhook'): RegisteredRoute {
            return WebhookRoute::register($path, $name);
        });

        if (config('vapi.webhook.enabled') === true) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/vapi.php' => config_path('vapi.php'),
            ], 'vapi-config');
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function createClient(): VapiClient
    {
        return new VapiClient(token: $this->apiKey(), options: $this->clientOptions());
    }

    /**
     * @throws BindingResolutionException
     */
    private function createRawClient(): RawVapiClient
    {
        $options = $this->clientOptions();
        $baseUrl = $options['baseUrl'] ?? 'https://api.vapi.ai';
        unset($options['baseUrl']);

        $options['headers'] = array_merge(
            ['Authorization' => 'Bearer '.$this->apiKey()],
            $options['headers'] ?? [],
        );

        return new RawVapiClient(new RawClient($options), $baseUrl);
    }

    private function apiKey(): string
    {
        $apiKey = config('vapi.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw MissingApiKey::create();
        }

        return $apiKey;
    }

    /**
     * @return array{
     *   baseUrl?: string,
     *   client?: ClientInterface,
     *   maxRetries?: int,
     *   timeout?: float,
     *   headers?: array<string, string>,
     * }
     *
     * @throws BindingResolutionException
     */
    private function clientOptions(): array
    {
        $options = [];

        $baseUrl = config('vapi.base_url');
        if (is_string($baseUrl) && $baseUrl !== '') {
            $options['baseUrl'] = $baseUrl;
        }

        if ($this->app->bound(ClientInterface::class)) {
            $options['client'] = $this->app->make(ClientInterface::class);
        }

        $maxRetries = config('vapi.max_retries');
        if (is_numeric($maxRetries)) {
            $options['maxRetries'] = (int) $maxRetries;
        }

        $timeout = config('vapi.timeout');
        if (is_numeric($timeout)) {
            $options['timeout'] = (float) $timeout;
        }

        $headers = [];
        $configuredHeaders = config('vapi.headers');
        if (is_array($configuredHeaders)) {
            foreach ($configuredHeaders as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $headers[$name] = $value;
                }
            }
        }
        if ($headers !== []) {
            $options['headers'] = $headers;
        }

        return $options;
    }
}
