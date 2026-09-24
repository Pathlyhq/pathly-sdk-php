<?php

declare(strict_types=1);

namespace Pathly\Tests;

use Pathly\ApiError;
use Pathly\Client;
use Pathly\MaintenanceWindowInput;
use Pathly\NotFoundError;
use Pathly\Scenario;
use Pathly\ScenarioInput;
use Pathly\SlaTargetInput;
use Pathly\WebhookInput;
use PHPUnit\Framework\TestCase;
use function Pathly\backoff;
use function Pathly\field_of;
use function Pathly\is_not_found;
use function Pathly\new_idempotency_key;
use function Pathly\omit_null;
use function Pathly\parse_api_error;
use function Pathly\wait_for;

final class ClientTest extends TestCase
{
    /** @param callable(string, string, array<string, string>, ?string, float): array{status:int, body:string, headers:array<string, string>} $handler */
    private function client(callable $handler): Client
    {
        return new Client(
            token: 'sp_test',
            sleep: static function (float $_): void {},
            transport: $handler,
        );
    }

    public function testRequiresToken(): void
    {
        putenv('PATHLY_API_TOKEN');
        $this->expectException(\InvalidArgumentException::class);
        new Client(token: '');
    }

    public function testDefaultsFromEnv(): void
    {
        putenv('PATHLY_API_TOKEN=sp_env');
        putenv('PATHLY_API_URL=https://example.test/');
        $calls = [];
        $c = new Client(
            sleep: static function (float $_): void {},
            transport: function (string $method, string $url, array $headers, ?string $body, float $timeout) use (&$calls): array {
                $calls[] = [$url, $headers['Authorization'] ?? '', $timeout];

                return ['status' => 200, 'body' => '{"planId":"p"}', 'headers' => []];
            },
        );
        self::assertSame('https://example.test', $c->baseUrl);
        $c->ping();
        self::assertSame('https://example.test/v1/usage', $calls[0][0]);
        self::assertSame('Bearer sp_env', $calls[0][1]);
        putenv('PATHLY_API_TOKEN');
        putenv('PATHLY_API_URL');
    }

    public function testDefaultBaseUrl(): void
    {
        putenv('PATHLY_API_URL');
        $c = new Client(token: 'sp_x', sleep: static function (float $_): void {}, transport: static fn (): array => ['status' => 200, 'body' => '{}', 'headers' => []]);
        self::assertSame(Client::DEFAULT_BASE_URL, $c->baseUrl);
    }

    public function testPingAccepts403(): void
    {
        $this->client(static fn (): array => [
            'status' => 403,
            'body' => '{"error":"no"}',
            'headers' => [],
        ])->ping();
        $this->addToAssertionCount(1);
    }

    public function testPingRaisesOtherErrors(): void
    {
        $this->expectException(ApiError::class);
        $this->client(static fn (): array => [
            'status' => 401,
            'body' => '{"error":"no"}',
            'headers' => [],
        ])->ping();
    }

    public function testRetry429ThenSuccess(): void
    {
        $n = 0;
        $waits = [];
        $c = new Client(
            token: 'sp_test',
            sleep: static function (float $s) use (&$waits): void {
                $waits[] = $s;
            },
            transport: static function () use (&$n): array {
                $n++;
                if ($n === 1) {
                    return ['status' => 429, 'body' => '{"error":"slow"}', 'headers' => ['retry-after' => '1']];
                }

                return ['status' => 200, 'body' => '{"id":"mon_1","name":"A","type":"http"}', 'headers' => []];
            },
        );
        $sc = $c->getScenario('mon_1');
        self::assertSame('mon_1', $sc->id);
        self::assertSame(2, $n);
        self::assertSame([1.0], $waits);
    }

    public function testRetryExhausted5xx(): void
    {
        $this->expectException(ApiError::class);
        $this->client(static fn (): array => [
            'status' => 503,
            'body' => '{"error":"down"}',
            'headers' => [],
        ])->getScenario('mon_1');
    }

    public function testTransportErrorRetries(): void
    {
        $this->expectException(ApiError::class);
        $this->client(static function (): array {
            throw new \RuntimeException('boom');
        })->getScenario('mon_1');
    }

