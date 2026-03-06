# GEMINI.md — Pair Framework (single source of truth)

Project context, conventions, and guardrails for AI assistants working on the Pair framework.

All automated agents must follow this file unless a task explicitly overrides it.

If you are an automated agent, also read **AGENTS.md** for workflow and PR expectations.

---

# Operating principles

- **Minimal diff:** change only what the task requires.
- **No breaking changes:** maintain backward compatibility unless explicitly requested.
- **Security first:** never weaken validation or security mechanisms.
- **Follow existing patterns:** reuse architecture already present in the framework.

---

# Project overview

**Pair** is a lightweight PHP framework for building modern web applications.

Primary goals:

- MVC architecture
- ActiveRecord ORM
- Clean routing
- Framework utilities
- Push notifications
- PWA helpers
- Simple integration with external services

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

# Framework architecture

## Directory layout

/src
Framework source code

/assets
Frontend utilities

/translations
Localization files

/tests
Unit and integration tests

---

## Namespace rules

Core classes are under:

Pair\

PSR‑4 autoloading.

One class per file.

Filename must match class name.

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

# Framework evolution rules

When adding new features:

- Prefer additive changes.
- Avoid modifying public APIs.
- Preserve backward compatibility.
- Extend existing components instead of introducing parallel systems.

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
