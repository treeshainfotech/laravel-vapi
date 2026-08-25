<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Events;

abstract class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload  The raw decoded webhook payload.
     * @param  string  $type  The `message.type` discriminator sent by Vapi.
     */
    public function __construct(
        public readonly array $payload,
        public readonly string $type,
    ) {}
}
