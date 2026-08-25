<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use TreeshaInfotech\Vapi\Contracts\RespondsToVapiWebhooks;
use TreeshaInfotech\Vapi\Events\StatusUpdateReceived;
use TreeshaInfotech\Vapi\Events\WebhookReceived;
use Vapi\Types\ServerMessageResponse;
use Vapi\Types\ServerMessageResponseAssistantRequest;
use Vapi\Types\ServerMessageStatusUpdate;

it('registers the webhook route when enabled', function (): void {
    expect(Route::has('vapi.webhook'))->toBeTrue();
});

it('rejects requests without the secret header', function (): void {
    $this->postJson(route('vapi.webhook'), ['message' => ['type' => 'status-update']])
        ->assertStatus(401);
});

it('rejects requests with a wrong secret', function (): void {
    $this->postJson(
        route('vapi.webhook'),
        ['message' => ['type' => 'status-update']],
        ['x-vapi-secret' => 'wrong-secret'],
    )->assertStatus(401);
});

it('fails closed when no secret is configured', function (): void {
    config()->set('vapi.webhook.secret', null);

    $this->postJson(
        route('vapi.webhook'),
        ['message' => ['type' => 'status-update']],
        ['x-vapi-secret' => 'webhook-secret'],
    )->assertStatus(500);
});

it('accepts the secret as a bearer token', function (): void {
    Event::fake();

    $this->postJson(
        route('vapi.webhook'),
        ['message' => ['type' => 'status-update', 'status' => 'ended']],
        ['Authorization' => 'Bearer webhook-secret'],
    )->assertOk();

    Event::assertDispatched(WebhookReceived::class);
});

it('accepts an hmac signature when configured', function (): void {
    config()->set('vapi.webhook.secret', null);
    config()->set('vapi.webhook.hmac.secret', 'hmac-secret');
    Event::fake();

    $json = json_encode(['message' => ['type' => 'status-update', 'status' => 'ended']], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('vapi.webhook'),
        server: $this->transformHeadersToServerVars([
            'x-vapi-signature' => 'sha256='.hash_hmac('sha256', $json, 'hmac-secret'),
            'Content-Type' => 'application/json',
        ]),
        content: $json,
    )->assertOk();

    Event::assertDispatched(WebhookReceived::class);
});

it('rejects an invalid hmac signature', function (): void {
    config()->set('vapi.webhook.secret', null);
    config()->set('vapi.webhook.hmac.secret', 'hmac-secret');

    $this->postJson(
        route('vapi.webhook'),
        ['message' => ['type' => 'status-update']],
        ['x-vapi-signature' => 'sha256=not-a-real-signature'],
    )->assertStatus(401);
});

it('dispatches generic and typed events for a verified payload', function (): void {
    Event::fake();

    $this->postJson(
        route('vapi.webhook'),
        ['message' => ['type' => 'status-update', 'status' => 'ended']],
        ['x-vapi-secret' => 'webhook-secret'],
    )->assertOk()->assertJson(['ok' => true]);

    Event::assertDispatched(
        WebhookReceived::class,
        fn (WebhookReceived $event): bool => $event->type === 'status-update'
            && $event->message instanceof ServerMessageStatusUpdate,
    );
    Event::assertDispatched(
        StatusUpdateReceived::class,
        fn (StatusUpdateReceived $event): bool => $event->statusUpdate->status === 'ended',
    );
});

it('dispatches only the generic event for unknown message types', function (): void {
    Event::fake();

    $this->postJson(
        route('vapi.webhook'),
        ['message' => ['type' => 'some-future-type', 'foo' => 'bar']],
        ['x-vapi-secret' => 'webhook-secret'],
    )->assertOk();

    Event::assertDispatched(
        WebhookReceived::class,
        fn (WebhookReceived $event): bool => $event->type === 'some-future-type'
            && $event->message === null,
    );
    Event::assertNotDispatched(StatusUpdateReceived::class);
});

it('rejects a payload that is not valid json', function (): void {
    $this->call(
        'POST',
        route('vapi.webhook'),
        server: $this->transformHeadersToServerVars([
            'x-vapi-secret' => 'webhook-secret',
            'Content-Type' => 'application/json',
        ]),
        content: 'not-json',
    )->assertStatus(422);
});

it('rejects a payload without a message object', function (): void {
    $this->postJson(
        route('vapi.webhook'),
        ['not-message' => true],
        ['x-vapi-secret' => 'webhook-secret'],
    )->assertStatus(422);
});

it('rejects a message without a type', function (): void {
    $this->postJson(
        route('vapi.webhook'),
        ['message' => ['status' => 'ended']],
        ['x-vapi-secret' => 'webhook-secret'],
    )->assertStatus(422);
});

it('returns a responder-provided response body', function (): void {
    app()->instance(RespondsToVapiWebhooks::class, new class implements RespondsToVapiWebhooks
    {
        public function respond(WebhookReceived $webhook): ?ServerMessageResponse
        {
            return new ServerMessageResponse([
                'messageResponse' => new ServerMessageResponseAssistantRequest([
                    'assistantId' => 'asst_123',
                ]),
            ]);
        }
    });

    $this->postJson(
        route('vapi.webhook'),
        ['message' => ['type' => 'assistant-request']],
        ['x-vapi-secret' => 'webhook-secret'],
    )->assertOk()->assertJson(['messageResponse' => ['assistantId' => 'asst_123']]);
});
