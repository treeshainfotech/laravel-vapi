<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Facades;

use Illuminate\Support\Facades\Facade;
use Psr\Http\Message\ResponseInterface;
use TreeshaInfotech\Vapi\RawVapiClient;
use TreeshaInfotech\Vapi\Testing\FakeHttpClient;
use TreeshaInfotech\Vapi\Testing\VapiFake;
use TreeshaInfotech\Vapi\VapiManager;
use Vapi\Analytics\AnalyticsClient;
use Vapi\Assistants\AssistantsClient;
use Vapi\Calls\CallsClient;
use Vapi\Campaigns\CampaignsClient;
use Vapi\Chats\ChatsClient;
use Vapi\Core\Client\RawClient;
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

/**
 * @method static AnalyticsClient analytics()
 * @method static AssistantsClient assistants()
 * @method static CallsClient calls()
 * @method static CampaignsClient campaigns()
 * @method static ChatsClient chats()
 * @method static VapiClient client()
 * @method static EvalClient eval()
 * @method static FilesClient files()
 * @method static InsightClient insight()
 * @method static ObservabilityScorecardClient observabilityScorecard()
 * @method static PhoneNumbersClient phoneNumbers()
 * @method static ProviderResourcesClient providerResources()
 * @method static RawVapiClient raw()
 * @method static SessionsClient sessions()
 * @method static SquadsClient squads()
 * @method static StructuredOutputsClient structuredOutputs()
 * @method static ToolsClient tools()
 *
 * @see VapiManager
 */
final class Vapi extends Facade
{
    /**
     * Replace the manager with one wired to an in-memory transport.
     * Queued responses are returned in order; an empty queue answers
     * with an empty 200, which the SDK treats as a null result.
     */
    public static function fake(ResponseInterface ...$responses): VapiFake
    {
        $transport = new FakeHttpClient;
        $transport->queue(...$responses);

        $apiKey = config('vapi.api_key');
        $baseUrl = config('vapi.base_url');
        $token = is_string($apiKey) && $apiKey !== '' ? $apiKey : 'fake-api-key';
        $base = is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : 'https://api.vapi.ai';

        self::swap(new VapiManager(
            new VapiClient(
                token: $token,
                options: [
                    'baseUrl' => $base,
                    'client' => $transport,
                    'maxRetries' => 0,
                ],
            ),
            new RawVapiClient(new RawClient([
                'client' => $transport,
                'maxRetries' => 0,
                'headers' => ['Authorization' => 'Bearer '.$token],
            ]), $base),
        ));

        return new VapiFake($transport);
    }

    public static function getFacadeAccessor(): string
    {
        return VapiManager::class;
    }
}
