<?php

declare(strict_types=1);

use TreeshaInfotech\Vapi\Tests\TestCase;
use TreeshaInfotech\Vapi\Tests\Webhooks\WebhookTestCase;

pest()->extend(TestCase::class)->in('ArchTest.php', 'BuildersTest.php', 'ConcurrencyTest.php', 'FakeTest.php', 'ManagerTest.php', 'RawTest.php', 'ServiceProviderTest.php', 'WebhookCustomizationTest.php');
pest()->extend(WebhookTestCase::class)->in('Webhooks');
