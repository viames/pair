# AGENTS.md — Pair Framework

Guide for automated agents (LLMs, code assistants, review bots) working on this repository.

This is the **primary entrypoint** for AI agents working on this repository.
Read this file first.

This file owns the high-level operating contract for agents:

- workflow
- repository exploration requirements
- change hygiene
- review expectations
- completion format
- document ownership and reading order

After this file:

- read `SKILL.md` for the compact quick-start and document map
- read `GEMINI.md` for technical conventions and framework-level guardrails
- read task-specific documents only when needed

---

## Mission

Help improve the **Pair framework** with **small, safe, reviewable changes** that respect existing architecture and conventions.

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

For coding conventions and technical guardrails, defer to `GEMINI.md`.

If `/tests` is not present, verify behavior through the closest available manual or local validation path and mention that in the final report.

---

## Workflow (recommended)

1. Locate the relevant component in `/src`.
2. Read existing patterns in that component or namespace.
3. Implement the **smallest possible change** that solves the task.
4. Verify that backward compatibility is preserved (unless explicitly requested otherwise).
5. Add or update tests if the behavior changes.
6. Keep the implementation consistent with Pair conventions defined in `GEMINI.md`.
7. If public framework behavior changes, update the relevant pages in the sibling `pair.wiki` docs, especially for `src/Api` and `.env` configuration changes.

Use deeper documents only when needed:

- `PAIR_PATTERNS.md` for idiomatic implementation details
- `PAIR_ARCHITECTURE.md` for framework internals
- `PAIR_TASKS.md` for larger or riskier tasks
- `PAIR_CONTEXT.md` when there is a risk of importing patterns from other frameworks

## Documentation roles

Use each file for its owning responsibility:

- `AGENTS.md`: primary entrypoint, workflow, mandatory exploration, output contract, document ownership
- `SKILL.md`: compact quick-start, search heuristics, component map, minimal runbook
- `GEMINI.md`: technical conventions, architecture summary, coding standards, security and testing guidance
- `CODEX.md`: Codex-specific operating notes that must stay compatible with this file
- `CLAUDE.md`: Claude-specific reading order that must stay compatible with this file
- `PAIR_ARCHITECTURE.md`: framework internals and design reasoning
- `PAIR_PATTERNS.md`: implementation patterns and nearby code shape
- `PAIR_CONTEXT.md`: strategic context and anti-pattern avoidance
- `PAIR_TASKS.md`: guidance for larger, riskier, or multi-step tasks

Do not duplicate the same rule in multiple files unless the repetition prevents a likely mistake.

## Reading order

Default reading order for most tasks:

1. `AGENTS.md`
2. `SKILL.md`
3. `GEMINI.md`
4. `CODEX.md` or `CLAUDE.md` only when relevant to the current agent
5. One deeper task-specific document only if uncertainty remains

If the task is small and local, stop after step 3 unless there is ambiguity or architectural risk.

## Conflict resolution

If guidance conflicts, use this order:

1. Existing repository code
2. Tests
3. `AGENTS.md`
4. `SKILL.md`
5. `GEMINI.md`
6. Agent-specific file (`CODEX.md`, `CLAUDE.md`, or equivalent)
7. `PAIR_ARCHITECTURE.md`
8. `PAIR_CONTEXT.md`
9. `PAIR_PATTERNS.md`
10. `PAIR_TASKS.md`

---

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
- If a request is ambiguous, too broad, or risks an architectural or backward-compatibility mistake, ask for clarification before changing code.

---

## Avoid

Agents must avoid:

- Large refactors unrelated to the task
- Rewriting existing utilities with custom implementations
- Introducing new dependencies without clear justification
- Changing public APIs without explicit request
- Changing coding conventions defined in GEMINI.md
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
- [ ] I followed conventions defined in `GEMINI.md`
- [ ] I applied the quick-start guardrails defined in `SKILL.md`
- [ ] I explored the repository before coding
- [ ] I preserved backward compatibility
- [ ] I did not introduce performance regressions
- [ ] I did not introduce new dependencies unnecessarily
- [ ] I did not commit secrets
- [ ] I updated or added tests when behavior changed
- [ ] I updated `pair.wiki` docs when public framework behavior changed
- [ ] I documented manual test steps if applicable
