# AGENTS.md — Pair Framework

Guide for automated agents (LLMs, code assistants, review bots) working on this repository.

This file focuses on **how to work** (workflow, expectations, PR format).
Project context, conventions, and environment are in **GEMINI.md** (single source of truth).

> Read GEMINI.md first: architecture, coding standards, security rules.

---

## Mission

Help improve the **Pair framework** with **small, safe, reviewable changes** that respect existing architecture and conventions.

---

## Workflow (recommended)

1.  **Locate the relevant component** in `/src`.
2.  **Read existing patterns** in that component or namespace.
3.  Implement the **smallest change** that solves the problem.
4.  Verify that the change does not break backward compatibility (unless requested).
5.  Add or update unit tests to cover the change.

---

## Change hygiene

- Keep diffs minimal: avoid unsolicited refactoring.
- Preserve backward compatibility.
- Do not add heavy dependencies for simple tasks.
- Do not add code that uses jQuery.
- Do not log sensitive data.
- Do not commit secrets or credentials.

---

## Output expectations (PR / assistant response)

Include:

- **What / Why** (1–3 points)
- **Files modified**
- **Risk and rollback**
- **Manual test steps** (brief checklist, if applicable)

### PR description template

- What / Why:
  - ...
- Files:
  - ...
- Risk and rollback:
  - ...
- Manual test:
  - [ ] ...

---

## Agent checklist

- [ ] I followed conventions (see GEMINI.md)
- [ ] I did not commit secrets
- [ ] I maintained backward compatibility
- [ ] I did not introduce obvious performance regressions (N+1, heavy loops)
- [ ] I added/updated unit tests
- [ ] I documented manual test steps (if applicable)