# AGENTS.md — Pair Framework v4

Guide for automated agents (LLMs, code assistants, review bots) working on this repository.

This is the **primary entrypoint** for AI agents working on this repository.
Read this file first.
Do not add agent-specific shim files unless they contain genuinely non-duplicated operational requirements.

This file owns the high-level operating contract for agents:

- workflow
- repository exploration requirements
- technical conventions
- change hygiene
- review expectations
- completion format
- document ownership and reading order

After this file:

- read `SKILL.md` for the compact quick-start and document map
- read task-specific documents only when needed

> Version scope
>
> The default branch of this repository documents and implements Pair v4 in alpha state. Use the `v3` branch for the stable Pair v3 line. Guidance in this branch targets Pair v4 and may not apply, or may apply only partially, to products based on earlier major versions.

---

## Mission

Help improve the **Pair v4 framework** with **small, safe, reviewable changes** that respect existing architecture and conventions.

When instructions conflict, prefer the smallest safe change aligned with existing Pair patterns and backward compatibility.

---

## Repository exploration (required before coding)

Before making any change:

1. Inspect the `/src` directory to understand namespace layout.
2. Identify the closest existing component solving a similar problem.
3. Read at least one full class in that component to understand conventions.
4. Check if utilities already exist before creating helpers.
5. Inspect `/tests` (if present) to understand expected behavior.
6. Verify public API usage before modifying framework classes.

Never introduce a new architectural pattern if an existing one already solves the problem.

If `/tests` is not present, verify behavior through the closest available manual or local validation path and mention that in the final report.

---

## Workflow (recommended)

1. Locate the relevant component in `/src`.
2. Read existing patterns in that component or namespace.
3. Implement the **smallest possible change** that solves the task.
4. Verify that backward compatibility is preserved (unless explicitly requested otherwise).
5. Add or update tests if the behavior changes.
6. Keep the implementation consistent with Pair conventions defined in this file and nearby code.
7. If public framework behavior changes, update the relevant pages in the sibling `pair.wiki` docs, especially for `src/Api` and `.env` configuration changes.

Use deeper documents only when needed:

- `PAIR_PATTERNS.md` for idiomatic implementation details
- `PAIR_ARCHITECTURE.md` for framework internals
- `PAIR_TASKS.md` for larger or riskier tasks
- `PAIR_CONTEXT.md` when there is a risk of importing patterns from other frameworks

## Documentation roles

Use each file for its owning responsibility:

- `AGENTS.md`: primary entrypoint, workflow, mandatory exploration, technical conventions, output contract, document ownership
- `SKILL.md`: compact quick-start, search heuristics, component map, minimal runbook
- `PAIR_ARCHITECTURE.md`: framework internals and design reasoning
- `PAIR_PATTERNS.md`: implementation patterns and nearby code shape
- `PAIR_CONTEXT.md`: strategic context and anti-pattern avoidance
- `PAIR_TASKS.md`: guidance for larger, riskier, or multi-step tasks

Do not duplicate the same rule in multiple files unless the repetition prevents a likely mistake.

## Reading order

Default reading order for most tasks:

1. `AGENTS.md`
2. `SKILL.md`
3. One deeper task-specific document only if uncertainty remains

If the task is small and local, stop after step 2 unless there is ambiguity or architectural risk.

## Conflict resolution

If guidance conflicts, use this order:

1. Existing repository code
2. Tests
3. `AGENTS.md`
4. `SKILL.md`
5. `PAIR_ARCHITECTURE.md`
6. `PAIR_CONTEXT.md`
7. `PAIR_PATTERNS.md`
8. `PAIR_TASKS.md`

---

## Technical conventions

Use these defaults unless nearby code establishes a more specific local pattern.

### Runtime and dependencies

- Target PHP 8.3, 8.4, and 8.5.
- Required Composer extensions are `curl`, `intl`, `json`, `mbstring`, `PDO`, and `pdo_mysql` for the default MySQL driver.
- Feature-specific extensions should remain optional unless the feature already requires them, for example `fileinfo`, `openssl`, `redis`, or `xdebug`.
- Do not introduce new runtime dependencies for simple framework behavior.

