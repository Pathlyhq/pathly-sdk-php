<?php

declare(strict_types=1);

namespace Pathly;

final class Scenario
{
    /**
     * @param list<string>|null $regions
     * @param list<string>|null $tags
     */
    public function __construct(
        public string $id,
        public string $name = '',
        public string $type = '',
        public ?string $url = null,
        public ?bool $enabled = null,
        public ?int $intervalSec = null,
        public ?string $method = null,
        public ?int $expectedStatus = null,
        public ?int $maxLatencyMs = null,
        public ?string $expectText = null,
        public ?string $runbook = null,
        public ?string $cron = null,
        public ?string $lastStatus = null,
        public ?array $regions = null,
        public ?array $tags = null,
        public ?string $folder = null,
        public ?string $severity = null,
        public ?string $mutedUntil = null,
        public ?string $scenarioFingerprint = null,
        public ?string $createdAt = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            url: isset($data['url']) ? (is_string($data['url']) ? $data['url'] : null) : null,
            enabled: isset($data['enabled']) ? (bool) $data['enabled'] : null,
            intervalSec: isset($data['intervalSec']) ? (int) $data['intervalSec'] : null,
            method: isset($data['method']) && is_string($data['method']) ? $data['method'] : null,
            expectedStatus: isset($data['expectedStatus']) ? (int) $data['expectedStatus'] : null,
            maxLatencyMs: isset($data['maxLatencyMs']) ? (int) $data['maxLatencyMs'] : null,
            expectText: isset($data['expectText']) && is_string($data['expectText']) ? $data['expectText'] : null,
            runbook: isset($data['runbook']) && is_string($data['runbook']) ? $data['runbook'] : null,
            cron: isset($data['cron']) && is_string($data['cron']) ? $data['cron'] : null,
            lastStatus: isset($data['lastStatus']) && is_string($data['lastStatus']) ? $data['lastStatus'] : null,
            regions: isset($data['regions']) && is_array($data['regions']) ? array_values(array_map('strval', $data['regions'])) : null,
            tags: isset($data['tags']) && is_array($data['tags']) ? array_values(array_map('strval', $data['tags'])) : null,
            folder: isset($data['folder']) && is_string($data['folder']) ? $data['folder'] : null,
            severity: isset($data['severity']) && is_string($data['severity']) ? $data['severity'] : null,
            mutedUntil: isset($data['mutedUntil']) && is_string($data['mutedUntil']) ? $data['mutedUntil'] : null,
            scenarioFingerprint: isset($data['scenarioFingerprint']) && is_string($data['scenarioFingerprint']) ? $data['scenarioFingerprint'] : null,
            createdAt: isset($data['createdAt']) && is_string($data['createdAt']) ? $data['createdAt'] : null,
        );
    }
}
