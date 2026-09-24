<?php

declare(strict_types=1);

namespace Pathly;

final class MaintenanceWindow
{
    public function __construct(
        public string $id,
        public ?string $monitorId = null,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public ?string $reason = null,
        public ?int $weekday = null,
        public ?int $startMinute = null,
        public ?int $durationMin = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            monitorId: isset($data['monitorId']) && is_string($data['monitorId']) ? $data['monitorId'] : null,
            startsAt: isset($data['startsAt']) && is_string($data['startsAt']) ? $data['startsAt'] : null,
            endsAt: isset($data['endsAt']) && is_string($data['endsAt']) ? $data['endsAt'] : null,
            reason: isset($data['reason']) && is_string($data['reason']) ? $data['reason'] : null,
            weekday: isset($data['weekday']) ? (int) $data['weekday'] : null,
            startMinute: isset($data['startMinute']) ? (int) $data['startMinute'] : null,
            durationMin: isset($data['durationMin']) ? (int) $data['durationMin'] : null,
        );
    }
}