### PHP conventions

- Use PSR-4 classes under the `Pair\` namespace.
- Use tabs for indentation.
- Keep one class per file and match filename to class name.
- Use `CamelCase` for classes, `camelCase` for variables and methods, `UPPER_SNAKE_CASE` for constants, and suffix interfaces with `Interface`.
- Use short English names for functions and tests: one word when clear, two if ambiguous, three only for exceptional cases.
- Put opening braces on the same line.
- Prefer readable multi-line control flow over compact clever code.
- Prefer `and` / `or`; use parentheses when precedence could be unclear.
- Avoid short PHP tags.

### JavaScript and frontend conventions

- Prefer vanilla JavaScript and PairUI directives.
- Keep frontend behavior lightweight, build-free, and progressively enhanced.
- Do not introduce jQuery or heavy frontend frameworks.
- Keep server-rendered behavior intact unless the task explicitly moves logic client-side.

### Framework conventions

- Pair uses ActiveRecord. Prefer ORM relation helpers, parent relation helpers, and collections over manual SQL joins when they express the intent.
- Default routing is `/<module>/<action>/<params>`, with actions resolved as `<action>Action()`.
- New Pair v4 web work should prefer explicit `Pair\Web\Controller` responses; `Pair\Core\Controller` and legacy views remain migration bridges.
- Layout files should stay mostly HTML; editor/static-analysis hints such as `declare(strict_types=1)` or `/** @var FooPageState $state */` are not runtime contracts.

### Security, tests, and performance

- Keep framework code secure by default, especially input validation, output escaping, CSRF, sessions, and database queries.
- When tests exist, update or add deterministic tests for changed behavior.
- Never modify tests merely to make a broken implementation pass.
- Avoid N+1 queries, repeated database calls, heavy loops, and unnecessary allocations.
- Prefer cached results and existing collections where they fit the existing code.

## Change hygiene

Agents must follow these rules:

- Keep diffs minimal.
- Avoid unsolicited refactoring.
- Preserve backward compatibility by default.
- Do not introduce heavy dependencies for simple tasks.
- Do not introduce jQuery.
- Do not log sensitive data.
- Do not commit credentials or secrets.
- Do not modify unrelated files.
- Add comments/docblocks to PHP and JS functions that are touched.
- Add a short comment for non-trivial code paths that are introduced or changed.
- Comments and docblocks in Pair framework code must always be written in English.
- Comments and docblocks are non-authoritative documentation only; removing them must never change runtime behavior.
- Do not encode required behavior, configuration, or migration-critical instructions exclusively in comments or docblocks.
- If a request is ambiguous, too broad, or risks an architectural or backward-compatibility mistake, ask for clarification before changing code.

---

## Avoid

Agents must avoid:

- Large refactors unrelated to the task
- Rewriting existing utilities with custom implementations
- Introducing new dependencies without clear justification
- Changing public APIs without explicit request
- Changing coding conventions defined in this file
- Creating parallel architectures for existing components

---

## Output expectations (PR / assistant response)

Every change proposal or pull request should include:

1. **What changed**
2. **Why the change is needed**
3. **Files modified**
4. **Risk assessment**
5. **Manual test steps**
6. **Possible follow‑up improvements (optional)**

### PR description template

What / Why:
- ...

Files:
- ...

Risk and rollback:
- ...

Manual test:
- [ ] ...

---

## Agent checklist

Before completing the task:

- [ ] I read `AGENTS.md` first and used it as the primary guide
- [ ] I followed conventions defined in `AGENTS.md`
- [ ] I applied the quick-start guardrails defined in `SKILL.md`
- [ ] I explored the repository before coding
- [ ] I preserved backward compatibility
- [ ] I did not introduce performance regressions
- [ ] I did not introduce new dependencies unnecessarily
- [ ] I did not commit secrets
- [ ] I updated or added tests when behavior changed
- [ ] I updated `pair.wiki` docs when public framework behavior changed
- [ ] I documented manual test steps if applicable
