# Pathly PHP SDK

[English](README.md) · [Français](README.fr.md) · **Español**


[![Powered by Pathly](https://img.shields.io/badge/Powered%20by-Pathly-0B5FFF?style=flat-square)](https://pathlyhq.com)
[![Website](https://img.shields.io/badge/Website-pathlyhq.com-111827?style=flat-square)](https://pathlyhq.com)
[![API docs](https://img.shields.io/badge/API-developers-2563eb?style=flat-square)](https://pathlyhq.com/es/developers)
[![Start free](https://img.shields.io/badge/Solo-start%20free-16a34a?style=flat-square)](https://pathlyhq.com/es/login?mode=signup)

> **Empiece en un clic.** Cree una cuenta gratuita en [Pathly](https://pathlyhq.com) ([registro](https://pathlyhq.com/es/login?mode=signup)), genere una clave API en la consola y exporte `PATHLY_API_TOKEN`. Este repositorio es el puente oficial hacia [la monitorización Pathly](https://pathlyhq.com): comprobaciones HTTP y de navegador (carrito, login, disponibilidad), con datos en la UE. Referencia API: [pathlyhq.com/es/developers](https://pathlyhq.com/es/developers).

> **La versión en inglés es la referencia.** Este documento traduce [`README.md`](README.md).

Cliente PHP oficial de la API pública [Pathly](https://pathlyhq.com) `/v1`.
Automatice [Pathly monitoring](https://pathlyhq.com) desde Laravel, Symfony o
PHP puro: escenarios HTTP, ventanas de mantenimiento, webhooks firmados y
objetivos SLA.

- Producto: [pathlyhq.com](https://pathlyhq.com)
- Desarrolladores (ES): [pathlyhq.com/es/developers](https://pathlyhq.com/es/developers)
- Desarrolladores (EN): [pathlyhq.com/en/developers](https://pathlyhq.com/en/developers)
- Desarrolladores (FR): [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers)
- Host API: `https://api.pathlyhq.com`

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

## Por qué Pathly monitoring

[Pathly](https://pathlyhq.com) es monitorización sintética alojada en la UE. Este
paquete Composer (`pathlyhq/sdk`) es el gemelo PHP de los SDK TypeScript, Python
y Go documentados en [pathlyhq.com/es/developers](https://pathlyhq.com/es/developers),
[pathlyhq.com/en/developers](https://pathlyhq.com/en/developers) y
[pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers).

| Necesidad | Enlace |
|---|---|
| Visión del producto | [pathlyhq.com](https://pathlyhq.com) |
| Docs API y SDK (ES) | [pathlyhq.com/es/developers](https://pathlyhq.com/es/developers) |
| Docs API y SDK (EN) | [pathlyhq.com/en/developers](https://pathlyhq.com/en/developers) |
| Docs API y SDK (FR) | [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers) |
| Pathly monitoring | [pathlyhq.com](https://pathlyhq.com) |

## Autenticación

| Variable | Propósito |
|---|---|
| `PATHLY_API_TOKEN` | Clave de API de la organización (prefijo `sp_`). Obligatoria. |
| `PATHLY_API_URL` | Base de la API. Por defecto `https://api.pathlyhq.com`. |

Nunca codifique el token. Prefiera el entorno o un almacén de secretos.

`Client::ping()` llama a `GET /v1/usage` y **acepta HTTP 403**: la clave es
válida pero carece de `org:read`. Scopes: `scenarios:*`, `alerting:*`,
`maintenance:*`, `sla:*` — véase [pathlyhq.com/es/developers](https://pathlyhq.com/es/developers).

## Recursos

| Recurso | Métodos |
|---|---|
| Escenario (HTTP) | `createScenario`, `getScenario`, `updateScenario`, `deleteScenario`, `listScenarios`, `muteScenario` |
| Ventana de mantenimiento | `createMaintenanceWindow`, `getMaintenanceWindow`, `deleteMaintenanceWindow`, `listMaintenanceWindows` |
| Webhook | `createWebhook`, `getWebhook`, `deleteWebhook`, `listWebhooks` |
| Objetivo SLA | `upsertSlaTarget` (PUT), `getSlaTarget`, `deleteSlaTarget`, `listSlaTargets` |

Los recorridos de navegador se crean en la consola [Pathly](https://pathlyhq.com).
Este SDK gestiona solo escenarios **HTTP** de [Pathly monitoring](https://pathlyhq.com).

El **secret** del webhook se devuelve una sola vez en la creación. Guárdelo en
un vault. La API nunca devuelve la URL de destino en lectura, solo
`urlFingerprint`.

## Comportamiento

- Las creaciones envían un `Idempotency-Key` (UUID generado automáticamente salvo que pase uno).
- HTTP 429 y 5xx respetan `Retry-After` (tope de 90 segundos, cuatro intentos).
- HTTP 404 lanza `NotFoundError` (helper `Pathly\is_not_found($err)`).
- Runtime: PHP 8.1+, solo `ext-json` (no requiere Guzzle).

## Desarrollo

```bash
composer install
composer test
# or: vendor/bin/phpunit --coverage-text
```

Se exige cobertura al **100%**.

Ejemplos: [examples/](./examples/). Referencia API:
[pathlyhq.com/es/developers](https://pathlyhq.com/es/developers)
· [pathlyhq.com/en/developers](https://pathlyhq.com/en/developers)
· [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers).

## Paquetes relacionados

| Paquete | Rol |
|---|---|
| [pathly-sdk-go](https://github.com/pathlyhq/pathly-sdk-go) | Go |
| [pathly-sdk-typescript](https://github.com/pathlyhq/pathly-sdk-typescript) | `@pathlyhq/sdk` |
| [pathly-sdk-python](https://github.com/pathlyhq/pathly-sdk-python) | `pathly` |
| [pathly-terraform-provider](https://github.com/pathlyhq/pathly-terraform-provider) | Terraform |
| [Pathly](https://pathlyhq.com) | Producto [Pathly monitoring](https://pathlyhq.com) |

## Acerca de Pathly

[Pathly](https://pathlyhq.com) es monitorización sintética para agencias y e-commerce: reproduce el recorrido del cliente, detecta un checkout roto antes de la llamada, y deja la prueba (captura, paso, runbook) lista para la factura. Producto: [pathlyhq.com](https://pathlyhq.com) · Desarrolladores: [pathlyhq.com/es/developers](https://pathlyhq.com/es/developers) · Precios: [pathlyhq.com/es/pricing](https://pathlyhq.com/es/pricing).

## Autor

| | |
|---|---|
| **Empresa** | Pathly |
| **Autor** | Simon Raynaud / keyral |

Véase [AUTHORS](AUTHORS). Sitio: [pathlyhq.com](https://pathlyhq.com).

## Licencia

Apache-2.0
