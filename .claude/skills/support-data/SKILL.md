---
name: support-data
description: Entity hydration, change tracking, field mapping, UUID/NanoID generation. Use for Hydration, Identity, FieldMapper.
user-invocable: false
---

# DATA_COMPONENT_SKILL
> jardissupport/data v2.0.0 | NS: `JardisSupport\Data` | PHP 8.2+

## ARCHITECTURE
```
Hydration (implements HydrationInterface)
  Handlers: HydrateEntity · HydrateAggregate · DetectChanges · CloneEntity · CloneAggregate
            DiffEntities · EntityToArray · AggregateToArray · LoadMultiple
  Helpers:  SetPropertyValue · GetPropertyValue · ColumnNameToPropertyName · PropertyNameToColumnName
            SetSnapshot · GetSnapshot · TypeCaster · ToSnapshotValue

Identity (implements IdentityInterface)
  Handlers: GenerateUuid4 · GenerateUuid5 · GenerateUuid7 · GenerateNanoId

FieldMapper (implements FieldMapperInterface)
  Methods:  toColumns · fromColumns · fromAggregate
```
**Entity requirement:** `private array $__snapshot = [];`. Getter priority: `get*()` > `is*()` > `has*()` > reflection.

## API

### Hydration
```php
$h = new Hydration();
$h->hydrate(object $entity, array $row): object           // DB row → entity + snapshot (merge)
$h->apply(object $entity, array $data): object            // set properties, NO snapshot update
$h->hydrateAggregate(object $agg, array $data): object    // recursive nested hydration
$h->loadMultiple(object $template, array $rows): array    // clone template per row

$h->getChanges(object $entity): array      // ['col_name' => scalar] — apply-detected changes
$h->getChangedFields(object $entity): array // ['col_name', ...]
$h->getSnapshot(object $entity): array     // original scalar snapshot from hydrate()

$h->clone(object $entity): object                   // flat clone (DB-cols + snapshot)
$h->cloneAggregate(object $agg): object             // deep clone (full graph + snapshots)
$h->diff(object $e1, object $e2): array             // ['propName' => value-from-e2]
$h->toArray(object $entity): array                  // flat [col_name => scalar]
$h->aggregateToArray(object $agg): array            // nested; keys from __snapshot, relations keep camelCase propName
```

### Identity
```php
$id = new Identity();
$id->generateUuid7(): string                          // time-ordered, RFC 9562 (recommended)
$id->generateUuid4(): string                          // random, RFC 4122
$id->generateUuid5(string $namespace, string $name): string  // deterministic SHA-1
$id->generateNanoId(int $size = 21, string $alphabet = '...'): string  // URL-safe
```

### FieldMapper
```php
$fm = new FieldMapper();
// Map format: [domainName => columnName]
$fm->toColumns(array $data, array $map): array    // domain → DB, flat only, unmapped keys pass through
$fm->fromColumns(array $data, array $map): array  // DB → domain, recursive, unmapped keys pass through
$fm->fromAggregate(array $data, callable $mapProvider, string $entity): array
// mapProvider: fn(string $entityName): array — per-entity map
// Unmapped scalar keys omitted. list arrays: each element mapped individually.
// Empty map: returns $data unchanged (no iteration)
// Symmetry: fromColumns(toColumns($data, $map), $map) === $data
```

## HYDRATE vs APPLY
| Aspect | `hydrate()` | `apply()` |
|--------|-------------|-----------|
| Updates snapshot | Yes (merge) | No |
| `getChanges()` after | Empty | Contains changed fields |
| Typical caller | Repository (DB load) | AggregateHandler (Set/Add) |
| Type Casting | Yes | Yes |

Both use `HydrateEntity` internally — `apply()` passes `updateSnapshot: false`.

## VALUE-BASED DETECTION (no `#[Relation]` attribute needed)
- **DB column:** `null`, scalar, `DateTimeInterface`, `BackedEnum`, plain scalar arrays
- **Relation:** objects, arrays of objects
- `array` property + flat scalar array → hydrate (JSON column)
- `array` property + indexed array of assoc arrays → skip (MANY-relation)
- Class property + array value → skip (ONE-relation)

