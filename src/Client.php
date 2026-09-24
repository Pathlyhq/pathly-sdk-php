<?php

declare(strict_types=1);

namespace Pathly;

/**
 * HTTP client for the Pathly public `/v1` API.
 *
 * Hand-written so creates stay idempotent, Retry-After is honoured, and a
 * missing resource (404) stays distinct from a transport failure.
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://api.pathlyhq.com';
    public const MAX_ATTEMPTS = 4;
    public const MAX_RETRY_WAIT = 90.0;
    public const PAGE_SIZE = 200;
    public const MAX_PAGES = 200;

    public readonly string $baseUrl;

    private string $token;

    /** @var callable(float): void */
    private $sleep;

    /**
     * @var callable(string, string, array<string, string>, ?string, float): array{status:int, body:string, headers:array<string, string>}
     */
    private $transport;

    public function __construct(
        ?string $token = null,
        ?string $baseUrl = null,
        public readonly float $timeout = 30.0,
        public readonly string $userAgent = Version::USER_AGENT,
        ?callable $sleep = null,
        ?callable $transport = null,
    ) {
        $resolved = trim($token ?? (getenv('PATHLY_API_TOKEN') ?: ''));
        if ($resolved === '') {
            throw new \InvalidArgumentException(
                'PATHLY_API_TOKEN is required. Export it, or pass $token to Client().',
            );
        }
        $url = trim($baseUrl ?? (getenv('PATHLY_API_URL') ?: ''));
        if ($url === '') {
            $url = self::DEFAULT_BASE_URL;
        }
        $this->baseUrl = rtrim($url, '/');
        $this->token = $resolved;
        $this->sleep = $sleep ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
        $this->transport = $transport ?? [$this, 'defaultTransport'];
    }

    /**
     * @param array<string, string> $headers
     * @return array{status:int, body:string, headers:array<string, string>}
     */
    private function defaultTransport(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeout,
    ): array {
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }
        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ];
        $ctx = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new ApiError(0, sprintf('calling %s %s: transport failure', $method, parse_url($url, PHP_URL_PATH) ?: $url), parse_url($url, PHP_URL_PATH) ?: '');
        }
        $status = 0;
        $respHeaders = [];
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                    $status = (int) $m[1];
                    continue;
                }
                $pos = strpos($line, ':');
                if ($pos !== false) {
                    $name = strtolower(trim(substr($line, 0, $pos)));
                    $respHeaders[$name] = trim(substr($line, $pos + 1));
                }
            }
        }

        return ['status' => $status, 'body' => $raw, 'headers' => $respHeaders];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|null
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $idempotencyKey = null,
        bool $expectJson = true,
    ): ?array {
        $payload = null;
        if ($body !== null) {
            $encoded = json_encode($body, JSON_THROW_ON_ERROR);
            $payload = $encoded;
        }

        for ($attempt = 1; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            [$wait, $result, $err] = $this->attempt($method, $path, $payload, $idempotencyKey, $expectJson, $attempt, false);
            if ($wait <= 0) {
                if ($err !== null) {
                    throw $err;
                }

                return $result;
            }
            ($this->sleep)($wait);
        }

        [, $result, $err] = $this->attempt($method, $path, $payload, $idempotencyKey, $expectJson, self::MAX_ATTEMPTS, true);
        if ($err !== null) {
            throw $err;
        }

        return $result;
    }

    /**
     * @return array{0: float, 1: ?array<string, mixed>, 2: ?\Throwable}
     */
    private function attempt(
        string $method,
        string $path,
        ?string $payload,
        ?string $idempotencyKey,
        bool $expectJson,
        int $attempt,
        bool $last,
    ): array {
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        try {
            $res = ($this->transport)($method, $this->baseUrl . $path, $headers, $payload, $this->timeout);
        } catch (ApiError $e) {
            if ($last) {
                return [0.0, null, $e];
            }

            return [backoff($attempt), null, $e];
        } catch (\Throwable $e) {
            $wrapped = new ApiError(0, sprintf('calling %s %s: %s', $method, $path, $e->getMessage()), $path);
            if ($last) {
                return [0.0, null, $wrapped];
            }

            return [backoff($attempt), null, $wrapped];
        }

        $status = $res['status'];
        $raw = $res['body'];
        $respHeaders = $res['headers'];

        if ($status === 429 || $status >= 500) {
            $failure = parse_api_error($status, $path, $raw);
            if ($last) {
                return [0.0, null, $failure];
            }

            return [wait_for($respHeaders, $attempt), null, $failure];
        }

        if ($status >= 400) {
            return [0.0, null, parse_api_error($status, $path, $raw)];
        }

        if (!$expectJson || $raw === '') {
            return [0.0, null, null];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [0.0, null, new ApiError($status, sprintf('unreadable response from %s %s: %s', $method, $path, $e->getMessage()), $path)];
        }
        if (!is_array($decoded)) {
            return [0.0, null, new ApiError($status, sprintf('unreadable response from %s %s: not an object', $method, $path), $path)];
        }

        /** @var array<string, mixed> $decoded */
        return [0.0, $decoded, null];
    }

    /**
     * Verify the token. A 403 is accepted (key valid, missing org:read).
     */
    public function ping(): void
    {
        try {
            $this->request('GET', '/v1/usage');
        } catch (ApiError $err) {
            if ($err->statusCode === 403) {
                return;
            }
            throw $err;
        }
    }

    // -------------------------------------------------------------- Scenarios

    /** @param ScenarioInput|array<string, mixed> $data */
    public function createScenario(ScenarioInput|array $data, ?string $idempotencyKey = null): Scenario
    {
        $body = $data instanceof ScenarioInput ? $data->toArray() : $data;
        $out = $this->request('POST', '/v1/scenarios', $body, $idempotencyKey ?? new_idempotency_key());

        return Scenario::fromArray($out ?? []);
    }

    public function getScenario(string $scenarioId): Scenario
    {
        $out = $this->request('GET', '/v1/scenarios/' . rawurlencode($scenarioId));

        return Scenario::fromArray($out ?? []);
    }

    /** @param ScenarioInput|array<string, mixed> $data */
    public function updateScenario(string $scenarioId, ScenarioInput|array $data): Scenario
    {
        $body = $data instanceof ScenarioInput ? $data->toArray() : $data;
        $out = $this->request('PATCH', '/v1/scenarios/' . rawurlencode($scenarioId), $body);

        return Scenario::fromArray($out ?? []);
    }

    public function deleteScenario(string $scenarioId): void
    {
        $this->request('DELETE', '/v1/scenarios/' . rawurlencode($scenarioId), null, null, false);
    }

    /** @return list<Scenario> */
    public function listScenarios(): array
    {
        return array_map(
            static fn (array $item): Scenario => Scenario::fromArray($item),
            $this->listPaged('/v1/scenarios'),
        );
    }

    public function muteScenario(string $scenarioId, ?string $mutedUntil = null): void
    {
        $this->request(
            'POST',
            '/v1/scenarios/' . rawurlencode($scenarioId) . '/mute',
            ['mutedUntil' => $mutedUntil],
            null,
            false,
        );
    }

    // ---------------------------------------------------- Maintenance windows

    /** @param MaintenanceWindowInput|array<string, mixed> $data */
    public function createMaintenanceWindow(MaintenanceWindowInput|array $data, ?string $idempotencyKey = null): MaintenanceWindow
    {
        $body = $data instanceof MaintenanceWindowInput ? $data->toArray() : $data;
        $out = $this->request('POST', '/v1/maintenance-windows', $body, $idempotencyKey ?? new_idempotency_key());

        return MaintenanceWindow::fromArray($out ?? []);
    }

    public function getMaintenanceWindow(string $windowId): MaintenanceWindow
    {
        $out = $this->request('GET', '/v1/maintenance-windows/' . rawurlencode($windowId));

        return MaintenanceWindow::fromArray($out ?? []);
    }

    public function deleteMaintenanceWindow(string $windowId): void
    {
        $this->request('DELETE', '/v1/maintenance-windows/' . rawurlencode($windowId), null, null, false);
    }

    /** @return list<MaintenanceWindow> */
    public function listMaintenanceWindows(): array
    {
        return array_map(
            static fn (array $item): MaintenanceWindow => MaintenanceWindow::fromArray($item),
            $this->listPaged('/v1/maintenance-windows'),
        );
    }

    // --------------------------------------------------------------- Webhooks

    /** @param WebhookInput|array<string, mixed> $data */
    public function createWebhook(WebhookInput|array $data, ?string $idempotencyKey = null): Webhook
    {
        $body = $data instanceof WebhookInput ? $data->toArray() : $data;
        $out = $this->request('POST', '/v1/webhooks', $body, $idempotencyKey ?? new_idempotency_key());

        return Webhook::fromArray($out ?? []);
    }

    public function getWebhook(string $webhookId): Webhook
    {
        $out = $this->request('GET', '/v1/webhooks/' . rawurlencode($webhookId));

        return Webhook::fromArray($out ?? []);
    }

    public function deleteWebhook(string $webhookId): void
    {
        $this->request('DELETE', '/v1/webhooks/' . rawurlencode($webhookId), null, null, false);
    }

    /** @return list<Webhook> */
    public function listWebhooks(): array
    {
        return array_map(
            static fn (array $item): Webhook => Webhook::fromArray($item),
            $this->listPaged('/v1/webhooks'),
        );
    }

    // ------------------------------------------------------------ SLA targets

    /** @param SlaTargetInput|array<string, mixed> $data */
    public function upsertSlaTarget(SlaTargetInput|array $data, ?string $idempotencyKey = null): SlaTarget
    {
        $body = $data instanceof SlaTargetInput ? $data->toArray() : $data;
        $out = $this->request('PUT', '/v1/sla-targets', $body, $idempotencyKey ?? new_idempotency_key());

        return SlaTarget::fromArray($out ?? []);
    }

    public function getSlaTarget(string $targetId): SlaTarget
    {
        $out = $this->request('GET', '/v1/sla-targets/' . rawurlencode($targetId));

        return SlaTarget::fromArray($out ?? []);
    }

    public function deleteSlaTarget(string $targetId): void
    {
        $this->request('DELETE', '/v1/sla-targets/' . rawurlencode($targetId), null, null, false);
    }

    /** @return list<SlaTarget> */
    public function listSlaTargets(): array
    {
        return array_map(
            static fn (array $item): SlaTarget => SlaTarget::fromArray($item),
            $this->listPaged('/v1/sla-targets'),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listPaged(string $base): array
    {
        $items = [];
        $cursor = '';
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = ['limit' => (string) self::PAGE_SIZE];
            if ($cursor !== '') {
                $query['cursor'] = $cursor;
            }
            $path = $base . '?' . http_build_query($query);
            $out = $this->request('GET', $path) ?? [];
            $batch = $out['items'] ?? [];
            if (is_array($batch)) {
                foreach ($batch as $row) {
                    if (is_array($row)) {
                        /** @var array<string, mixed> $row */
                        $items[] = $row;
                    }
                }
            }
            $next = $out['nextCursor'] ?? null;
            if ($next === null || $next === '') {
                return $items;
            }
            $cursor = (string) $next;
        }

        throw new ApiError(0, sprintf('pagination exceeded %d pages for %s', self::MAX_PAGES, $base), $base);
    }
}
