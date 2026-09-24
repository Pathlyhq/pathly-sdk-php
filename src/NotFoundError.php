<?php

declare(strict_types=1);

namespace Pathly;

/** Resource missing from the authenticated organization. */
class NotFoundError extends ApiError
{
    public function __construct(string $message, string $path = '')
    {
        parent::__construct(404, $message, $path);
    }
}
