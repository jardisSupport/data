# Jardis Data

[![License: PolyForm Noncommercial](https://img.shields.io/badge/License-PolyForm%20Noncommercial-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![Coverage](https://img.shields.io/badge/Coverage-91%25-brightgreen.svg)](https://github.com/jardisSupport/data)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](https://phpstan.org/)
[![PSR-4](https://img.shields.io/badge/PSR--4-Autoloader-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-12](https://img.shields.io/badge/PSR--12-Code%20Style-blue.svg)](https://www.php-fig.org/psr/psr-12/)

> Part of the **[Jardis Ecosystem](https://jardis.io)** — A modular DDD framework for PHP

Lightweight entity data service for PHP. Reflection-based hydration, snapshot-based change tracking, and entity utilities — all without heavy ORM overhead.

---

## Features

- **Entity Hydration** — Populate entities from database rows with automatic snake_case to camelCase conversion
- **Change Tracking** — Snapshot-based dirty checking to detect modified fields
- **Type Casting** — Automatic conversion of database strings to PHP types (int, bool, float, DateTime)
- **Deep Cloning** — Clone entities including nested objects and snapshots
- **Entity Diffing** — Compare two entities and get their differences
- **Aggregate Hydration** — Recursive hydration of nested objects and arrays
- **Batch Loading** — Load multiple rows into entity arrays efficiently

---

## Installation

```bash
composer require jardissupport/data
```

## Quick Start

```php
use JardisSupport\Data\DataService;

$dataService = new DataService();

// Hydrate entity from database row
$user = new User();
$dataService->hydrate($user, [
    'id' => 1,
    'first_name' => 'John',
    'last_name' => 'Doe',
    'created_at' => '2024-01-15 14:30:45',
]);

// Modify entity
$user->setFirstName('Jane');

// Detect changes
$changes = $dataService->getChanges($user);
// ['first_name' => 'Jane']

// Check if entity has changes
if ($dataService->hasChanges($user)) {
    // Save to database...
}
```

## API

### DataService Methods

| Method | Description |
|--------|-------------|
| `hydrate($entity, $data)` | Hydrate entity from database row |
| `hydrateFromArray($aggregate, $data)` | Hydrate aggregate with nested data |
| `getChanges($entity)` | Get changed fields with new values |
| `hasChanges($entity)` | Check if entity has modifications |
| `getChangedFields($entity)` | Get list of changed field names |
| `getSnapshot($entity)` | Get original values snapshot |
| `clone($entity)` | Deep clone entity with snapshot |
| `diff($entity1, $entity2)` | Compare two entities |
| `toArray($entity)` | Convert entity to associative array |
| `loadMultiple($template, $rows)` | Batch load rows into entities |
| `updateProperties($entity, $data)` | Update without changing snapshot |

## Entity Requirements

Entities should have:
- Private properties with optional getter/setter methods
- Optional `private array $__snapshot = []` for change tracking
- Optional `getSnapshot(): array` method for fast snapshot access

```php
class User
{
    private ?int $id = null;
    private ?string $firstName = null;
    private ?string $lastName = null;
    private array $__snapshot = [];

    public function getId(): ?int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): void { $this->firstName = $firstName; }
    public function getSnapshot(): array { return $this->__snapshot; }
}
```

## Attributes

PHP 8 attributes for entity metadata (optional, for use with other Jardis packages):

```php
use JardisSupport\Data\Attribute\Table;
use JardisSupport\Data\Attribute\Column;
use JardisSupport\Data\Attribute\PrimaryKey;

#[Table('users')]
class User
{
    #[PrimaryKey]
    #[Column('id')]
    private ?int $id = null;

    #[Column('first_name')]
    private ?string $firstName = null;
}
```

## Documentation

Full documentation, examples and API reference:

**→ [jardis.io/docs/support/data](https://jardis.io/docs/support/data)**

## Jardis Ecosystem

This package is part of the Jardis Ecosystem — a collection of modular, high-quality PHP packages designed for Domain-Driven Design.

| Category | Packages |
|----------|----------|
| **Core** | Domain, Kernel |
| **Adapter** | Cache, Logger, Messaging, DbConnection |
| **Support** | Data, DotEnv, DbQuery, Validation, Factory, ClassVersion, Workflow |
| **Tools** | DomainBuilder, DbSchema |

**→ [Explore all packages](https://jardis.io/docs)**

## License

This package is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE).

For commercial use, see [COMMERCIAL.md](COMMERCIAL.md).

---

**[Jardis Ecosystem](https://jardis.io)** by [Headgent Development](https://headgent.com)
