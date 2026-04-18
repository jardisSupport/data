# Jardis Data

![Build Status](https://github.com/jardisSupport/data/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

Three focused services for entity data operations: **Hydration** transforms raw database rows into typed PHP objects with automatic change tracking. **FieldMapper** provides bidirectional array-key mapping between domain and database names. **Identity** generates unique identifiers (UUID v4/v5/v7, NanoID).

---

## Features

- **Two-Stage Hydration** — `hydrate()` for flat entities, `hydrateAggregate()` for nested aggregates with recursive object graphs
- **Programmatic Apply** — `apply()` sets properties like `hydrate()` but preserves the snapshot — changes are detected by `getChanges()`
- **Automatic Snapshots** — a snapshot is taken on every hydration call to establish the baseline for change detection. Snapshot keys are the original DB column names — used as source of truth for `toArray()`, `aggregateToArray()`, and `diff()`
- **Value-Based Detection** — `toArray()` and `diff()` automatically distinguish DB columns from relations by value type — no attributes required
- **Change Tracking** — field-level diff between current property values and the snapshot via `getChanges()`
- **Entity Cloning** — `clone()` for flat entity clone (DB columns), `cloneAggregate()` for deep clone including nested objects
- **Batch Loading** — `loadMultiple()` hydrates an array of database rows into an array of entities efficiently
- **Field Mapping** — `toColumns()` and `fromColumns()` rename array keys between domain and database names using an explicit map; `fromAggregate()` maps hierarchical arrays with per-entity mappings and filters unmapped keys
- **Identity Generation** — UUID v4 (random), v5 (deterministic), v7 (time-ordered with monotonic counter), and NanoID (compact, URL-safe)
- **PHP Attributes** — `#[Table]`, `#[Column]`, `#[PrimaryKey]`, `#[Aggregate]` for entity metadata
- **TypeCaster** — automatic DB-to-PHP type conversion (int, bool, float, DateTime, DateTimeImmutable, BackedEnum)

---

## Installation

```bash
composer require jardissupport/data
```

## Quick Start

```php
use JardisSupport\Data\Hydration;
use JardisSupport\Data\Identity;
use JardisSupport\Data\FieldMapper;

// --- Hydration ---
$hydration = new Hydration();

// Hydrate an entity from a database row
$user = new User();
$hydration->hydrate($user, [
    'id'         => 42,
    'first_name' => 'Jane',
    'last_name'  => 'Doe',
    'created_at' => '2024-06-01 10:00:00',
]);

// Apply programmatic changes (snapshot stays untouched)
$hydration->apply($user, ['first_name' => 'John']);

// Detect exactly what changed since hydration
$changes = $hydration->getChanges($user);
// ['first_name' => 'John']

// --- Identity ---
$identity = new Identity();

$id = $identity->generateUuid7();   // time-ordered, sortable
$id = $identity->generateUuid4();   // random
$id = $identity->generateUuid5($namespace, 'customer:12345');  // deterministic
$id = $identity->generateNanoId();  // compact, URL-safe (21 chars)

// --- FieldMapper ---
$mapper = new FieldMapper();

$map = ['customerName' => 'name', 'orderNumber' => 'order_number'];
$columns = $mapper->toColumns(['customerName' => 'Müller'], $map);
// ['name' => 'Müller']
$fields = $mapper->fromColumns(['name' => 'Müller'], $map);
// ['customerName' => 'Müller']
```

## Interfaces

All three services implement port interfaces from `jardissupport/contract`:

| Service | Interface | Purpose |
|---------|-----------|---------|
| `Hydration` | `JardisSupport\Contract\Data\HydrationInterface` | Entity hydration, snapshots, change tracking |
| `Identity` | `JardisSupport\Contract\Data\IdentityInterface` | UUID v4/v5/v7, NanoID generation |
| `FieldMapper` | `JardisSupport\Contract\Data\FieldMapperInterface` | Bidirectional array-key mapping |

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/support/data](https://docs.jardis.io/en/support/data)**

## License

This package is licensed under the [MIT License](LICENSE.md).

---

**[Jardis](https://jardis.io)** · [Documentation](https://docs.jardis.io) · [Headgent](https://headgent.com)

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## KI-gestützte Entwicklung

Dieses Package liefert einen Skill für Claude Code, Cursor, Continue und Aider mit. Installation im Konsumentenprojekt:

```bash
composer require --dev jardis/dev-skills
```

Mehr Details: <https://docs.jardis.io/skills>
<!-- END jardis/dev-skills README block -->
