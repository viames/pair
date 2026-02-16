---
name: "Pair Framework"
slug: "pair"
version: "3.0.1"
description: "Entry skill for assistants working on the Pair framework repository."
tags:
  - php
  - pair
  - framework
  - review
language: "en"
---

# Pair Framework — SKILL

Use this skill as the starting point for assistants working in this repository.

This file intentionally stays short to avoid duplicated rules.

---

## Scope

- Framework development in this repository (`src/`, `assets/`, `translations/`, tests when present).
- Safe, minimal, reviewable changes.
- Backward compatibility by default unless explicitly overridden by the task.

---

## Canonical References

1. `GEMINI.md`:
   - architecture, coding standards, security, framework and app-integration conventions.
2. `AGENTS.md`:
   - operating workflow, change hygiene, PR/assistant output format, completion checklist.

---

## Conflict Resolution Order

When guidance conflicts, use this priority:
1. Existing repository code and tests.
2. `GEMINI.md`.
3. `AGENTS.md`.
4. `SKILL.md`.

---

## Minimal Runbook

1. Read `GEMINI.md` first, then `AGENTS.md`.
2. Implement the smallest viable change following local patterns.
3. Add or update unit tests for changed behavior.
4. Deliver results using the format required in `AGENTS.md`.

---