    public function testTransportApiErrorRetries(): void
    {
        $this->expectException(ApiError::class);
        $this->client(static function (): array {
            throw new ApiError(0, 'calling GET /v1/x: transport failure', '/v1/x');
        })->getScenario('mon_1');
    }

    public function testInvalidJsonResponse(): void
    {
        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('unreadable');
        $this->client(static fn (): array => [
            'status' => 200,
            'body' => 'not-json',
            'headers' => [],
        ])->getScenario('mon_1');
    }

    public function testNonObjectJsonResponse(): void
    {
        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('not an object');
        $this->client(static fn (): array => [
            'status' => 200,
            'body' => '"string"',
            'headers' => [],
        ])->getScenario('mon_1');
    }

    public function testWaitForHelpers(): void
    {
        self::assertSame(1.0, wait_for(['retry-after' => 'nope'], 2));
        self::assertSame(Client::MAX_RETRY_WAIT, wait_for(['retry-after' => '9999'], 1));
        self::assertSame(0.5, backoff(1));
        self::assertSame(0.5, wait_for(['retry-after' => '0'], 1));
        self::assertNotSame('', new_idempotency_key());
    }

    public function testClientErrorsNotRetried(): void
    {
        $n = 0;
        try {
            $this->client(static function () use (&$n): array {
                $n++;

                return [
                    'status' => 400,
                    'body' => '{"error":"Invalid body","details":[{"message":"required","path":["name"]}]}',
                    'headers' => [],
                ];
            })->ping();
            self::fail('expected');
        } catch (ApiError $e) {
            self::assertSame(1, $n);
            self::assertStringContainsString('name: required', $e->getMessage());
        }
    }

    public function testNotFound(): void
    {
        try {
            $this->client(static fn (): array => [
                'status' => 404,
                'body' => '{"error":"Scenario not found"}',
                'headers' => [],
            ])->getScenario('missing');
            self::fail('expected');
        } catch (NotFoundError $e) {
            self::assertTrue(is_not_found($e));
            self::assertTrue($e->isNotFound());
        }
        self::assertFalse(is_not_found(new \RuntimeException('x')));
    }

    public function testEmptyErrorBodyAndGuidance(): void
    {
        try {
            $this->client(static fn (): array => [
                'status' => 400,
                'body' => '',
                'headers' => [],
            ])->getScenario('x');
            self::fail('expected');
        } catch (ApiError $e) {
            self::assertStringContainsString('Bad Request', $e->getMessage());
        }
        try {
            $this->client(static fn (): array => [
                'status' => 403,
                'body' => '{"error":"refused"}',
                'headers' => [],
            ])->getScenario('x');
            self::fail('expected');
        } catch (ApiError $e) {
            self::assertStringContainsString('scopes', $e->getMessage());
        }
        $plain = new ApiError(500, 'boom');
        self::assertStringContainsString('boom (HTTP 500)', $plain->getMessage());
    }

    public function testFieldOfAndParseDetails(): void
    {
        self::assertSame('body', field_of([]));
        self::assertSame('events.0', field_of(['events', 0.0]));
        $err = parse_api_error(400, '/v1/x', '{"error":"e","details":[{"message":"m","path":["a",1]},"bad"]}');
        self::assertStringContainsString('a.1: m', $err->getMessage());
        $err2 = parse_api_error(418, '/v1/x', 'plain');
        self::assertStringContainsString('plain', $err2->getMessage());
        self::assertStringContainsString('HTTP 418', parse_api_error(418, '', '')->getMessage());
    }

