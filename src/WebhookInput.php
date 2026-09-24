<?php

declare(strict_types=1);

namespace Pathly;

final class WebhookInput
{
    /** @param list<string>|null $events */
    public function __construct(
        public string $url,
        public ?array $events = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = ['url' => $this->url];
        if ($this->events !== null) {
            $out['events'] = $this->events;
        }

        return $out;
    }
}
