<?php

declare(strict_types=1);

namespace Pathly;

final class ScenarioInput
{
    /**
     * @param list<string>|null $regions
     * @param list<string>|null $tags
     */
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $url = null,
        public ?int $intervalSec = null,
        public ?string $method = null,
        public ?int $expectedStatus = null,
        public ?int $maxLatencyMs = null,
        public ?string $expectText = null,
        public ?array $regions = null,
        public ?array $tags = null,
        public ?string $folder = null,
        public ?string $severity = null,
        public ?string $runbook = null,
        public ?string $cron = null,
        public ?bool $enabled = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return omit_null([
            'name' => $this->name,
            'type' => $this->type,
            'url' => $this->url,
            'intervalSec' => $this->intervalSec,
            'method' => $this->method,
            'expectedStatus' => $this->expectedStatus,
            'maxLatencyMs' => $this->maxLatencyMs,
            'expectText' => $this->expectText,
            'regions' => $this->regions,
            'tags' => $this->tags,
            'folder' => $this->folder,
            'severity' => $this->severity,
            'runbook' => $this->runbook,
            'cron' => $this->cron,
            'enabled' => $this->enabled,
        ]);
    }
}
