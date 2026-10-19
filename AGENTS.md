# AGENTS.md — Pair Framework v4

Primary entrypoint for automated agents working on this repository. Read this
first, then [SKILL.md](SKILL.md) for the source map and local commands. Do not add
agent-specific shim files unless they contain genuinely non-duplicated
operational requirements.

The default branch implements and documents **Pair v4**, the stable line used by
the maintainer's applications. The `v3` branch is the previous maintenance line;
v4 guidance may not apply to it.

## Mission

Make small, safe, reviewable changes consistent with existing Pair architecture.
Preserve backward compatibility unless explicitly asked otherwise. When guidance
conflicts, favor the smallest safe change aligned with existing patterns and the
precedence below.

## Workflow

Before making any change:

1. Inspect `src/` to understand the namespace layout.
2. Find the closest component solving a similar problem and read at least one
   full class in it.
3. Check for existing utilities before creating helpers or architectural patterns.
4. Inspect `tests/` to understand expected behavior.
5. Verify public API usage before modifying framework classes.

Then:

1. Implement the smallest change in the owning component, following nearby code
   and the conventions below.
2. Add or update deterministic tests when behavior changes. Never modify tests
   merely to make a broken implementation pass.
3. Run relevant checks and verify compatibility, security and performance. If
   tests are absent, use the closest manual/local validation and report that limit.
4. Update the owning documentation when commands, configuration or public
   behavior change; public framework changes require the relevant sibling
   `pair.wiki` pages, especially API and `.env` references.
5. Review the diff for unrelated files, dependencies and secrets, then report
   the result using the completion format below.

For an ambiguous or overly broad request, or one risking an architectural or
compatibility mistake, ask for clarification before changing code.

## Documentation roles

Each topic has one owning document. Link to it instead of copying rules, unless
repetition prevents a likely mistake.

| Document | Owns |
| --- | --- |
| [AGENTS.md](AGENTS.md) | Workflow, required exploration, conventions, change hygiene, completion format, document ownership and precedence. |
| [SKILL.md](SKILL.md) | Compact quick start, component map, search heuristics and local commands. |
| [PAIR_ARCHITECTURE.md](PAIR_ARCHITECTURE.md) | Framework internals and request lifecycle. |
| [PAIR_PATTERNS.md](PAIR_PATTERNS.md) | Idiomatic implementation examples. |
| [PAIR_CONTEXT.md](PAIR_CONTEXT.md) | Strategic context and avoiding unsuitable patterns from other frameworks. |
| [PAIR_TASKS.md](PAIR_TASKS.md) | Work crossing layers, performance evidence, migrations and rollout risk. |
| [README.md](README.md) | Public introduction, installation and guide selection. |
| [Pair wiki](https://github.com/viames/pair/wiki) | Application workflows, class/API references and environment configuration. |
| [UPGRADE_V4.md](UPGRADE_V4.md) | Application upgrade commands, mode defaults and manual migration steps. |
| [PAIR_V4_DESIGN.md](PAIR_V4_DESIGN.md) | v4 design rationale; verify proposals against current source. |
| [RELEASING.md](RELEASING.md) | Branch and release process. |
| [Composer dist contents](docs/COMPOSER_DIST_CONTENTS.md) | Files shipped to consuming applications. |

### Reading order

Read `AGENTS.md`, then `SKILL.md`, then one task-specific document only if needed.
For small local tasks, stop after the first two unless uncertainty remains.

### Conflict resolution

Use this precedence: existing code → tests → `AGENTS.md` → `SKILL.md` →
`PAIR_ARCHITECTURE.md` → `PAIR_CONTEXT.md` → `PAIR_PATTERNS.md` → `PAIR_TASKS.md`.

## Technical conventions

Nearby code may establish a more specific local pattern.

### Runtime and dependencies

- Target PHP 8.4.1 and 8.5.
- Required Composer extensions: `curl`, `intl`, `json`, `mbstring`, `PDO` and
  `pdo_mysql` for the default MySQL driver.
- Keep feature-specific extensions optional unless the feature requires them:
  for example `fileinfo`, `openssl`, `redis` and `xdebug`.
- Do not add runtime dependencies for simple framework behavior. Any new
  dependency needs clear justification, especially heavy packages.

### PHP

- PSR-4 classes under `Pair\`, one class per file matching its name.
- Tabs for indentation; opening braces on the same line; no short PHP tags.
- `CamelCase` classes, `camelCase` variables/methods, `UPPER_SNAKE_CASE` constants;
  suffix interfaces with `Interface`.
- Short English function/test names: one word when clear, two if ambiguous,
  three only in exceptional cases.
- Readable multi-line control flow; prefer `and` / `or`, with parentheses where
  precedence could be unclear.

### Frontend and framework

- Vanilla JavaScript and PairUI directives: lightweight, build-free, progressive
  enhancement. No jQuery or heavy frontend frameworks.
- Preserve server-rendered behavior unless explicitly asked to move it client-side.
- Use ActiveRecord relation/parent helpers and collections instead of manual
  SQL joins when they express the intent.
- Default routes: `/<module>/<action>/<params>`, resolving `<action>Action()`.
- New v4 web modules prefer explicit `Pair\Web\Controller` responses.
  `Pair\Core\Controller` and legacy views remain migration bridges.
- Layouts stay mostly HTML. `declare(strict_types=1)` and hints such as
  `/** @var FooPageState $state */` are not runtime contracts.

### Security and performance

- Secure defaults for input validation, output escaping, CSRF, sessions and queries.
- Avoid N+1 queries, repeated database calls, heavy loops and unnecessary allocations.
- Reuse cached results and existing collections where appropriate.

## Change hygiene

- Keep diffs minimal and limited to the task; no unsolicited or large unrelated refactors.
- Reuse existing utilities and architecture; do not create parallel subsystems.
- Do not change public APIs or these coding conventions without an explicit request.
- Never log sensitive data or commit credentials/secrets.
- Add comments/docblocks to touched PHP and JS functions and short comments for
  introduced or changed non-trivial paths. Write all framework comments/docblocks
  in English.
- Comments/docblocks are non-authoritative: removing them must not change runtime
  behavior. Required behavior, configuration and migration-critical instructions
  must also exist outside comments/docblocks.

## Completion format

Every proposal or final report must cover:

- What changed and why.
- Files modified.
- Risk and rollback.
- Verification performed and manual test steps, including any limits.
- Optional follow-up improvements when useful.

For pull requests, use:

```text
What / Why:
- ...

Files:
- ...

Risk and rollback:
- ...

Manual test:
- [ ] ...
```
