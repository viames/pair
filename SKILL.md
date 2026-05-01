---
name: "Pair Framework"
slug: "pair"
version: "4.0-alpha"
description: "Entry skill for assistants working on the Pair v4 framework repository."
tags:
  - php
  - pair
  - framework
  - review
language: "en"
---

# Pair Framework v4 — SKILL

Use this file as the **compact quick-start companion** to `AGENTS.md`.

Read `AGENTS.md` first.
Then use this file to move quickly through the repository without losing the main workflow and output rules.
This quick-start assumes Pair v4 on the default branch. The authoritative version-scope note lives in `AGENTS.md`.

---

## Scope

Framework development in this repository:

- src/
- assets/
- translations/
- tests

The goal is to produce **safe, minimal, reviewable changes**.

---

## Quick Guardrails

Use this file for navigation, not as a second source of truth.

- Workflow, technical conventions, change hygiene, clarification rules, and final output format are owned by `AGENTS.md`.
- Reuse existing framework patterns before introducing helpers or abstractions.
- Prefer additive changes over rewrites.
- Use the smallest set of documents needed for the current task.

---

## Fast path

Use this decision guide to avoid reading too much documentation:

- For any code change: read `AGENTS.md`, then use this file for navigation.
- For architecture or framework internals: read `PAIR_ARCHITECTURE.md`.
- For implementation style and nearby code shape: read `PAIR_PATTERNS.md`.
- To avoid importing patterns from heavier frameworks: read `PAIR_CONTEXT.md`.
- For larger features, refactors, migrations, or performance work: read `PAIR_TASKS.md`.

Start with `AGENTS.md` and `SKILL.md`, then stop unless the task still needs deeper context.

---

## Official Docs Shortcuts

Use the official Pair wiki when the task touches one of these areas:

- Routing, URL params, ordering, and pagination state: `Router`
- Controller lifecycle, `_init()`, model/view loading, and PRG flows: `Controller`
- View responsibility, `render()`, assigned variables, and layout behavior: `View`
- Table-mapped entities, request population, persistence, and relations: `ActiveRecord`
- Control rendering, CSRF, generated forms, and select conventions: `Form`
- Progressive enhancement with `data-*` directives and lightweight helpers: `PairUI.js`
- Runtime configuration or new environment keys: `.env configuration`

Prefer these official docs over generic framework assumptions when behavior is unclear.

---

## Component map

Use this map to find the right area quickly before searching broadly:

- `src/Core`: application lifecycle, router, controller, model, view, logger, environment.
- `src/Orm`: ActiveRecord, query builder, collections, database access.
- `src/Api`: API controllers, request/response wrappers, middleware, throttling, OpenAPI support.
- `src/Html`: forms, widgets, menus, render helpers, form controls.
- `src/Helpers`: reusable framework helpers and utility-style classes.
- `src/Services`: integrations with external services and provider-specific logic.
- `src/Models`: framework models built on Pair ORM.
- `src/Push`: push notification delivery and subscription handling.
- `src/Exceptions`: framework exception types and error codes.
- `src/Traits`: small reusable behavior shared across classes.
- `assets`: PairUI and frontend-side JavaScript helpers.
- `translations`: localization resources.

If the task touches request handling, check `src/Core`, `src/Api`, and nearby helpers before creating new flow logic.
If the task touches UI or forms, check `src/Html` and `assets` before adding new frontend patterns.

---

## Search heuristics

When locating code, prefer targeted searches over broad grep-style scans:

- Search by namespace or class name first.
- Search by method name only after identifying the likely component area.
- Search for one nearby concrete example before designing a new approach.
- For forms and UI, search in `src/Html` and `assets` together.
- For API flows, search in `src/Api` first, then `src/Core`, then related models/helpers.
- For ORM behavior, search in `src/Orm` and then in the model using that behavior.

Useful starting points:

- request or validation logic: `src/Api/Request.php`, `src/Core/Controller.php`
- form rendering: `src/Html/Form.php`, `src/Html/FormControl.php`
- frontend progressive enhancement: `assets/PairUI.js`
- application lifecycle: `src/Core/Application.php`, `src/Core/Router.php`

If a search returns too many matches, narrow by namespace or directory before reading more files.

---

## Accuracy checklist

Before editing, confirm these high-risk assumptions:

- The change follows an existing Pair pattern nearby.
- The public API remains compatible unless the task explicitly says otherwise.
- ORM helpers are not being bypassed without a concrete reason.
- Server-rendered behavior is preserved unless the task explicitly moves logic client-side.
- Security behavior is not weakened.

---

## Task recipes

Use these default flows for common tasks.

### Small bug fix

1. Reproduce or isolate the failing behavior.
2. Find the closest class handling the same responsibility.
3. Implement the smallest safe fix.
4. Check that adjacent behavior did not change.
5. Update or add tests if they exist for that area.

### Small feature

1. Identify the correct layer: controller, model, helper, service, or frontend helper.
2. Reuse an existing pattern in the same namespace.
3. Prefer additive behavior over changing an existing public contract.
4. Keep frontend behavior progressive and lightweight.

### Local refactor

1. Confirm the goal is clarity, duplication removal, or maintainability.
2. Keep external behavior unchanged.
3. Limit the refactor to the smallest useful scope.
4. Do not expand into nearby unrelated cleanup.
5. Verify public APIs and tests remain valid.

### Documentation update

1. Update only the document that owns the topic.
2. Keep examples aligned with current Pair behavior.
3. Avoid copying rules across multiple files.
4. Preserve the distinction between entrypoint, workflow, and technical reference.

---

## Capabilities of the Pair framework

The framework provides:

- MVC routing system
- ActiveRecord ORM
- migration tools
- push notification system
- PWA helpers
- PairUI frontend helper library
- optional integrations with external services

Agents should reuse existing framework components instead of reimplementing similar functionality.

---

## Documentation map

Document ownership and conflict resolution are defined in `AGENTS.md`.

Use deeper docs only for their narrow role:

- `AGENTS.md`: authoritative workflow, technical conventions, and output contract
- `PAIR_ARCHITECTURE.md`: framework internals and request flow
- `PAIR_CONTEXT.md`: anti-import guardrails
- `PAIR_PATTERNS.md`: idiomatic code shape and examples
- `PAIR_TASKS.md`: extra guidance for broader or higher-risk tasks

Read the minimum set needed for the task. More documentation is not automatically better if it delays a safe, pattern-consistent change.
