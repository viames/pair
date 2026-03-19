---
name: "Pair Framework"
slug: "pair"
version: "3.3"
description: "Entry skill for assistants working on the Pair framework repository."
tags:
  - php
  - pair
  - framework
  - review
language: "en"
---

# Pair Framework — SKILL

Use this file as the **compact quick-start companion** to `AGENTS.md`.

Read `AGENTS.md` first.
Then use this file to move quickly through the repository without losing the main workflow and output rules.

---

## Scope

Framework development in this repository:

- src/
- assets/
- translations/
- tests

The goal is to produce **safe, minimal, reviewable changes**.

Backward compatibility must be preserved unless explicitly requested otherwise.

---

## Core rules

These are the fast-path guardrails to keep in mind while applying `AGENTS.md` and `GEMINI.md`:

- Keep diffs minimal and focused on the requested task.
- Preserve backward compatibility unless the task explicitly requires otherwise.
- Reuse existing framework patterns before introducing new helpers or abstractions.
- Do not weaken validation, escaping, CSRF, session, or query safety.
- Prefer additive changes over rewrites.
- Do not introduce heavy dependencies or jQuery.
- Do not modify unrelated files.
- Always add docblocks/comments to PHP and JS functions.
- Add a short comment for non-trivial code paths.

Before coding:

1. Inspect `/src` to understand the relevant namespace.
2. Find the closest existing component solving a similar problem.
3. Read at least one full class in that component.
4. Check whether a utility already exists before creating a new helper.
5. Inspect `/tests` if present.
6. Verify public API usage before changing framework internals.

If a task is ambiguous, too broad, or may cause a backward-compatibility or architecture mistake, stop and ask for clarification before editing code.

---

## Fast path

Use this decision guide to avoid reading too much documentation:

- For any code change: read `AGENTS.md` and `GEMINI.md`.
- For architecture or framework internals: read `PAIR_ARCHITECTURE.md`.
- For implementation style and nearby code shape: read `PAIR_PATTERNS.md`.
- To avoid importing patterns from heavier frameworks: read `PAIR_CONTEXT.md`.
- For larger features, refactors, migrations, or performance work: read `PAIR_TASKS.md`.
- In Codex environments: read `CODEX.md`.

Default reading order for most tasks:

1. `AGENTS.md`
2. `SKILL.md`
3. `GEMINI.md`
4. One task-specific document from the list above

If the task is small and local, stop after step 3 unless you hit uncertainty.

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
- PHP and JS functions keep explicit comments/docblocks.

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
5. Add comments/docblocks to touched PHP and JS functions.

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

Read the following files only as needed after the primary entrypoint (`AGENTS.md`):

1. AGENTS.md  
   Primary entrypoint, workflow, review hygiene, document ownership, and expected final output.

2. GEMINI.md  
   Technical conventions, architecture summary, and coding guardrails.

3. CODEX.md  
   Additional guidelines for Codex agents.

4. PAIR_ARCHITECTURE.md  
   Framework architecture and design philosophy.

5. PAIR_CONTEXT.md  
   Strategic context for understanding what Pair is and what it is not.

6. PAIR_PATTERNS.md  
   Idiomatic coding patterns for Pair framework components.

7. PAIR_TASKS.md  
   Guidelines for handling complex tasks (features, bugfixes, refactors, migrations).

Read the minimum set needed for the task. More documentation is not automatically better if it delays a safe, pattern-consistent change.

## Conflict resolution order

If guidance conflicts:

1. Existing repository code
2. Tests
3. AGENTS.md
4. SKILL.md
5. GEMINI.md
6. CODEX.md
7. PAIR_ARCHITECTURE.md
8. PAIR_CONTEXT.md
9. PAIR_PATTERNS.md
10. PAIR_TASKS.md

---

## Minimal runbook

1. Read `AGENTS.md`.
2. Read `SKILL.md`.
3. Read `GEMINI.md`.
4. Read `CODEX.md` when working in Codex environments.
5. Read deeper documents only if the task needs architectural or pattern-specific context.
6. Explore the repository structure.
7. Implement the smallest viable change.
8. Provide output using the format defined in `AGENTS.md`.
