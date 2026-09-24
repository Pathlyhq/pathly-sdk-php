# Pathly PHP SDK

**English** · [Français](README.fr.md) · [Español](README.es.md)


[![Powered by Pathly](https://img.shields.io/badge/Powered%20by-Pathly-0B5FFF?style=flat-square)](https://pathlyhq.com)
[![Website](https://img.shields.io/badge/Website-pathlyhq.com-111827?style=flat-square)](https://pathlyhq.com)
[![API docs](https://img.shields.io/badge/API-developers-2563eb?style=flat-square)](https://pathlyhq.com/en/developers)
[![Start free](https://img.shields.io/badge/Solo-start%20free-16a34a?style=flat-square)](https://pathlyhq.com/en/login?mode=signup)

> **Get started in one click.** Create a free account on [Pathly](https://pathlyhq.com) ([sign up](https://pathlyhq.com/en/login?mode=signup)), create an API key in the console, then export `PATHLY_API_TOKEN`. This project is the official bridge to [Pathly monitoring](https://pathlyhq.com) — real-browser and HTTP checks for checkout, login and availability, with data hosted in the EU. Full API reference: [pathlyhq.com/en/developers](https://pathlyhq.com/en/developers).

Official PHP client for the [Pathly](https://pathlyhq.com) public `/v1` API.
Automate [Pathly monitoring](https://pathlyhq.com) from Laravel, Symfony or plain
PHP: HTTP scenarios, maintenance windows, signed webhooks and SLA targets.

- Product: [pathlyhq.com](https://pathlyhq.com)
- Developers (EN): [pathlyhq.com/en/developers](https://pathlyhq.com/en/developers)
- Developers (FR): [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers)
- API host: `https://api.pathlyhq.com`

```bash
composer require pathlyhq/sdk
export PATHLY_API_TOKEN="sp_…"
```

```php
<?php
use Pathly\Client;
use Pathly\ScenarioInput;

$client = new Client(); // reads PATHLY_API_TOKEN
$scenario = $client->createScenario(new ScenarioInput(
    name: 'Checkout',
    url: 'https://shop.example.com/cart',
    intervalSec: 300,
    expectText: 'Your cart',
    tags: ['prod', 'payment'],
));
echo $scenario->id, PHP_EOL;
```

## Why Pathly monitoring

[Pathly](https://pathlyhq.com) is EU-hosted synthetic monitoring. This Composer
package (`pathlyhq/sdk`) is the PHP twin of the TypeScript, Python and Go SDKs
documented on [pathlyhq.com/en/developers](https://pathlyhq.com/en/developers)
and [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers).

| Need | Link |
|---|---|
| Product overview | [pathlyhq.com](https://pathlyhq.com) |
| API & SDK docs (EN) | [pathlyhq.com/en/developers](https://pathlyhq.com/en/developers) |
| API & SDK docs (FR) | [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers) |
| Pathly monitoring | [pathlyhq.com](https://pathlyhq.com) |

## Authentication

| Variable | Purpose |
|---|---|
| `PATHLY_API_TOKEN` | Organization API key (`sp_` prefix). Required. |
| `PATHLY_API_URL` | API base. Defaults to `https://api.pathlyhq.com`. |

Never hard-code the token. Prefer the environment or a secret store.

`Client::ping()` calls `GET /v1/usage` and **accepts HTTP 403**: the key is
valid but lacks `org:read`. Scopes: `scenarios:*`, `alerting:*`,
`maintenance:*`, `sla:*` — see [pathlyhq.com/en/developers](https://pathlyhq.com/en/developers).

## Resources

| Resource | Methods |
|---|---|
| Scenario (HTTP) | `createScenario`, `getScenario`, `updateScenario`, `deleteScenario`, `listScenarios`, `muteScenario` |
| Maintenance window | `createMaintenanceWindow`, `getMaintenanceWindow`, `deleteMaintenanceWindow`, `listMaintenanceWindows` |
| Webhook | `createWebhook`, `getWebhook`, `deleteWebhook`, `listWebhooks` |
| SLA target | `upsertSlaTarget` (PUT), `getSlaTarget`, `deleteSlaTarget`, `listSlaTargets` |

Browser journeys are created in the [Pathly](https://pathlyhq.com) console. This
SDK manages **HTTP** [Pathly monitoring](https://pathlyhq.com) scenarios only.

The webhook **secret** is returned once at creation. Store it in a vault. The
API never returns the destination URL on read, only `urlFingerprint`.

## Behavior

- Creates send an `Idempotency-Key` (auto-generated UUID unless you pass one).
- HTTP 429 and 5xx honour `Retry-After` (capped at 90 seconds, four attempts).
- HTTP 404 raises `NotFoundError` (`Pathly\is_not_found($err)` helper).
- Runtime: PHP 8.1+, `ext-json` only (no Guzzle required).

## Development

```bash
composer install
composer test
# or: vendor/bin/phpunit --coverage-text
```

Coverage is required at **100%**.

Examples: [examples/](./examples/). API reference:
[pathlyhq.com/en/developers](https://pathlyhq.com/en/developers)
· [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers).

## Related packages

| Package | Role |
|---|---|
| [pathly-sdk-go](https://github.com/pathlyhq/pathly-sdk-go) | Go |
| [pathly-sdk-typescript](https://github.com/pathlyhq/pathly-sdk-typescript) | `@pathlyhq/sdk` |
| [pathly-sdk-python](https://github.com/pathlyhq/pathly-sdk-python) | `pathly` |
| [pathly-terraform-provider](https://github.com/pathlyhq/pathly-terraform-provider) | Terraform |
| [Pathly](https://pathlyhq.com) | [Pathly monitoring](https://pathlyhq.com) product |

## About Pathly

[Pathly](https://pathlyhq.com) is synthetic monitoring for agencies and e-commerce: replay the customer journey, catch broken checkouts before your clients call, and keep evidence (screenshot, step, runbook) ready for the invoice. Product: [pathlyhq.com](https://pathlyhq.com) · Developers: [pathlyhq.com/en/developers](https://pathlyhq.com/en/developers) · Status & pricing: [pathlyhq.com/en/pricing](https://pathlyhq.com/en/pricing).

## Author

| | |
|---|---|
| **Company** | Pathly |
| **Author** | Simon Raynaud / keyral |

See [AUTHORS](AUTHORS). Homepage: [pathlyhq.com](https://pathlyhq.com).

## License

Apache-2.0
