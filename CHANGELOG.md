# Changelog

## 0.1.0 — 2026-09-23

- First publish: PHP client (`pathlyhq/sdk`) for Pathly monitoring HTTP
  scenarios, webhooks, maintenance windows and SLA targets.
- `Idempotency-Key` on creates, retries (`Retry-After`, max 4, 90 s cap).
- `ping()` via `GET /v1/usage` (403 accepted).
- PHP 8.1+, `ext-json` only.
