<?php

declare(strict_types=1);

namespace Pathly;

/**
 * Error returned by the Pathly API or the transport layer.
 */
class ApiError extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        string $message,
        public readonly string $path = '',
    ) {
        parent::__construct($this->format($message));
    }

    private function format(string $message): string
    {
        if ($this->path === '') {
            return sprintf('%s (HTTP %d)', $message, $this->statusCode);
        }

        return sprintf('%s: %s (HTTP %d)', $this->path, $message, $this->statusCode);
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }
}
