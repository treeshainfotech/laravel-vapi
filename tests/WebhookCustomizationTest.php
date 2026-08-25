<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use TreeshaInfotech\Vapi\Events\StatusUpdateReceived;
use TreeshaInfotech\Vapi\Events\WebhookReceived;
use TreeshaInfotech\Vapi\Webhooks\WebhookProcessor;

it('registers a webhook route anywhere via the route macro', function (): void {
    config()->set('vapi.webhook.secret', 'macro-secret');
    Route::vapiWebhook('integrations/vapi');
    Route::getRoutes()->refreshNameLookups();
    Event::fake();

    expect(Route::has('vapi.webhook'))->toBeTrue();

    $this->postJson(
        '/integrations/vapi',
        ['message' => ['type' => 'status-update', 'status' => 'ended']],
        ['x-vapi-secret' => 'macro-secret'],
    )->assertOk()->assertJson(['ok' => true]);

    Event::assertDispatched(StatusUpdateReceived::class);
});

it('supports a custom route name in the macro', function (): void {
    Route::vapiWebhook('hooks/vapi', 'tenant.vapi.webhook');
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('tenant.vapi.webhook'))->toBeTrue()
        ->and(Route::has('vapi.webhook'))->toBeFalse();
});

it('lets a custom controller reuse the webhook pipeline', function (): void {
    Event::fake();

    $request = Request::create(
        '/anything',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode(['message' => ['type' => 'status-update', 'status' => 'ended']], JSON_THROW_ON_ERROR),
    );

    $response = app(WebhookProcessor::class)->handle($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200);

    Event::assertDispatched(
        WebhookReceived::class,
        fn (WebhookReceived $event): bool => $event->type === 'status-update',
    );
    Event::assertDispatched(StatusUpdateReceived::class);
});
