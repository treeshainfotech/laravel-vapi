<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Events;

use Vapi\Core\Json\JsonSerializableType;

/**
 * Dispatched for every verified Vapi webhook, regardless of message type.
 */
final class WebhookReceived extends WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  ?JsonSerializableType  $message  The typed SDK message, or null when the
     *                                          type is unknown or fails to deserialize.
     */
    public function __construct(
        array $payload,
        string $type,
        public readonly ?JsonSerializableType $message,
    ) {
        parent::__construct($payload, $type);
    }
}
