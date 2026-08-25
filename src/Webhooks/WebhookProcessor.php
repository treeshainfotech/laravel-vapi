<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Webhooks;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Throwable;
use TreeshaInfotech\Vapi\Contracts\RespondsToVapiWebhooks;
use TreeshaInfotech\Vapi\Events\EndOfCallReportReceived;
use TreeshaInfotech\Vapi\Events\StatusUpdateReceived;
use TreeshaInfotech\Vapi\Events\ToolCallsReceived;
use TreeshaInfotech\Vapi\Events\TranscriptReceived;
use TreeshaInfotech\Vapi\Events\WebhookEvent;
use TreeshaInfotech\Vapi\Events\WebhookReceived;
use Vapi\Core\Json\JsonSerializableType;
use Vapi\Types\ServerMessageEndOfCallReport;
use Vapi\Types\ServerMessageResponse;
use Vapi\Types\ServerMessageStatusUpdate;
use Vapi\Types\ServerMessageToolCalls;
use Vapi\Types\ServerMessageTranscript;

/**
 * The webhook pipeline: decode and validate the payload, deserialize the
 * message by its type discriminator, dispatch the generic and typed
 * events, and produce the response (asking a bound responder first).
 *
 * The package controller delegates here; a custom controller can inject
 * this class to reuse the entire pipeline while adding its own concerns.
 * Verification is NOT part of the pipeline — keep the VerifyVapiWebhook
 * middleware (or your own) on any route that reaches it.
 */
final readonly class WebhookProcessor
{
    public function __construct(
        private Container $container,
        private Dispatcher $events,
    ) {}

    /**
     * @throws BindingResolutionException When a bound responder cannot be resolved.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $this->decode($request->getContent());

        $messageData = $payload['message'] ?? null;
        if (! is_array($messageData)) {
            abort(422, 'The Vapi webhook payload has no message object.');
        }
        $messageData = self::stringKeyed($messageData);

        $type = $messageData['type'] ?? null;
        if (! is_string($type) || $type === '') {
            abort(422, 'The Vapi webhook message has no type.');
        }

        $received = new WebhookReceived($payload, $type, $this->deserialize($type, $messageData));
        $this->events->dispatch($received);

        $typed = $this->typedEvent($received);
        if ($typed instanceof WebhookEvent) {
            $this->events->dispatch($typed);
        }

        $response = $this->respond($received);
        if ($response instanceof ServerMessageResponse) {
            return new JsonResponse($response);
        }

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(422, 'The Vapi webhook payload is not valid JSON.');
        }

        if (! is_array($decoded)) {
            abort(422, 'The Vapi webhook payload is not a JSON object.');
        }

        return self::stringKeyed($decoded);
    }

    /**
     * @param  array<string, mixed>  $messageData
     */
    private function deserialize(string $type, array $messageData): ?JsonSerializableType
    {
        $class = ServerMessageTypeMap::resolve($type);
        if ($class === null) {
            return null;
        }

        try {
            /** @var JsonSerializableType $message */
            $message = $class::jsonDeserialize($messageData);

            return $message;
        } catch (Throwable) {
            // An unparseable message still dispatches the generic event with
            // the raw payload; listeners decide how to handle it.
            return null;
        }
    }

    private function typedEvent(WebhookReceived $received): ?WebhookEvent
    {
        $message = $received->message;

        return match (true) {
            $message instanceof ServerMessageEndOfCallReport => new EndOfCallReportReceived($received->payload, $received->type, $message),
            $message instanceof ServerMessageStatusUpdate => new StatusUpdateReceived($received->payload, $received->type, $message),
            $message instanceof ServerMessageToolCalls => new ToolCallsReceived($received->payload, $received->type, $message),
            $message instanceof ServerMessageTranscript => new TranscriptReceived($received->payload, $received->type, $message),
            default => null,
        };
    }

    /**
     * @throws BindingResolutionException
     */
    private function respond(WebhookReceived $received): ?ServerMessageResponse
    {
        if (! $this->container->bound(RespondsToVapiWebhooks::class)) {
            return null;
        }

        return $this->container->make(RespondsToVapiWebhooks::class)->respond($received);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
