<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use TreeshaInfotech\Vapi\Builders\CreateAssistantBuilder;
use TreeshaInfotech\Vapi\Builders\Generator\BuilderGenerator;
use TreeshaInfotech\Vapi\Builders\ListCallsRequestBuilder;
use TreeshaInfotech\Vapi\Facades\Vapi;
use Vapi\Types\CreateAssistantDto;
use Vapi\Types\CreateAssistantDtoClientMessagesItem;
use Vapi\Types\CreateAssistantDtoFirstMessageMode;

it('keeps the generated builders up to date', function (): void {
    $generator = new BuilderGenerator;
    $sources = $generator->generateAll();
    $directory = dirname(__DIR__).'/src/Builders';

    $names = array_map(
        static fn (string $file): string => basename($file, '.php'),
        glob($directory.'/*.php') ?: [],
    );
    sort($names, SORT_STRING);

    expect($names)->toBe(
        array_keys($sources),
        'Generated builders are stale. Run `composer build:builders`.',
    );

    foreach ($sources as $builder => $source) {
        expect((string) file_get_contents($directory.'/'.$builder.'.php'))->toBe(
            $source,
            'Generated builders are stale. Run `composer build:builders`.',
        );
    }
});

it('builds a dto with scalar, enum, and enum-array setters', function (): void {
    $dto = CreateAssistantBuilder::make()
        ->name('Support bot')
        ->firstMessage('Hi, how can I help?')
        ->firstMessageMode(CreateAssistantDtoFirstMessageMode::AssistantSpeaksFirst)
        ->clientMessages(
            CreateAssistantDtoClientMessagesItem::ConversationUpdate,
            CreateAssistantDtoClientMessagesItem::StatusUpdate,
        )
        ->build();

    expect($dto)->toBeInstanceOf(CreateAssistantDto::class)
        ->and($dto->name)->toBe('Support bot')
        ->and($dto->firstMessage)->toBe('Hi, how can I help?')
        ->and($dto->firstMessageMode)->toBe('assistant-speaks-first')
        ->and($dto->clientMessages)->toBe(['conversation-update', 'status-update']);
});

it('leaves unset fields out of the serialized payload', function (): void {
    $dto = CreateAssistantBuilder::make()->name('Support bot')->build();

    expect($dto->jsonSerialize())->toBe(['name' => 'Support bot']);
});

it('exposes required fields as make arguments', function (): void {
    $dto = TreeshaInfotech\Vapi\Builders\CreateCampaignBuilder::make('August outreach')
        ->phoneNumberId('pn_1')
        ->build();

    expect($dto->name)->toBe('August outreach')
        ->and($dto->phoneNumberId)->toBe('pn_1');
});

it('builds request objects with date and scalar fields', function (): void {
    $createdAfter = new DateTime('2026-08-01T00:00:00Z');

    $request = ListCallsRequestBuilder::make()
        ->limit(25.0)
        ->createdAtGt($createdAfter)
        ->build();

    expect($request->limit)->toBe(25.0)
        ->and($request->createdAtGt)->toBe($createdAfter);
});

it('sends a built dto through the faked facade', function (): void {
    $fake = Vapi::fake(new Response(200, [], ''));

    Vapi::assistants()->create(
        CreateAssistantBuilder::make()
            ->name('Support bot')
            ->firstMessageMode(CreateAssistantDtoFirstMessageMode::AssistantWaitsForUser)
            ->build(),
    );

    $fake->assertSent(function (RequestInterface $request): bool {
        $body = json_decode((string) $request->getBody(), true);

        return is_array($body)
            && ($body['name'] ?? null) === 'Support bot'
            && ($body['firstMessageMode'] ?? null) === 'assistant-waits-for-user';
    });
});
