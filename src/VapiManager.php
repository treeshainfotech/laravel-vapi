<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi;

use Vapi\Analytics\AnalyticsClient;
use Vapi\Assistants\AssistantsClient;
use Vapi\Calls\CallsClient;
use Vapi\Campaigns\CampaignsClient;
use Vapi\Chats\ChatsClient;
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

final readonly class VapiManager
{
    public function __construct(
        private VapiClient $client,
        private RawVapiClient $raw,
    ) {}

    public function client(): VapiClient
    {
        return $this->client;
    }

    /**
     * Raw JSON access to Vapi endpoints the SDK does not cover.
     */
    public function raw(): RawVapiClient
    {
        return $this->raw;
    }

    public function analytics(): AnalyticsClient
    {
        return $this->client->analytics;
    }

    public function assistants(): AssistantsClient
    {
        return $this->client->assistants;
    }

    public function calls(): CallsClient
    {
        return $this->client->calls;
    }

    public function campaigns(): CampaignsClient
    {
        return $this->client->campaigns;
    }

    public function chats(): ChatsClient
    {
        return $this->client->chats;
    }

    public function eval(): EvalClient
    {
        return $this->client->eval;
    }

    public function files(): FilesClient
    {
        return $this->client->files;
    }

    public function insight(): InsightClient
    {
        return $this->client->insight;
    }

    public function observabilityScorecard(): ObservabilityScorecardClient
    {
        return $this->client->observabilityScorecard;
    }

    public function phoneNumbers(): PhoneNumbersClient
    {
        return $this->client->phoneNumbers;
    }

    public function providerResources(): ProviderResourcesClient
    {
        return $this->client->providerResources;
    }

    public function sessions(): SessionsClient
    {
        return $this->client->sessions;
    }

    public function squads(): SquadsClient
    {
        return $this->client->squads;
    }

    public function structuredOutputs(): StructuredOutputsClient
    {
        return $this->client->structuredOutputs;
    }

    public function tools(): ToolsClient
    {
        return $this->client->tools;
    }
}
