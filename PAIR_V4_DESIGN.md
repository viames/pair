## Pair v4 Design

### Problem Statement

Pair v3 delivered speed and convenience, but too much of that convenience was concentrated in `ActiveRecord`, controller/view bootstrapping, and implicit serialization. The result was a framework that stayed productive for small apps, yet accumulated structural coupling in exactly the places Pair v4 now needs to simplify.

The main Pair v3 problems that Pair v4 addresses are:

- `ActiveRecord` acting as persistence model, view payload, and API payload at the same time.
- HTML formatting and escaping living inside persistence objects.
- magic `__get()` / `__set()` and dynamic properties being part of the normal public API.
- partially initialized objects and `reload()`-style flows that can destabilize typed properties.
- API fallback paths that serialize raw records through `toArray()` instead of explicit contracts.
- controller, view, and framework state being coupled by convention and hidden mutation.

### Guiding Principles

- Keep Pair server-rendered first.
- Keep Pair small and fast.
- Prefer one explicit object over multiple clever fallback paths.
- Keep persistence and read contracts close, but not conflated.
- Reuse the same read contract for HTML and JSON whenever possible.
- Make migration explicit through tools, not by preserving every runtime shortcut.

### Core Decisions

#### 1. `ActiveRecord` stays, but only as persistence

Pair v4 keeps `ActiveRecord`, `Query`, and the ORM query path because they are still practical, lightweight, and performant. The breaking change is conceptual: ORM objects are no longer the natural public contract for views or API responses.

#### 2. Read contracts are explicit

Pair v4 introduces:

- `Pair\Data\ReadModel`
- `Pair\Data\MapsFromRecord`
- `Pair\Data\RecordMapper`

The normal path is now:

`ActiveRecord` -> explicit read model -> HTML or JSON

This keeps data mapping explicit, typed, and cheap at runtime.

#### 3. Request input is immutable

Pair v4 introduces `Pair\Http\Input` as a small immutable request object. It provides explicit merged access to query/body data plus typed accessors without introducing validation containers, reflection metadata, or request mutation layers.

#### 4. The controller path is response-oriented

Pair v4 introduces:

- `Pair\Web\Controller`
- `Pair\Web\PageResponse`
- `Pair\Http\JsonResponse`
- `Pair\Http\ResponseInterface`

The new controller flow is explicit:

- the controller orchestrates;
- the action returns a response object;
- the response renders a typed page state or JSON payload.

`Application` now understands response-returning actions directly. This creates a clean v4 path without requiring a heavy rewrite of the old runtime.

#### 5. CRUD no longer serializes raw records implicitly

`Pair\Api\CrudController` now requires one of these explicit contracts:

- `readModel`
- `resource`

The raw `ActiveRecord::toArray()` fallback is no longer the normal path. Legacy `resource` adapters are still supported as a migration bridge, but the preferred contract is now an explicit read model.

`SpecGenerator` follows the same rule for documentation: the OpenAPI response schema is generated from the configured `readModel` when present, so runtime behavior and published contract stay aligned.

#### 6. Migration gets a deliberate bridge

`Pair\Data\Payload` exists as a minimal readonly adapter for code that still needs an explicit object before a richer typed read model is introduced. It is intentionally small and documented as a migration bridge, not as the ideal end-state for application code.

### What Pair v4 Removes or De-emphasizes

- implicit record-as-response behavior
- implicit record-as-view-state behavior
- HTML helper logic inside the data contract path
- hidden controller/view lifecycle as the preferred application flow
- magic fallback serialization in CRUD

### What Pair v4 Introduces

- explicit read models
- immutable request input
- response objects for HTML and JSON
- typed page state through plain PHP objects
- upgrade tooling that rewrites the low-risk patterns automatically
- upgrade tooling that refuses unsafe controller rewrites and reports the remaining manual migration work explicitly
- upgrade tooling that seeds readonly page-state skeletons from legacy `View::assign()` contracts

### Trade-offs

- Mapping from record to read model is one extra explicit step. This is deliberate. It removes ambiguity, makes contracts reusable, and localizes presentation/API shape decisions.
- Legacy MVC modules are still present in the codebase because Pair needs a real migration path. They are no longer the recommended design center.
- The legacy MVC bridge now emits deprecation notices outside production so remaining Pair v3-style modules stay visible during migration work.
- `Payload` is not as strong as an app-specific readonly DTO. It exists to keep the migration tool practical and to avoid forcing raw arrays back into the public path.

### Why This Is Simpler

- fewer hidden rules at runtime
- fewer framework-owned mutations
- a clearer boundary between storage and output
- one response shape for HTML and JSON instead of special cases

Pair v4 does not add more concepts than Pair v3 had in practice. It replaces implicit concepts with smaller explicit ones.

### Why This Is Faster

- no heavy container
- no build step
- no reflection scanning on the hot path
- simple object creation and array export
- direct PHP layout rendering

The new v4 path adds explicit mapping cost where data leaves persistence, but avoids repeated magic lookups and fallback behavior. Benchmarks are defined in `scripts/benchmark-v4.php` so the common-path overhead stays visible and reviewable.
