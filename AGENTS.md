# AGENTS.md — Pair Framework

Guide for automated agents (LLMs, code assistants, review bots) working on this repository.

This file focuses on **how to work** (workflow, expectations, PR format).
Project context, conventions, and environment are in **GEMINI.md** (single source of truth).
`SKILL.md` is the lightweight entrypoint that points to this file and GEMINI.

---

## Mission

Help improve the **Pair framework** with **small, safe, reviewable changes** that respect existing architecture and conventions.

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

---

## Workflow (recommended)

1. Locate the relevant component in `/src`.
2. Read existing patterns in that component or namespace.
3. Implement the **smallest possible change** that solves the task.
4. Verify that backward compatibility is preserved (unless explicitly requested otherwise).
5. Add or update tests if the behavior changes.
6. Keep the implementation consistent with Pair conventions defined in `GEMINI.md`.

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

- [ ] I followed conventions defined in GEMINI.md
- [ ] I explored the repository before coding
- [ ] I preserved backward compatibility
- [ ] I did not introduce performance regressions
- [ ] I did not introduce new dependencies unnecessarily
- [ ] I did not commit secrets
- [ ] I updated or added tests when behavior changed
- [ ] I documented manual test steps if applicable