## TYPE CASTING
`TypeCaster`: `string` → `DateTime`|`DateTimeImmutable` (formats: `Y-m-d H:i:s` | `Y-m-d` | `H:i:s`) · `int` · `bool` (`(bool)((int)$v)`) · `float` · `BackedEnum::from($v)`.
- Date-only `Y-m-d`: time reset to `00:00:00`
- Time-only `H:i:s`: date reset to `1970-01-01`
- Null + untyped properties: unchanged

**Snapshot scalars:** `int`/`float`/`bool`/`string` as-is · `DateTimeInterface` → `'Y-m-d H:i:s'` · `BackedEnum` → `->value`. No objects in snapshot.

**toArray / aggregateToArray output:** `DateTime` → `'Y-m-d H:i:s'` · `BackedEnum` → `->value`.

## DIFF BEHAVIOR
- DB-column comparison only (relations skipped value-based)
- `DateTime`/`DateTimeImmutable`: cross-type by timestamp
- `BackedEnum`: `===` · Scalars: `===` · Arrays: `===`
- Uninitialized props (both): skipped
- Different classes: `InvalidArgumentException`
- Returns: `[propertyName => value-from-entity2]`

## AGGREGATE HYDRATION TYPE RESOLUTION
MANY relations: adder `add{Singular}()` param type → `@var ClassName[]` / `@var array<int, ClassName>` docblock.
ONE relations: property type hint. Numeric array keys (collection indices) skipped.

## UUID DETAILS
- **v7** (recommended): 48-bit ms timestamp + 12-bit monotonic counter + 62-bit random. Counter starts at random offset per ms (cross-instance collision avoidance). Lexicographically sortable.
- **v5**: deterministic — same namespace+name = same UUID. Use for stable identities from business data.
- **NanoID**: default 21 chars ~126 bits entropy, configurable alphabet+length.
- **Convention:** Aggregate `identifier` = UUID v7 `CHAR(36)` UNIQUE (external/API). PK = autoincrement INT (internal/FK).

## ATTRIBUTES
```php
use JardisSupport\Data\Attribute\{Table, Column, PrimaryKey, ForeignKey, Relation, Aggregate};

#[Table(name: 'users', schema: 'public')]
#[Aggregate(name: 'UserAggregate', root: true)]
class User {
    #[PrimaryKey(autoIncrement: true)]
    #[Column(name: 'id', type: 'integer')]
    private int $id;

    #[Column(name: 'email', type: 'varchar', length: 255, nullable: false, unique: true)]
    private string $email;

    #[Column(name: 'score', type: 'decimal', precision: 10, scale: 2, default: 0.0)]
    private float $score;

    #[ForeignKey(referencedTable: 'companies', referencedColumn: 'id', onDelete: 'CASCADE')]
    #[Column(name: 'company_id', type: 'integer', nullable: true)]
    private ?int $companyId;

    private array $__snapshot = [];
}
```
| Attribute | Target | Params |
|-----------|--------|--------|
| `#[Table]` | class | `name`, `?schema` |
| `#[Column]` | property | `name`, `type`, `?length`, `?precision`, `?scale`, `nullable`, `?default`, `unique` |
| `#[PrimaryKey]` | property | `autoIncrement` |
| `#[ForeignKey]` | property | `referencedTable`, `referencedColumn`, `onUpdate`, `onDelete` |
| `#[Relation]` | property | `type` ('one'\|'many'), `target` — **NOT read by handlers**, metadata only for Builder |
| `#[Aggregate]` | class | `name`, `root` (bool) |

## TOARRAY vs AGGREGATETOARRAY
| Method | Scope | Keys | Values |
|--------|-------|------|--------|
| `toArray()` | DB-cols only (no relations) | from `__snapshot` (column names) | current property state |
| `aggregateToArray()` | Full graph incl. relations | columns from `__snapshot`, relations = camelCase propName | current property state |

Use `toArray()` for `Repository::insert()`. Use `aggregateToArray()` for API/transport serialization.

## CLONE vs CLONEAGGREGATE
| Method | Scope |
|--------|-------|
| `clone()` | Flat — DB-col properties + snapshot. Relations stay default. |
| `cloneAggregate()` | Deep — recursive full object graph + snapshots per level. |

## LAYER
- **Domain:** define entity classes with attributes + `$__snapshot`
- **Infrastructure/Repository:** `Hydration` (hydration + change detection), `FieldMapper` (key mapping)
- **Application:** NEVER imports `Hydration`/`FieldMapper` directly — via Repository
- **Identity:** injectable into Domain or Application (not infrastructure-only)
