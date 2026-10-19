# Pair Framework — Context

Read this when assumptions from another framework could lead to an unsuitable
solution. [AGENTS.md](AGENTS.md) owns implementation rules;
[PAIR_ARCHITECTURE.md](PAIR_ARCHITECTURE.md) explains the runtime.

## Design intent

Pair serves small and medium PHP/MySQL applications that need predictable
structure and low operational overhead. Its core choices are server-rendered
MVC, ActiveRecord persistence, explicit v4 responses, progressive enhancement,
and a small dependency surface.

The useful unit of change is usually an existing controller, model, helper or
service. Additional layers need a concrete benefit for the task being solved.

## Assumptions to check

| Imported assumption | Pair starting point |
| --- | --- |
| A service container is needed for ordinary behavior | Direct framework utilities and existing runtime adapter registrations. |
| Every table needs a repository layer | ActiveRecord, model methods, relation helpers and collections. |
| Every interaction needs an API and client-side state framework | Server-rendered HTML, PairUI and existing progressive region responses. |
| A controller implicitly loads its model and view | Explicit `Pair\Web\Controller` actions return responses; implicit loading belongs to the legacy bridge. |
| A persistence record is also the public response schema | Explicit page state and read contracts containing the intended fields. |
| A small feature needs a generic configuration or event subsystem | The owning class's existing hooks and configuration path. |
| A familiar pattern from another framework is automatically suitable | A nearby Pair implementation and its tests. |

These are starting points for evaluation, not grounds to rewrite established
code. Pair already has interfaces, adapters, middleware and optional integrations;
use them when they own the responsibility in question.

## Deciding whether a new abstraction is justified

Before adding a layer, identify the concrete duplication, runtime constraint or
public contract that existing components cannot express. Compare the proposed
solution with the nearest Pair implementation and account for compatibility,
query cost and review scope.

If the task can be completed within the owning component, prefer that path.
For examples, use [PAIR_PATTERNS.md](PAIR_PATTERNS.md); for work spanning several
layers, use [PAIR_TASKS.md](PAIR_TASKS.md).
