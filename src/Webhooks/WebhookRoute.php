<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Webhooks;

use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use TreeshaInfotech\Vapi\Http\Controllers\VapiWebhookController;
use TreeshaInfotech\Vapi\Http\Middleware\VerifyVapiWebhook;

/**
 * Registers the verified Vapi webhook route. Used by the package's own
 * route file and the Route::vapiWebhook() macro; call it directly from
 * your route files for a static-analysis-friendly alternative to the
 * macro. Composes with route groups (prefix, domain, middleware).
 */
final class WebhookRoute
{
    public static function register(?string $path = null, string $name = 'vapi.webhook'): RegisteredRoute
    {
        if ($path === null) {
            $configured = config('vapi.webhook.path');
            $path = is_string($configured) && $configured !== '' ? $configured : 'webhooks/vapi';
        }

        return Route::post($path, VapiWebhookController::class)
            ->middleware(VerifyVapiWebhook::class)
            ->name($name);
    }
}
