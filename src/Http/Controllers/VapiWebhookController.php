<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Http\Controllers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TreeshaInfotech\Vapi\Webhooks\WebhookProcessor;

final readonly class VapiWebhookController
{
    public function __construct(private WebhookProcessor $processor) {}

    /**
     * @throws BindingResolutionException When a bound responder cannot be resolved.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return $this->processor->handle($request);
    }
}
