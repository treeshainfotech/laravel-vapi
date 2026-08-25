<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Contracts;

use TreeshaInfotech\Vapi\Events\WebhookReceived;
use Vapi\Types\ServerMessageResponse;

/**
 * Bind an implementation in the container to answer Vapi messages that
 * expect a response body, such as assistant-request and tool-calls.
 * Returning null sends the default acknowledgement.
 */
interface RespondsToVapiWebhooks
{
    public function respond(WebhookReceived $webhook): ?ServerMessageResponse;
}
