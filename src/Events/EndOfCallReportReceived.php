<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Events;

use Vapi\Types\ServerMessageEndOfCallReport;

final class EndOfCallReportReceived extends WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        array $payload,
        string $type,
        public readonly ServerMessageEndOfCallReport $report,
    ) {
        parent::__construct($payload, $type);
    }
}