    public function testScenarioCrudListMute(): void
    {
        $c = $this->client(static function (string $method, string $url, array $headers, ?string $body): array {
            if ($method === 'POST' && str_ends_with($url, '/v1/scenarios')) {
                self::assertArrayHasKey('Idempotency-Key', $headers);
                $decoded = json_decode((string) $body, true);
                self::assertIsArray($decoded);
                self::assertArrayHasKey('name', $decoded);
                self::assertArrayNotHasKey('intervalSec', $decoded);

                return ['status' => 201, 'body' => '{"id":"mon_1","name":"' . $decoded['name'] . '","type":"http","url":"https://x"}', 'headers' => []];
            }
            if ($method === 'GET' && str_contains($url, '/v1/scenarios/mon_1')) {
                return ['status' => 200, 'body' => '{"id":"mon_1","name":"Checkout","type":"http"}', 'headers' => []];
            }
            if ($method === 'PATCH') {
                return ['status' => 200, 'body' => '{"id":"mon_1","name":"Checkout2","type":"http"}', 'headers' => []];
            }
            if ($method === 'DELETE') {
                return ['status' => 204, 'body' => '', 'headers' => []];
            }
            if ($method === 'GET' && str_contains($url, '/v1/scenarios?')) {
                return ['status' => 200, 'body' => '{"items":[{"id":"mon_1","name":"Checkout","type":"http"}],"nextCursor":null}', 'headers' => []];
            }
            if (str_ends_with($url, '/mute')) {
                return ['status' => 204, 'body' => '', 'headers' => []];
            }
            self::fail($method . ' ' . $url);
        });

        $sc = $c->createScenario(new ScenarioInput(name: 'Checkout'), 'idem-1');
        self::assertSame('mon_1', $sc->id);
        self::assertSame('https://x', $sc->url);
        self::assertSame('Checkout', $c->getScenario('mon_1')->name);
        self::assertSame('Checkout2', $c->updateScenario('mon_1', new ScenarioInput(name: 'Checkout2'))->name);
        $c->muteScenario('mon_1', '2026-10-01T00:00:00Z');
        $c->muteScenario('mon_1');
        self::assertCount(1, $c->listScenarios());
        $c->deleteScenario('mon_1');
        $c->createScenario(['name' => 'ViaArray']);
    }

    public function testMaintenanceWebhookSla(): void
    {
        $c = $this->client(static function (string $method, string $url): array {
            if (str_contains($url, '/maintenance-windows') && $method === 'POST') {
                return ['status' => 201, 'body' => '{"id":"mw_1","reason":"deploy"}', 'headers' => []];
            }
            if (str_contains($url, '/maintenance-windows/mw_1') && $method === 'GET') {
                return ['status' => 200, 'body' => '{"id":"mw_1"}', 'headers' => []];
            }
            if (str_contains($url, '/maintenance-windows') && $method === 'GET') {
                return ['status' => 200, 'body' => '{"items":[{"id":"mw_1"}]}', 'headers' => []];
            }
            if (str_contains($url, '/maintenance-windows') && $method === 'DELETE') {
                return ['status' => 204, 'body' => '', 'headers' => []];
            }
            if (str_contains($url, '/webhooks') && $method === 'POST') {
                return ['status' => 201, 'body' => '{"id":"wh_1","secret":"sec","events":["run.failed"]}', 'headers' => []];
            }
            if (str_contains($url, '/webhooks/wh_1') && $method === 'GET') {
                return ['status' => 200, 'body' => '{"id":"wh_1","events":["run.failed"]}', 'headers' => []];
            }
            if (str_contains($url, '/webhooks') && $method === 'GET') {
                return ['status' => 200, 'body' => '{"items":[{"id":"wh_1"}]}', 'headers' => []];
            }
            if (str_contains($url, '/webhooks') && $method === 'DELETE') {
                return ['status' => 204, 'body' => '', 'headers' => []];
            }
            if (str_contains($url, '/sla-targets') && $method === 'PUT') {
                return ['status' => 200, 'body' => '{"id":"sla_1","objectivePct":99.9,"windowDays":30}', 'headers' => []];
            }
            if (str_contains($url, '/sla-targets/sla_1') && $method === 'GET') {
                return ['status' => 200, 'body' => '{"id":"sla_1"}', 'headers' => []];
            }
            if (str_contains($url, '/sla-targets') && $method === 'GET') {
                return ['status' => 200, 'body' => '{"items":[{"id":"sla_1"}]}', 'headers' => []];
            }
            if (str_contains($url, '/sla-targets') && $method === 'DELETE') {
                return ['status' => 204, 'body' => '', 'headers' => []];
            }
            self::fail($method . ' ' . $url);
        });

        $mw = $c->createMaintenanceWindow(new MaintenanceWindowInput(reason: 'deploy'));
        self::assertSame('mw_1', $mw->id);
        $c->getMaintenanceWindow('mw_1');
        $c->listMaintenanceWindows();
        $c->deleteMaintenanceWindow('mw_1');
        $c->createMaintenanceWindow(['reason' => 'x'], 'k');

        $wh = $c->createWebhook(new WebhookInput(url: 'https://hooks.example/x', events: ['run.failed']));
        self::assertSame('sec', $wh->secret);
        $c->getWebhook('wh_1');
        $c->listWebhooks();
        $c->deleteWebhook('wh_1');
        $c->createWebhook(['url' => 'https://hooks.example/y']);

        $sla = $c->upsertSlaTarget(new SlaTargetInput(objectivePct: 99.9, windowDays: 30, monitorId: 'mon_1', name: 'n'));
        self::assertSame('sla_1', $sla->id);
        $c->getSlaTarget('sla_1');
        $c->listSlaTargets();
        $c->deleteSlaTarget('sla_1');
        $c->upsertSlaTarget(['objectivePct' => 99.0, 'windowDays' => 7], 'k');
    }

