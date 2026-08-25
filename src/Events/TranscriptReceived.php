<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Events;

use Vapi\Types\ServerMessageTranscript;

final class TranscriptReceived extends WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        array $payload,
        string $type,
        public readonly ServerMessageTranscript $transcript,
    ) {
        parent::__construct($payload, $type);
    }
}
