# GEMINI.md — Pair Framework v4 Technical Reference

Technical context, conventions, and guardrails for AI assistants working on the Pair v4 framework.

Read `AGENTS.md` first for the primary workflow and operating contract.
Read `SKILL.md` next for the compact quick-start and repository map.
Use this file as the main technical reference after the entrypoint.

---

# Scope

This file owns technical conventions and framework-level behavior.
Workflow, change scope, document ownership, and output format remain in `AGENTS.md`.

---

# High-signal conventions

These are the conventions most likely to affect implementation accuracy:

- Use tabs for indentation.
- Keep one class per file.
- Match filename and class name.
- Prefer readable multi-line code over compact clever code.
- Prefer existing ORM relation helpers over manual joins when possible.
- Keep frontend behavior lightweight and progressively enhanced.

---

# Stack and conventions

## Backend

Language: **PHP 8.3+ / 8.4+**

Required extensions:

- fileinfo
- json
- pcre
- PDO
- intl
- pdo_mysql
- Reflection

---

## Frontend

Pair includes a lightweight frontend helper library:

**PairUI**

Agents should prefer:

- Vanilla JavaScript
- PairUI directives

Avoid:

- jQuery
- heavy frontend frameworks

---

## Namespace rules

Core classes are under:

Pair\

PSR‑4 autoloading.

---

# ORM behavior

Pair uses **ActiveRecord**.

Important behaviors:

- automatic type casting
- relationship helpers
- parent relation helpers
- reverse relations returning collections

Agents must prefer relation helpers instead of manual SQL joins when possible.

---

# Routing conventions

Default route pattern:

/<module>/<action>/<params>

Example:

example.com/user/login

module → /modules/user

controller → controller.php

action → loginAction()

Views and layouts are auto-loaded by convention.

---

# Coding standards

## Formatting

- indentation: **tabs**
- tab width: **4**
- opening brace on same line
- avoid short PHP tags

---

## Naming

Variables:

camelCase

Classes:

CamelCase

Interfaces:

Suffix Interface

Constants:

UPPER_SNAKE_CASE

---

## Control flow

Prefer readable multi-line code instead of complex one-liners.

Prefer:

and / or

instead of

&& / ||

Use parentheses when needed due to precedence differences.

## Comments

- PHP functions should include a docblock or function comment.
- JS functions should include a docblock or function comment.
- Non-trivial code should include a short explanatory comment.

---

# Security rules

Framework code must always be:

secure-by-default.

Critical areas:

- input validation
- output escaping
- CSRF protection
- session handling
- database queries

Never introduce code that weakens security mechanisms.

---

# Testing philosophy

When tests exist:

- Update them when behavior changes.
- Add tests for new behavior.
- Prefer deterministic tests.

Never modify tests just to make failing implementations pass.

---

# Performance

Agents should avoid:

- N+1 queries
- heavy loops
- unnecessary allocations
- repeated database calls

Prefer reusing cached results and collections when possible.
