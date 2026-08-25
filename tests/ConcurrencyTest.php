<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Concurrency;
use TreeshaInfotech\Vapi\Facades\Vapi;

it('runs faked vapi calls under the concurrency sync driver', function (): void {
    config()->set('concurrency.default', 'sync');

    $fake = Vapi::fake(
        new Response(200, [], '[]'),
        new Response(200, [], '[]'),
    );

    [$calls, $assistants] = Concurrency::run([
        fn (): ?array => Vapi::calls()->list(),
        fn (): ?array => Vapi::assistants()->list(),
    ]);

    expect($calls)->toBe([])
        ->and($assistants)->toBe([])
        ->and($fake->requests())->toHaveCount(2);
});
