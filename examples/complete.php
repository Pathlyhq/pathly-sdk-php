<?php

declare(strict_types=1);

/**
 * Complete walkthrough against the live Pathly API.
 * Requires PATHLY_API_TOKEN. See https://pathlyhq.com/en/developers
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Pathly\Client;
use Pathly\ScenarioInput;
use Pathly\SlaTargetInput;
use Pathly\WebhookInput;

$client = new Client();
$client->ping();

$scenario = $client->createScenario(new ScenarioInput(
    name: 'sdk-php-example',
    url: 'https://example.com/',
    intervalSec: 3600,
));
echo 'scenario ', $scenario->id, PHP_EOL;

$webhook = $client->createWebhook(new WebhookInput(
    url: 'https://hooks.example.com/pathly',
    events: ['run.failed', 'run.recovered'],
));
if ($webhook->secret !== null) {
    echo 'webhook secret (store once): ', $webhook->secret, PHP_EOL;
}

$client->upsertSlaTarget(new SlaTargetInput(
    objectivePct: 99.9,
    windowDays: 30,
    monitorId: $scenario->id,
    name: 'example 99.9',
));

$client->deleteWebhook($webhook->id);
$client->deleteScenario($scenario->id);
echo "cleaned up — Pathly monitoring\n";
