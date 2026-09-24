<?php

declare(strict_types=1);

namespace Pathly;

final class SlaTargetInput
{
    public function __construct(
        public float $objectivePct,
        public int $windowDays,
        public ?string $monitorId = null,
        public ?string $name = null,
        public ?bool $excludeMaintenance = null,
        public ?float $warnAtBudgetRatio = null,
        public ?bool $enabled = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return omit_null([
            'objectivePct' => $this->objectivePct,
            'windowDays' => $this->windowDays,
            'monitorId' => $this->monitorId,
            'name' => $this->name,
            'excludeMaintenance' => $this->excludeMaintenance,
            'warnAtBudgetRatio' => $this->warnAtBudgetRatio,
            'enabled' => $this->enabled,
        ]);
    }
}
