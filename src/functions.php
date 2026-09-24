<?php

declare(strict_types=1);

namespace Pathly;

function is_not_found(\Throwable $err): bool
{
    return $err instanceof ApiError && $err->isNotFound();
}

/**
 * @param list<mixed> $path
 */
function field_of(array $path): string
{
    $parts = [];
    foreach ($path as $item) {
        if (is_string($item)) {
            $parts[] = $item;
        } elseif (is_int($item) || is_float($item)) {
            $parts[] = (string) (int) $item;
        }
    }

    return $parts === [] ? 'body' : implode('.', $parts);
}

function parse_api_error(int $status, string $path, string $body): ApiError
{
    $text = trim($body);
    $message = $text !== '' ? $text : http_status_text($status);
    $parsed = json_decode($text, true);
    if (is_array($parsed) && isset($parsed['error']) && is_string($parsed['error']) && $parsed['error'] !== '') {
        $message = $parsed['error'];
        $details = $parsed['details'] ?? [];
        if (is_array($details)) {
            foreach ($details as $detail) {
                if (!is_array($detail)) {
                    continue;
                }
                $field = field_of(is_array($detail['path'] ?? null) ? $detail['path'] : []);
                $detailMsg = isset($detail['message']) && is_string($detail['message']) ? $detail['message'] : '';
                $message .= sprintf(' [%s: %s]', $field, $detailMsg);
            }
        }
    }

    if ($status === 401) {
        $message .= ' Check PATHLY_API_TOKEN: an expired, revoked or truncated key gives the same response.';
    } elseif ($status === 403) {
        $message .= ' Widen the scopes of the key, or check that the plan includes the feature.';
    }

    if ($status === 404) {
        return new NotFoundError($message, $path);
    }

    return new ApiError($status, $message, $path);
}

function http_status_text(int $status): string
{
    return match ($status) {
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
        default => 'HTTP ' . $status,
    };
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function omit_null(array $input): array
{
    $out = [];
    foreach ($input as $key => $value) {
        if ($value !== null) {
            $out[$key] = $value;
        }
    }

    return $out;
}

function backoff(int $attempt): float
{
    return (float) $attempt * 0.5;
}

/**
 * @param array<string, string> $headers
 */
function wait_for(array $headers, int $attempt): float
{
    $raw = $headers['retry-after'] ?? '';
    if ($raw !== '') {
        if (is_numeric(trim($raw))) {
            $secs = (float) trim($raw);
            if ($secs > 0) {
                return min($secs, Client::MAX_RETRY_WAIT);
            }
        }
    }

    return backoff($attempt);
}

function new_idempotency_key(): string
{
    return bin2hex(random_bytes(16));
}
