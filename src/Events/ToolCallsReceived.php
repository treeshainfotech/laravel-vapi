<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Events;

use Vapi\Types\ServerMessageToolCalls;

final class ToolCallsReceived extends WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        array $payload,
        string $type,
        public readonly ServerMessageToolCalls $toolCalls,
    ) {
        parent::__construct($payload, $type);
    }
}
