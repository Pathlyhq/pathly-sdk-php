<?php

declare(strict_types=1);

namespace Pathly;

final class SlaTarget
{
    public function __construct(
        public string $id,
        public ?string $monitorId = null,
        public ?string $name = null,
        public ?float $objectivePct = null,
        public ?int $windowDays = null,
        public ?bool $excludeMaintenance = null,
        public ?float $warnAtBudgetRatio = null,
        public ?bool $enabled = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            monitorId: isset($data['monitorId']) && is_string($data['monitorId']) ? $data['monitorId'] : null,
            name: isset($data['name']) && is_string($data['name']) ? $data['name'] : null,
            objectivePct: isset($data['objectivePct']) ? (float) $data['objectivePct'] : null,
            windowDays: isset($data['windowDays']) ? (int) $data['windowDays'] : null,
            excludeMaintenance: isset($data['excludeMaintenance']) ? (bool) $data['excludeMaintenance'] : null,
            warnAtBudgetRatio: isset($data['warnAtBudgetRatio']) ? (float) $data['warnAtBudgetRatio'] : null,
            enabled: isset($data['enabled']) ? (bool) $data['enabled'] : null,
        );
    }
}
