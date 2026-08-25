# Laravel Vapi

A Laravel wrapper for the [Vapi](https://vapi.ai) PHP server SDK ([`vapi/vapi`](https://github.com/VapiAI/server-sdk-php)) — configuration, a `Vapi` facade with full IDE autocomplete, verified webhooks with typed events, generated type-safe request builders, raw API access, and first-class testing fakes.

```php
use TreeshaInfotech\Vapi\Builders\CreateAssistantBuilder;
use TreeshaInfotech\Vapi\Facades\Vapi;
use Vapi\Types\CreateAssistantDtoFirstMessageMode;

$assistant = Vapi::assistants()->create(
    CreateAssistantBuilder::make()
        ->name('Support bot')
        ->firstMessage('Hi, how can I help?')
        ->firstMessageMode(CreateAssistantDtoFirstMessageMode::AssistantSpeaksFirst)
        ->build(),
);
```

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Request builders](#request-builders)
- [Raw endpoint access](#raw-endpoint-access)
- [Webhooks](#webhooks)
- [Testing your application](#testing-your-application)
- [Concurrency](#concurrency)
- [Known upstream SDK limitations](#known-upstream-sdk-limitations)
- [License](#license)

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13
- A Vapi private API key ([Dashboard → Organization Settings → API Keys](https://dashboard.vapi.ai))

## Installation

```sh
composer require treeshainfotech/laravel-vapi
```

The service provider and the `Vapi` facade alias are registered through package auto-discovery — no manual registration needed.

## Configuration

Set your API key in `.env`:

```dotenv
VAPI_API_KEY=your-private-api-key
```

Optionally publish the config file:

```sh
php artisan vendor:publish --tag=vapi-config
```

All options:

| Env var | Config key | Default | Purpose |
|---|---|---|---|
| `VAPI_API_KEY` | `vapi.api_key` | — | Private API key (required; a missing key throws `MissingApiKey` on first use) |
| `VAPI_BASE_URL` | `vapi.base_url` | `https://api.vapi.ai` | API endpoint; change only for proxies |
| `VAPI_TIMEOUT` | `vapi.timeout` | client default | Request timeout in seconds |
| `VAPI_MAX_RETRIES` | `vapi.max_retries` | `2` | Retries for 408/429/5xx, with exponential backoff |
| — | `vapi.headers` | `[]` | Extra headers sent with every request |
| `VAPI_WEBHOOK_ENABLED` | `vapi.webhook.enabled` | `false` | Registers the webhook route when `true` |
| `VAPI_WEBHOOK_PATH` | `vapi.webhook.path` | `webhooks/vapi` | Webhook route path |
| `VAPI_WEBHOOK_SECRET` | `vapi.webhook.secret` | — | Shared secret (`x-vapi-secret` header or Bearer token) |
| `VAPI_WEBHOOK_HMAC_SECRET` | `vapi.webhook.hmac.secret` | — | HMAC credential secret (alternative to the shared secret) |
| `VAPI_WEBHOOK_HMAC_HEADER` | `vapi.webhook.hmac.header` | `x-vapi-signature` | Signature header, must match your Vapi credential |
| `VAPI_WEBHOOK_HMAC_ALGORITHM` | `vapi.webhook.hmac.algorithm` | `sha256` | Hash algorithm, must match your Vapi credential |

### Bringing your own HTTP client

The package resolves a PSR-18 client via discovery (Guzzle ships with it). To use a specific client — a configured Guzzle instance, a middleware-wrapped client — bind it in the container before the manager is resolved:

```php
$this->app->instance(\Psr\Http\Client\ClientInterface::class, $myPsr18Client);
```

## Usage

The `Vapi` facade exposes one method per Vapi resource, returning the SDK's typed clients:

```php
use TreeshaInfotech\Vapi\Facades\Vapi;

Vapi::assistants();             // AssistantsClient
Vapi::calls();                  // CallsClient
Vapi::chats();                  // ChatsClient
Vapi::campaigns();              // CampaignsClient
Vapi::sessions();               // SessionsClient
Vapi::phoneNumbers();           // PhoneNumbersClient
Vapi::squads();                 // SquadsClient
Vapi::tools();                  // ToolsClient
Vapi::files();                  // FilesClient
Vapi::structuredOutputs();      // StructuredOutputsClient
Vapi::analytics();              // AnalyticsClient
Vapi::insight();                // InsightClient
Vapi::eval();                   // EvalClient
Vapi::observabilityScorecard(); // ObservabilityScorecardClient
Vapi::providerResources();      // ProviderResourcesClient

Vapi::client();                 // the underlying Vapi\VapiClient
Vapi::raw();                    // raw JSON access, see below
```

Example calls:

```php
use TreeshaInfotech\Vapi\Builders\ListCallsRequestBuilder;

// List recent calls for an assistant
$calls = Vapi::calls()->list(
    ListCallsRequestBuilder::make()
        ->assistantId('asst_123')
        ->limit(25.0)
        ->createdAtGt(new DateTime('-7 days'))
        ->build(),
);

// Fetch one call
$call = Vapi::calls()->get('call_abc');
$call?->status; // string, see Vapi\Types\CallStatus for the enum values
```

Prefer constructor injection? The manager is a container singleton — type-hint `TreeshaInfotech\Vapi\VapiManager` (alias `vapi`) anywhere instead of using the facade.

### Error handling

The SDK throws `Vapi\Exceptions\VapiApiException` for non-2xx API responses and `Vapi\Exceptions\VapiException` for transport failures:

```php
use Vapi\Exceptions\VapiApiException;

try {
    $call = Vapi::calls()->get($id);
} catch (VapiApiException $exception) {
    $exception->getCode(); // HTTP status code
    $exception->getBody(); // decoded error body
}
```

Note: exceptions thrown while **deserializing** responses (malformed payloads) are bare `\Exception`s from the generated SDK code — catch `\Throwable` around SDK calls when you need a hard guarantee.

## Request builders

The SDK's request DTOs take untyped arrays, and enum-valued fields are plain strings. This package generates **40 fluent builders** — one for every request DTO the SDK's clients accept that can be typed soundly — restoring autocomplete and strict types, including **native enum parameters**:

```php
use TreeshaInfotech\Vapi\Builders\CreateAssistantBuilder;
use TreeshaInfotech\Vapi\Builders\CreateCampaignBuilder;
use Vapi\Types\CreateAssistantDtoClientMessagesItem;

$dto = CreateAssistantBuilder::make()
    ->name('Support bot')
    ->clientMessages(
        CreateAssistantDtoClientMessagesItem::ConversationUpdate,
        CreateAssistantDtoClientMessagesItem::StatusUpdate,
    )
    ->build();

// Required DTO fields become typed make() arguments:
$campaign = CreateCampaignBuilder::make('August outreach')
    ->phoneNumberId('pn_1')
    ->build();
```

Rules of the road:

- Unset fields are **omitted from the payload entirely** — a builder never sends nulls you didn't set.
- Enum-annotated fields accept **only the enum** (e.g. `CreateAssistantDtoFirstMessageMode::AssistantSpeaksFirst`), which is what makes typos impossible.
- Array fields of a single type take variadics: `->clientMessages(Item::A, Item::B)`.
- A few deeply-unioned fields (e.g. `hooks`, free-form `metadata`) have no setter — each builder's docblock lists them; pass those through the DTO constructor.
- Seven discriminated-union wrapper DTOs are excluded on purpose (they have their own static factories in the SDK).

## Raw endpoint access

The live Vapi API has endpoints the official SDK does not cover yet (Knowledge Bases V2, Simulations, Reporting Boards, Campaigns V2, call recordings and logs). `Vapi::raw()` reaches any endpoint with the configured authentication, base URL, retries, and HTTP client:

```php
$bases = Vapi::raw()->get('/v2/knowledge-base', ['limit' => 10]);
$created = Vapi::raw()->post('/v2/knowledge-base', ['name' => 'Docs']);
Vapi::raw()->patch("/v2/knowledge-base/{$id}", ['name' => 'Docs v2']);
Vapi::raw()->delete("/v2/knowledge-base/{$id}");
```

`get`/`post`/`patch`/`put`/`delete` return decoded JSON (`null` for empty responses). Non-2xx responses throw `Vapi\Exceptions\VapiApiException` with the status code, the decoded error body, and a message naming the method and path.

## Webhooks

Vapi POSTs server messages (end-of-call reports, transcripts, tool calls, status updates, …) to your app. Enable the route and configure verification:

```dotenv
VAPI_WEBHOOK_ENABLED=true
VAPI_WEBHOOK_SECRET=your-shared-secret
```

The package registers `POST /webhooks/vapi` (route name `vapi.webhook`, path configurable) outside the `web` middleware group, so CSRF and sessions don't apply. Point your assistant, phone number, or organization **Server URL** at it.

### Custom routing

Every piece of the webhook stack is public, so you choose your level of control:

**Change the path** — set `VAPI_WEBHOOK_PATH=integrations/vapi/hooks`. (Re-run `php artisan route:cache` after changing it if you cache routes.)

**Place the route yourself** — set `VAPI_WEBHOOK_ENABLED=false` and register it in any route file, with any group:

```php
Route::vapiWebhook('integrations/vapi');                       // macro
WebhookRoute::register('integrations/vapi');                   // static-analysis-friendly equivalent

Route::domain('hooks.example.com')->middleware('throttle:60,1')->group(function (): void {
    Route::vapiWebhook('vapi', 'tenant.vapi.webhook');         // custom name for multi-registration
});
```

Both forms register the controller + verification middleware and compose with prefixes, domains, and extra middleware. If you place the route in `routes/web.php`, exclude it from CSRF (`$middleware->validateCsrfTokens(except: ['integrations/vapi'])` in `bootstrap/app.php`) — Vapi's POSTs carry no CSRF token. `routes/api.php` needs no exclusion but adds the `/api` prefix.

**Write your own controller** — inject `WebhookProcessor` to reuse the whole pipeline (decode, validate, typed deserialization, events, responder) while adding your own concerns:

```php
use TreeshaInfotech\Vapi\Webhooks\WebhookProcessor;

final class MyVapiController
{
    public function __invoke(Request $request, WebhookProcessor $processor): JsonResponse
    {
        Log::info('vapi webhook', ['tenant' => $request->route('tenant')]);

        return $processor->handle($request);
    }
}
```

Keep `VerifyVapiWebhook` (or a stricter replacement) on any route that reaches the processor — verification is middleware, not part of the pipeline.

**Go fully custom** — `ServerMessageTypeMap`, the event classes, and the responder contract are all public API; a bespoke controller can reuse the type-safe deserialization and handle everything else its own way.

### Verification

Requests are rejected with `401` unless they carry valid credentials, and with `500` when no verification is configured (fail closed). Accepted methods:

- **Shared secret** (`VAPI_WEBHOOK_SECRET`) — matched against the legacy `x-vapi-secret` header *or* an `Authorization: Bearer` token, covering Vapi's credential-based bearer auth.
- **HMAC signature** (`VAPI_WEBHOOK_HMAC_SECRET`) — verifies the signature header against the raw request body; header name and algorithm are configurable and must match the credential configured in your Vapi dashboard. Hex, `sha256=`-prefixed, and base64 digests are accepted.

### Events

Every verified webhook dispatches `WebhookReceived`. Common message types also dispatch a typed event:

| Event | Message type | Typed payload property |
|---|---|---|
| `WebhookReceived` | all | `?JsonSerializableType $message` |
| `EndOfCallReportReceived` | `end-of-call-report` | `ServerMessageEndOfCallReport $report` |
| `StatusUpdateReceived` | `status-update` | `ServerMessageStatusUpdate $statusUpdate` |
| `TranscriptReceived` | `transcript` | `ServerMessageTranscript $transcript` |
| `ToolCallsReceived` | `tool-calls` | `ServerMessageToolCalls $toolCalls` |

Every event also carries `array $payload` (the raw decoded body) and `string $type`. Messages with types the SDK doesn't know yet still dispatch `WebhookReceived` with the raw payload and `message: null` — new Vapi message types never break your endpoint.

```php
use TreeshaInfotech\Vapi\Events\EndOfCallReportReceived;

Event::listen(function (EndOfCallReportReceived $event): void {
    ProcessCallReport::dispatch($event->report);
});
```

Listeners can use queue attributes on Laravel 13 (`#[Tries(3)]`, `#[Backoff(60)]`, …).

> The package deserializes messages by their `message.type` discriminator into the correct SDK class itself. The SDK's own `ServerMessage` union resolves every payload to its first arm and is not used.

### Responding to webhooks

Some messages (`assistant-request`, `tool-calls`, `transfer-destination-request`) expect a response body. Bind a responder:

```php
use TreeshaInfotech\Vapi\Contracts\RespondsToVapiWebhooks;
use TreeshaInfotech\Vapi\Events\WebhookReceived;
use Vapi\Types\ServerMessageResponse;

final class VapiResponder implements RespondsToVapiWebhooks
{
    public function respond(WebhookReceived $webhook): ?ServerMessageResponse
    {
        // return a ServerMessageResponse to answer, or null for the default acknowledgement
    }
}

// In a service provider:
$this->app->bind(RespondsToVapiWebhooks::class, VapiResponder::class);
```

### Testing webhooks locally

Run `php artisan serve`, tunnel with ngrok or Expose (`ngrok http 8000`), and set the tunnel URL + your secret as the Server URL credential in the Vapi dashboard. Real traffic then exercises the full verification and event pipeline.

## Testing your application

`Vapi::fake()` routes every Vapi call — resource clients and `raw()` alike — through an in-memory transport. Faked calls still exercise the real SDK request pipeline (headers, serialization, URL building); only the network is fake:

```php
use GuzzleHttp\Psr7\Response;
use TreeshaInfotech\Vapi\Facades\Vapi;

$fake = Vapi::fake(new Response(200, [], '[]'));

// code under test
Vapi::calls()->list();

$fake->assertSentCount(1);
$fake->assertSent(fn ($request) => str_contains((string) $request->getUri(), '/call'));
```

The returned `VapiFake` handle:

| Method | Purpose |
|---|---|
| `queue(Response ...$responses)` | Append more queued responses |
| `requests()` | All recorded PSR-7 requests |
| `lastRequest()` | The most recent request, or null |
| `assertSent(callable $callback)` | At least one request matches |
| `assertSentCount(int $count)` | Exact request count |
| `assertNothingSent()` | No requests were made |

Queued responses are consumed in order. When the queue is empty, an empty `200` is returned, which the SDK surfaces as `null` — unqueued calls never explode. Retries are disabled under the fake, so queued error responses don't trigger backoff sleeps.

## Concurrency

The Vapi PHP SDK is synchronous (there is no PHP equivalent of the Python SDK's `AsyncVapi`), but Laravel's `Concurrency` facade runs multiple calls in parallel processes — several requests cost one round-trip of wall time:

```php
use Illuminate\Support\Facades\Concurrency;

[$call, $assistant, $numbers] = Concurrency::run([
    fn () => Vapi::calls()->get($callId),
    fn () => Vapi::assistants()->get($assistantId),
    fn () => Vapi::phoneNumbers()->list(),
]);
```

For fire-and-forget work, dispatch a queued job and let the webhook events close the loop.

Testing caveat: the default `process` driver runs closures in fresh PHP processes where a `Vapi::fake()` from your test does not exist — switch to the sync driver in tests (`config(['concurrency.default' => 'sync'])`).

## Known upstream SDK limitations

Validated against the live OpenAPI spec (`https://api.vapi.ai/api-json`); these apply to Vapi's official PHP *and* Python SDKs equally:

- ~58 API operations have no SDK client (Knowledge Bases V2, Simulations, Reporting Boards, Campaigns V2, call recordings/logs). Use [`Vapi::raw()`](#raw-endpoint-access).
- Two webhook message types (`call.artifact.upload`, `campaign.predial`) aren't modeled yet — they arrive as untyped `WebhookReceived` events.
- `CreateCallDto` lacks the API's `assistantVersion` field — pass it via the `$options['bodyProperties']` argument the SDK's client methods accept.
- The SDK repository currently ships without a license file.

## License

MIT — see [LICENSE.md](LICENSE.md). Contributing and package maintenance (quality gates, builder regeneration, SDK upgrades) are covered in [CONTRIBUTING.md](CONTRIBUTING.md).
