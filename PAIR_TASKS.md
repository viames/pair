# Pair Framework — Larger tasks

Use this for work that crosses layers, carries migration or rollout risk, or
requires performance evidence. Routine local work follows [AGENTS.md](AGENTS.md)
and [SKILL.md](SKILL.md) without this additional guide.

## Shape the change

Before implementing:

1. Identify the affected layers and public surfaces, including downstream consumers.
2. Separate compatibility, schema/data, configuration and deployment risks.
3. Choose the smallest independently reviewable slice.
4. Define the evidence needed to verify that slice before editing.

## Verification by task

| Task | Evidence to gather |
| --- | --- |
| Bug affecting several layers | Reproduction, root cause, regression coverage and adjacent consumer behavior. |
| Framework feature | Owning layer, existing extension points, public contract, defaults and affected consumers. |
| Refactor | Unchanged public behavior, tests covering the moved responsibilities and a bounded diff. |
| Performance | Measured bottleneck, representative inputs, before/after results and query/allocation impact. |
| Migration or upgrade | Starting version, dry-run output, manual steps, schema/data effects, deployment order and rollback constraints. |
| Documentation reorganization | Topic ownership, valid links, current source signatures and usable examples. |

For performance work, distinguish database queries, loops and allocations before
choosing an optimization. Reuse cached results or collections when they solve the
measured problem. The v4 benchmark command is listed in [SKILL.md](SKILL.md).

## Migrations and rollout

Check downstream models, forms, APIs, jobs and exports. Prefer additive changes
or a deprecation path when an immediate removal would break consumers. Record
which code/configuration/schema changes must land first and whether rollback
requires data recovery rather than only reverting code.

Application upgrade commands and mode defaults belong in
[UPGRADE_V4.md](UPGRADE_V4.md); framework release steps belong in
[RELEASING.md](RELEASING.md).

## Documentation changes

Use the ownership table in [AGENTS.md](AGENTS.md#documentation-roles).
Keep new configuration keys, contract examples and migration instructions in the
same change as their implementation. For reorganizations, check links and
anchors before moving content; preserve existing reference pages where possible.

## Completion evidence

Use the completion format in [AGENTS.md](AGENTS.md). For larger tasks, also state
which affected layers were checked, remaining verification limits, and rollout or
rollback constraints. Match verification to the actual risk of the change.
