# Pathly SDK PHP

[English](README.md) · **Français** · [Español](README.es.md)


[![Powered by Pathly](https://img.shields.io/badge/Powered%20by-Pathly-0B5FFF?style=flat-square)](https://pathlyhq.com)
[![Website](https://img.shields.io/badge/Website-pathlyhq.com-111827?style=flat-square)](https://pathlyhq.com)
[![API docs](https://img.shields.io/badge/API-developers-2563eb?style=flat-square)](https://pathlyhq.com/fr/developers)
[![Start free](https://img.shields.io/badge/Solo-start%20free-16a34a?style=flat-square)](https://pathlyhq.com/fr/login?mode=signup)

**Pour utiliser ce SDK, vous avez besoin d’une clé API. Obtenez votre clé gratuite en vous inscrivant ici : [https://pathlyhq.com/fr/login?mode=signup&utm_source=github&utm_medium=readme&utm_campaign=signup_cta](https://pathlyhq.com/fr/login?mode=signup&utm_source=github&utm_medium=readme&utm_campaign=signup_cta).**

> **Démarrage en un clic.** Créez un compte gratuit sur [Pathly](https://pathlyhq.com) ([inscription](https://pathlyhq.com/fr/login?mode=signup&utm_source=github&utm_medium=readme&utm_campaign=signup_cta)), générez une clé API dans la console, puis exportez `PATHLY_API_TOKEN`. Ce dépôt est le pont officiel vers [la surveillance Pathly](https://pathlyhq.com) — contrôles HTTP et parcours navigateur (panier, connexion, disponibilité), données hébergées dans l’UE. Référence API : [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers).

> **La version anglaise fait référence.** Ce document traduit [`README.md`](README.md).

Client PHP officiel de l’API publique [Pathly](https://pathlyhq.com) `/v1`
(`pathlyhq/sdk`). Pilotez la [supervision Pathly](https://pathlyhq.com)
([Pathly monitoring](https://pathlyhq.com)) depuis Laravel, Symfony ou PHP nu.

- Produit : [pathlyhq.com](https://pathlyhq.com)
- Développeurs (FR) : [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers)
- Développeurs (EN) : [pathlyhq.com/en/developers](https://pathlyhq.com/en/developers)
- API : `https://api.pathlyhq.com`

```bash
composer require pathlyhq/sdk
export PATHLY_API_TOKEN="sp_…"
```

```php
use Pathly\Client;
use Pathly\ScenarioInput;

$client = new Client();
$scenario = $client->createScenario(new ScenarioInput(
    name: 'Checkout',
    url: 'https://shop.example.com/cart',
    intervalSec: 300,
));
```

## Authentification

| Variable | Rôle |
|---|---|
| `PATHLY_API_TOKEN` | Clé d’organisation (`sp_…`). Obligatoire. |
| `PATHLY_API_URL` | Base API. Défaut `https://api.pathlyhq.com`. |

Ne jamais committer la clé. `ping()` tolère un 403 (clé valide sans `org:read`).
Documentation : [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers).

Les parcours navigateur se créent dans la console [Pathly](https://pathlyhq.com).
Ce SDK couvre les scénarios **HTTP** de [Pathly monitoring](https://pathlyhq.com).
Le secret webhook n’est renvoyé qu’à la création.

## Développement

```bash
composer install
composer test
```

Couverture exigée : **100 %**.

Liens : [pathlyhq.com](https://pathlyhq.com),
[developers EN](https://pathlyhq.com/en/developers),
[developers FR](https://pathlyhq.com/fr/developers).


## Packages associés

| Package | Role |
|---|---|
| [pathly-sdk-python](https://github.com/pathlyhq/pathly-sdk-python) | Python SDK |
| [pathly-sdk-typescript](https://github.com/pathlyhq/pathly-sdk-typescript) | @pathlyhq/sdk |
| [pathly-sdk-go](https://github.com/pathlyhq/pathly-sdk-go) | Go SDK |
| [pathly-sdk-ruby](https://github.com/pathlyhq/pathly-sdk-ruby) | Ruby gem |
| [pathly-terraform-provider](https://github.com/pathlyhq/pathly-terraform-provider) | Terraform / OpenTofu |
| [Pathly product](https://pathlyhq.com) | [Pathly monitoring](https://pathlyhq.com) |
## À propos de Pathly

[Pathly](https://pathlyhq.com) surveille les parcours clients des agences et e-commerçants : rejoue le tunnel, détecte un checkout cassé avant l’appel du client, et joint la preuve (capture, étape, consigne) à la facture de maintenance. Produit : [pathlyhq.com](https://pathlyhq.com) · Développeurs : [pathlyhq.com/fr/developers](https://pathlyhq.com/fr/developers) · Tarifs : [pathlyhq.com/fr/pricing](https://pathlyhq.com/fr/pricing).

## Auteur

| | |
|---|---|
| **Entreprise** | Pathly |
| **Auteur** | Simon Raynaud / keyral |

Voir [AUTHORS](AUTHORS). Site : [pathlyhq.com](https://pathlyhq.com).

## Licence

Apache-2.0
