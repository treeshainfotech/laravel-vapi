<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Events;

use Vapi\Types\ServerMessageStatusUpdate;

final class StatusUpdateReceived extends WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        array $payload,
        string $type,
        public readonly ServerMessageStatusUpdate $statusUpdate,
    ) {
        parent::__construct($payload, $type);
    }
}
