<?php

declare(strict_types=1);

use TreeshaInfotech\Vapi\Events\WebhookEvent;

arch()->preset()->php();

// WebhookEvent is the abstract base the typed webhook events extend; an
// abstract parent cannot be final, which the strict preset otherwise demands.
arch()->preset()->strict()->ignoring(WebhookEvent::class);

arch()->preset()->security();
