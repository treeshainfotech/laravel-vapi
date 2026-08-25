<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Tests\Webhooks;

use TreeshaInfotech\Vapi\Tests\TestCase;

abstract class WebhookTestCase extends TestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('vapi.webhook.enabled', true);
        $app['config']->set('vapi.webhook.secret', 'webhook-secret');
    }
}
