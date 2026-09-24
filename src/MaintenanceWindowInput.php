<?php

declare(strict_types=1);

namespace Pathly;

final class MaintenanceWindowInput
{
    public function __construct(
        public ?string $monitorId = null,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public ?string $reason = null,
        public ?int $weekday = null,
        public ?int $startMinute = null,
        public ?int $durationMin = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return omit_null([
            'monitorId' => $this->monitorId,
            'startsAt' => $this->startsAt,
            'endsAt' => $this->endsAt,
            'reason' => $this->reason,
            'weekday' => $this->weekday,
            'startMinute' => $this->startMinute,
            'durationMin' => $this->durationMin,
        ]);
    }
}
