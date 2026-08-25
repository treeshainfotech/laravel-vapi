<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\AssertionFailedError;
use Psr\Http\Message\RequestInterface;
use TreeshaInfotech\Vapi\Facades\Vapi;
use TreeshaInfotech\Vapi\VapiManager;

it('routes facade calls through the fake transport', function (): void {
    $fake = Vapi::fake(new Response(200, [], '[]'));

    $result = Vapi::calls()->list();

    expect($result)->toBe([]);
    $fake->assertSentCount(1);
    $fake->assertSent(
        fn (RequestInterface $request): bool => $request->getMethod() === 'GET'
            && str_starts_with((string) $request->getUri(), 'https://api.vapi.ai/call'),
    );
});

it('answers with an empty response when the queue runs dry', function (): void {
    $fake = Vapi::fake();

    expect(Vapi::calls()->list())->toBeNull();
    $fake->assertSentCount(1);
});

it('consumes queued responses in order', function (): void {
    $fake = Vapi::fake(
        new Response(200, [], '[]'),
        new Response(200, [], ''),
    );

    expect(Vapi::calls()->list())->toBe([])
        ->and(Vapi::calls()->list())->toBeNull();
    $fake->assertSentCount(2);
});

it('sends the configured api key', function (): void {
    $fake = Vapi::fake();

    Vapi::calls()->list();

    $fake->assertSent(
        fn (RequestInterface $request): bool => $request->getHeaderLine('Authorization') === 'Bearer test-key',
    );
});

it('replaces an already-resolved manager', function (): void {
    $original = app(VapiManager::class);

    $fake = Vapi::fake();
    Vapi::calls()->list();

    expect(app(VapiManager::class))->not->toBe($original);
    $fake->assertSentCount(1);
});

it('supports queueing after faking and exposes the last request', function (): void {
    $fake = Vapi::fake();
    $fake->queue(new Response(200, [], '[]'));

    expect($fake->lastRequest())->toBeNull();
    $fake->assertNothingSent();

    Vapi::calls()->list();

    expect($fake->lastRequest())->toBeInstanceOf(RequestInterface::class);
});

it('fails the assertion when no request matches', function (): void {
    $fake = Vapi::fake();

    Vapi::calls()->list();

    expect(fn () => $fake->assertSent(fn (RequestInterface $request): bool => false))
        ->toThrow(AssertionFailedError::class);
});
