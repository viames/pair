# PAIR_ARCHITECTURE.md — Pair Framework

This document explains the architectural philosophy and internal structure of the **Pair PHP framework**.

It is intended primarily for:

- AI coding agents
- Framework contributors
- Developers extending Pair internals

This file complements:

- SKILL.md
- GEMINI.md
- AGENTS.md
- CODEX.md

Agents should read these files before modifying the framework.

---

# Design philosophy

Pair is designed with these principles:

1. Simplicity
2. Predictable structure
3. Minimal dependencies
4. Server-rendered first
5. Progressive enhancement
6. Clean MVC separation
7. Backward compatibility

Pair intentionally avoids heavy abstractions or unnecessary layers.

Agents should extend existing patterns instead of introducing new architectural concepts.

---

# Core architecture

Pair follows a pragmatic MVC architecture.

The framework provides:

- application bootstrap
- routing
- controllers
- views
- layouts
- ORM
- utilities
- integrations
- debugging tools

Applications built with Pair define modules under `/modules`.

---

# High-level request flow

Typical lifecycle:

1. HTTP request arrives
2. Application bootstrap initializes environment
3. Router parses URL
4. Module controller is resolved
5. Controller action executes
6. Model operations may run through ORM
7. View is rendered
8. Layout wraps the view
9. Response is returned

Agents should respect this flow when modifying framework code.

---

# Directory structure

Core framework directories:

- `/src` – framework source code
- `/assets` – frontend helpers
- `/translations` – localization files
- `/tests` – framework tests

Applications using Pair typically include:

- `/modules`
- `/config`
- `/public`

---

# ORM architecture

Pair uses an **ActiveRecord-style ORM**.

Key features:

- automatic type casting
- relationship helpers
- lazy loading
- collections

Agents should prefer ORM helpers instead of raw SQL queries.

Example:

`affiliate_id → getAffiliate()`

---

# PairUI philosophy

PairUI is a lightweight progressive enhancement library.

Principles:

- no build step
- minimal JavaScript
- server-rendered HTML first

Main directives:

- data-text
- data-html
- data-show
- data-if
- data-model
- data-on
- data-each

Agents should prefer these directives instead of introducing heavy frontend frameworks.

---

# Framework evolution guidelines

When modifying the framework:

Prefer:

- additive changes
- extending existing classes
- small focused improvements

Avoid:

- breaking public APIs
- large refactors
- heavy dependencies

---

# Security model

Framework code must remain **secure by default**.

Critical areas:

- input validation
- output encoding
- CSRF protection
- session management
- database queries

Agents must never weaken these protections.

---

# Performance considerations

Agents should avoid:

- N+1 queries
- unnecessary loops
- repeated database calls
- excessive allocations

Prefer reuse of cached results and collections.

---

# When uncertainty exists

If architecture decisions are unclear:

1. Prefer a minimal safe implementation
2. Document assumptions
3. Avoid breaking compatibility
4. Ask for clarification only when a change would introduce a new subsystem or break public APIs

---

# Summary

Agents working on Pair should:

1. Study the repository structure
2. Follow existing patterns
3. Implement minimal changes
4. Preserve backward compatibility
5. Avoid unnecessary complexity

Pair favors **clarity and stability over cleverness**.
