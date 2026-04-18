# jardissupport/data

Entity hydration, change tracking, deep clone, field mapping, identity generation — all reflection-based, no ORM. Three service classes: `Hydration`, `Identity`, `FieldMapper` implement Contracts from `jardissupport/contract`.

## Usage essentials

- **Entity convention required:** `private array $__snapshot = [];` must exist on every hydrated entity — `getChanges()`, `toArray()`, and `aggregateToArray()` depend on it. Getter resolution order: `get{Name}()` > `is{Name}()` > `has{Name}()` > Reflection fallback; setter `set{Name}()` > Reflection + `TypeCaster`. Snake→Camel on column mapping (`user_name` → `userName`).
- **Value-Based Detection** separates DB columns from relations without a `#[Relation]` attribute: DB column = `null|scalar|DateTimeInterface|BackedEnum|plain array`, relation = objects or arrays of objects. `HydrateEntity` additionally checks the property type (array property + flat scalar array → hydrate as JSON column; array property + indexed array of assoc → skip as MANY-relation data). The `#[Relation]` attribute is NOT evaluated by this package — metadata only, for the Builder.
- **`hydrate()` vs `apply()`:** both set properties, but `hydrate()` merges into `__snapshot` (DB load, no changes), `apply()` leaves the snapshot untouched → `getChanges()` detects the modifications. Snapshot is **MERGE, not REPLACE** — multiple `hydrate()` calls accumulate. Snapshot holds only **scalars**: `DateTime` → `'Y-m-d H:i:s'`, `BackedEnum` → `->value`, no objects.
- **`toArray()` vs `aggregateToArray()`:** `toArray()` is flat (DB-column properties only, for `Repository::insert()`), `aggregateToArray()` serializes the full graph (recursive incl. relations, relation property names stay camelCase). Both read **keys from `__snapshot`** (real DB column names), **values from current properties**. Round-trip safe: `hydrate(['order_number' => 'X']) → aggregateToArray() → ['order_number' => 'X']`.
- **Identity generators:** `generateUuid7()` recommended (time-ordered, RFC 9562, monotonic counter for batch ordering and cross-instance collision avoidance); `generateUuid5()` deterministic (namespace + name, same input → same UUID); `generateUuid4()` for compatibility only; `generateNanoId(21, alphabet)` compact URL-safe. Use case: `identifier` = UUID v7 CHAR(36) public-facing, PK = autoincrement INT internal for FKs.
- **FieldMapper asymmetry:** `toColumns` is flat (Command-DTOs are flat), `fromColumns` recursive (Query responses are nested); `fromAggregate($array, $mapProvider, $entityName)` has a per-entity provider and **omits unmapped keys** (implicit PK/FK filtering). Empty-map shortcut: returns `$data` unchanged. Symmetry: `fromColumns(toColumns($data, $map), $map) === $data`. Layer rule: Domain defines entities, Infrastructure/Repository uses `Hydration`+`FieldMapper`, Application never directly.

## Full reference

https://docs.jardis.io/en/support/data
