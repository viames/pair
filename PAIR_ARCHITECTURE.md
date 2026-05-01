# PAIR_ARCHITECTURE.md — Pair Framework

This document explains the architectural philosophy and internal structure of the **Pair PHP framework**.
Read it when a task touches framework internals or requires architectural decisions.
Use `AGENTS.md` for workflow, safety rules, and technical conventions.

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

# Fast architecture view

- Pair is pragmatic MVC.
- Applications define modules under `/modules`.
- Routing resolves a module, then a controller action.
- Pair v4 prefers explicit response objects; the legacy MVC bridge still supports view/layout conventions during migration.
- ORM usage should stay close to ActiveRecord and relation helpers.
- PairUI is progressive enhancement, not a frontend application framework.

---

# Core architecture

Pair follows a pragmatic, server-rendered MVC architecture.

The framework provides:

- application bootstrap
- routing
- controllers
- explicit HTML and JSON responses
- legacy views and layouts for migration paths
- ORM
- utilities
- integrations
- debugging tools

Applications built with Pair define modules under `/modules`.

---

# High-level request flow

Typical Pair v4 lifecycle:

1. HTTP request arrives
2. Application bootstrap initializes environment
3. Router parses URL
4. Module controller is resolved
5. Controller action executes
6. Model operations may run through ORM
7. Action returns a `ResponseInterface`
8. Response renders HTML or JSON
9. Page responses may be wrapped by the configured template

Legacy `Pair\Core\Controller` modules still follow the older view/layout bridge while applications migrate to `Pair\Web\Controller`.

Agents should respect this flow when modifying framework code.

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
