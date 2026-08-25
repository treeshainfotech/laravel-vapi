<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use TreeshaInfotech\Vapi\Exceptions\MissingApiKey;
use TreeshaInfotech\Vapi\Facades\Vapi;
use TreeshaInfotech\Vapi\VapiManager;
use Vapi\Analytics\AnalyticsClient;
use Vapi\Assistants\AssistantsClient;
use Vapi\Calls\CallsClient;
use Vapi\Campaigns\CampaignsClient;
use Vapi\Chats\ChatsClient;
use Vapi\Core\Client\MockHttpClient;
use Vapi\Eval\EvalClient;
use Vapi\Files\FilesClient;
use Vapi\Insight\InsightClient;
use Vapi\ObservabilityScorecard\ObservabilityScorecardClient;
use Vapi\PhoneNumbers\PhoneNumbersClient;
use Vapi\ProviderResources\ProviderResourcesClient;
use Vapi\Sessions\SessionsClient;
use Vapi\Squads\SquadsClient;
use Vapi\StructuredOutputs\StructuredOutputsClient;
use Vapi\Tools\ToolsClient;
use Vapi\VapiClient;

it('binds the manager as a singleton with a container alias', function (): void {
    $manager = app(VapiManager::class);

    expect($manager)
        ->toBeInstanceOf(VapiManager::class)
        ->toBe(app(VapiManager::class))
        ->toBe(app('vapi'));
});

it('throws when no api key is configured', function (): void {
    config()->set('vapi.api_key', null);

    app(VapiManager::class);
})->throws(MissingApiKey::class);

it('exposes the underlying sdk client', function (): void {
    expect(Vapi::client())->toBeInstanceOf(VapiClient::class);
});

it('exposes every resource client', function (string $method, string $class): void {
    expect(Vapi::{$method}())->toBeInstanceOf($class);
})->with([
    ['analytics', AnalyticsClient::class],
    ['assistants', AssistantsClient::class],
    ['calls', CallsClient::class],
    ['campaigns', CampaignsClient::class],
    ['chats', ChatsClient::class],
    ['eval', EvalClient::class],
    ['files', FilesClient::class],
    ['insight', InsightClient::class],
    ['observabilityScorecard', ObservabilityScorecardClient::class],
    ['phoneNumbers', PhoneNumbersClient::class],
    ['providerResources', ProviderResourcesClient::class],
    ['sessions', SessionsClient::class],
    ['squads', SquadsClient::class],
    ['structuredOutputs', StructuredOutputsClient::class],
    ['tools', ToolsClient::class],
]);

it('sends requests through a container-bound psr-18 client', function (): void {
    $mock = new MockHttpClient;
    $mock->append(new Response(200, [], '[]'));
    app()->instance(ClientInterface::class, $mock);

    $result = Vapi::calls()->list();

    expect($result)->toBe([])
        ->and($mock->getRequestCount())->toBe(1)
        ->and((string) $mock->getLastRequest()?->getUri())->toStartWith('https://api.vapi.ai/call')
        ->and($mock->getLastRequest()?->getHeaderLine('Authorization'))->toBe('Bearer test-key');
});
