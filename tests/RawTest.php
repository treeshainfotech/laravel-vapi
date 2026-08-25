<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use TreeshaInfotech\Vapi\Facades\Vapi;
use Vapi\Exceptions\VapiApiException;

it('sends raw get requests through the configured transport', function (): void {
    $fake = Vapi::fake(new Response(200, [], '{"results":[1,2]}'));

    $result = Vapi::raw()->get('/v2/knowledge-base', ['limit' => 10]);

    expect($result)->toBe(['results' => [1, 2]]);
    $fake->assertSent(
        fn (RequestInterface $request): bool => $request->getMethod() === 'GET'
            && (string) $request->getUri() === 'https://api.vapi.ai/v2/knowledge-base?limit=10'
            && $request->getHeaderLine('Authorization') === 'Bearer test-key',
    );
});

it('sends raw post bodies as json', function (): void {
    $fake = Vapi::fake(new Response(201, [], '{"id":"kb_1"}'));

    $result = Vapi::raw()->post('/v2/knowledge-base', ['name' => 'Docs']);

    expect($result)->toBe(['id' => 'kb_1']);
    $fake->assertSent(
        fn (RequestInterface $request): bool => $request->getMethod() === 'POST'
            && json_decode((string) $request->getBody(), true) === ['name' => 'Docs'],
    );
});

it('returns null for empty responses', function (): void {
    Vapi::fake();

    expect(Vapi::raw()->delete('/v2/knowledge-base/kb_1'))->toBeNull();
});

it('throws a typed exception carrying status and body on errors', function (): void {
    Vapi::fake(new Response(404, [], '{"message":"Not Found"}'));

    try {
        Vapi::raw()->get('/v2/knowledge-base/kb_missing');
        $this->fail('Expected a VapiApiException.');
    } catch (VapiApiException $exception) {
        expect($exception->getCode())->toBe(404)
            ->and($exception->getBody())->toBe(['message' => 'Not Found'])
            ->and($exception->getMessage())->toContain('GET /v2/knowledge-base/kb_missing');
    }
});
