<?php

declare(strict_types=1);

namespace Pathly;

final class Webhook
{
    /** @param list<string>|null $events */
    public function __construct(
        public string $id,
        public ?array $events = null,
        public ?bool $enabled = null,
        public ?bool $hasSecret = null,
        public ?string $urlFingerprint = null,
        public ?string $createdAt = null,
        public ?string $secret = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            events: isset($data['events']) && is_array($data['events']) ? array_values(array_map('strval', $data['events'])) : null,
            enabled: isset($data['enabled']) ? (bool) $data['enabled'] : null,
            hasSecret: isset($data['hasSecret']) ? (bool) $data['hasSecret'] : null,
            urlFingerprint: isset($data['urlFingerprint']) && is_string($data['urlFingerprint']) ? $data['urlFingerprint'] : null,
            createdAt: isset($data['createdAt']) && is_string($data['createdAt']) ? $data['createdAt'] : null,
            secret: isset($data['secret']) && is_string($data['secret']) ? $data['secret'] : null,
        );
    }
}