    public function testListPaginationAndCap(): void
    {
        $page = 0;
        $c = $this->client(static function () use (&$page): array {
            $page++;
            if ($page === 1) {
                return ['status' => 200, 'body' => '{"items":[{"id":"a","name":"A","type":"http"}],"nextCursor":"c1"}', 'headers' => []];
            }

            return ['status' => 200, 'body' => '{"items":[{"id":"b","name":"B","type":"http"}],"nextCursor":null}', 'headers' => []];
        });
        self::assertCount(2, $c->listScenarios());

        $page = 0;
        $c2 = $this->client(static function () use (&$page): array {
            $page++;

            return ['status' => 200, 'body' => '{"items":[],"nextCursor":"c' . $page . '"}', 'headers' => []];
        });
        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('pagination exceeded');
        $c2->listScenarios();
    }

    public function testTypesRoundTrip(): void
    {
        $full = [
            'id' => 'mon_1',
            'name' => 'N',
            'type' => 'http',
            'url' => 'https://x',
            'enabled' => true,
            'intervalSec' => 60,
            'method' => 'GET',
            'expectedStatus' => 200,
            'maxLatencyMs' => 1000,
            'expectText' => 'ok',
            'runbook' => 'rb',
            'cron' => '0 * * * *',
            'lastStatus' => 'ok',
            'regions' => ['eu'],
            'tags' => ['t'],
            'folder' => 'f',
            'severity' => 'high',
            'mutedUntil' => '2026-01-01T00:00:00Z',
            'scenarioFingerprint' => 'fp',
            'createdAt' => '2026-01-01T00:00:00Z',
        ];
        $sc = Scenario::fromArray($full);
        self::assertSame('mon_1', $sc->id);
        self::assertSame(['eu'], $sc->regions);

        $in = new ScenarioInput(
            name: 'n',
            type: 'http',
            url: 'https://x',
            intervalSec: 60,
            method: 'GET',
            expectedStatus: 200,
            maxLatencyMs: 1,
            expectText: 'e',
            regions: ['eu'],
            tags: ['t'],
            folder: 'f',
            severity: 'high',
            runbook: 'r',
            cron: 'c',
            enabled: true,
        );
        self::assertArrayHasKey('name', $in->toArray());
        self::assertSame(['a' => 1], omit_null(['a' => 1, 'b' => null]));

        $mw = \Pathly\MaintenanceWindow::fromArray([
            'id' => 'mw',
            'monitorId' => 'm',
            'startsAt' => 's',
            'endsAt' => 'e',
            'reason' => 'r',
            'weekday' => 1,
            'startMinute' => 0,
            'durationMin' => 30,
        ]);
        self::assertSame('mw', $mw->id);
        $mwi = new MaintenanceWindowInput(
            monitorId: 'm',
            startsAt: 's',
            endsAt: 'e',
            reason: 'r',
            weekday: 1,
            startMinute: 0,
            durationMin: 30,
        );
        self::assertArrayHasKey('monitorId', $mwi->toArray());

        $wh = \Pathly\Webhook::fromArray([
            'id' => 'wh',
            'events' => ['run.failed'],
            'enabled' => true,
            'hasSecret' => true,
            'urlFingerprint' => 'fp',
            'createdAt' => 'c',
            'secret' => 's',
        ]);
        self::assertSame('s', $wh->secret);
        self::assertArrayHasKey('events', (new WebhookInput(url: 'https://x', events: ['a']))->toArray());
        self::assertArrayNotHasKey('events', (new WebhookInput(url: 'https://x'))->toArray());

        $sla = \Pathly\SlaTarget::fromArray([
            'id' => 'sla',
            'monitorId' => 'm',
            'name' => 'n',
            'objectivePct' => 99.9,
            'windowDays' => 30,
            'excludeMaintenance' => true,
            'warnAtBudgetRatio' => 0.8,
            'enabled' => true,
        ]);
        self::assertSame(99.9, $sla->objectivePct);
        $si = new SlaTargetInput(
            objectivePct: 99.9,
            windowDays: 30,
            monitorId: 'm',
            name: 'n',
            excludeMaintenance: true,
            warnAtBudgetRatio: 0.8,
            enabled: true,
        );
        self::assertArrayHasKey('objectivePct', $si->toArray());
        self::assertSame('', Scenario::fromArray([])->id);
    }

    public function testJsonEncodeFailure(): void
    {
        $this->expectException(\JsonException::class);
        $this->client(static fn (): array => ['status' => 200, 'body' => '{}', 'headers' => []])
            ->request('POST', '/v1/scenarios', ['v' => NAN]);
    }

    public function testDefaultTransportSuccessAndFailure(): void
    {
        $host = '127.0.0.1';
        $port = 18765;
        $router = sys_get_temp_dir() . '/pathly_sdk_php_router.php';
        file_put_contents($router, <<<'PHP'
<?php
http_response_code(200);
header('Content-Type: application/json');
echo '{"planId":"pro"}';
PHP);
        $cmd = sprintf(
            'php -S %s:%d %s',
            $host,
            $port,
            escapeshellarg($router),
        );
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir() . '/pathly_php_srv.out', 'w'],
            2 => ['file', sys_get_temp_dir() . '/pathly_php_srv.err', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        self::assertIsResource($proc);
        usleep(300_000);
        try {
            $c = new Client(token: 'sp_x', baseUrl: "http://{$host}:{$port}", sleep: static function (float $_): void {});
            $c->ping();
            $this->addToAssertionCount(1);
        } finally {
            proc_terminate($proc);
            proc_close($proc);
            @unlink($router);
        }

        $c2 = new Client(token: 'sp_x', baseUrl: 'http://127.0.0.1:1', sleep: static function (float $_): void {});
        $this->expectException(ApiError::class);
        $c2->ping();
    }

    public function testListPagedSkipsNonArrayItems(): void
    {
        $c = $this->client(static fn (): array => [
            'status' => 200,
            'body' => '{"items":[{"id":"a","name":"A","type":"http"},"bad",null],"nextCursor":null}',
            'headers' => [],
        ]);
        self::assertCount(1, $c->listScenarios());
    }

    public function testDefaultSleepAndLastAttemptSuccess(): void
    {
        $n = 0;
        $c = new Client(
            token: 'sp_x',
            transport: static function () use (&$n): array {
                $n++;
                if ($n === 1) {
                    return ['status' => 429, 'body' => '{"error":"slow"}', 'headers' => ['retry-after' => '0.01']];
                }

                return ['status' => 200, 'body' => '{"planId":"p"}', 'headers' => []];
            },
        );
        $c->ping();
        self::assertSame(2, $n);

        $n = 0;
        $c2 = new Client(
            token: 'sp_x',
            sleep: static function (float $_): void {},
            transport: static function () use (&$n): array {
                $n++;
                if ($n < 4) {
                    return ['status' => 503, 'body' => '{"error":"down"}', 'headers' => []];
                }

                return ['status' => 200, 'body' => '{"planId":"p"}', 'headers' => []];
            },
        );
        $c2->ping();
        self::assertSame(4, $n);
    }

    public function testHttpStatusTexts(): void
    {
        foreach ([401, 403, 404, 429, 500, 503] as $code) {
            $err = parse_api_error($code, '/p', '');
            self::assertInstanceOf(ApiError::class, $err);
        }
    }
}
