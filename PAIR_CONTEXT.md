# PAIR_CONTEXT.md — Context for AI Agents Working on Pair

This document gives AI coding agents the **strategic context** needed to work effectively on the Pair framework.

Its purpose is to reduce incorrect assumptions and prevent agents from importing patterns from other frameworks that do not fit Pair.

This file complements:

- SKILL.md
- GEMINI.md
- AGENTS.md
- CODEX.md
- PAIR_ARCHITECTURE.md
- PAIR_PATTERNS.md
- PAIR_TASKS.md

Agents should read this file when they need to understand **what Pair is**, **what it is not**, and **how it differs from larger frameworks**.

---

## What Pair is

Pair is a **lightweight PHP framework** for building modern web applications with a pragmatic MVC approach.

Its defining characteristics are:

- server-rendered first
- simple routing conventions
- ActiveRecord-style ORM
- progressive enhancement on the frontend
- minimal dependencies
- practical utilities instead of heavy abstraction layers

Pair favors a developer experience based on:

- clarity
- speed of development
- low ceremony
- explicit conventions
- maintainability

---

## What Pair is not

Agents must avoid assuming that Pair is:

- a Laravel clone
- a Symfony-style dependency injection framework
- a Rails-style convention engine with many hidden layers
- a SPA-first JavaScript framework
- a build-step-heavy frontend stack
- a framework that expects complex service containers for ordinary tasks

Pair is intentionally smaller, more direct, and more explicit.

---

## Mental model agents should use

When working on Pair, agents should think:

- "Find the closest existing class and follow it."
- "Use conventions already present in the framework."
- "Prefer a small addition over a new subsystem."
- "Preserve server-rendered behavior."
- "Enhance progressively instead of shifting logic to the frontend."

This mental model is usually more correct for Pair than patterns borrowed from larger frameworks.

---

## Comparison with larger PHP frameworks

Compared to Laravel or Symfony, Pair generally prefers:

- fewer layers
- less indirection
- lighter abstractions
- smaller diffs
- direct framework utilities
- simpler frontend behavior

Agents should not introduce patterns such as:

- complex service container usage where not already present
- repository layers for trivial ORM use cases
- event-driven indirection without strong justification
- elaborate configuration systems for small features
- frontend architecture that depends on transpilation or bundling unless explicitly requested

---

## Backend expectations

On the backend, Pair code should usually be:

- readable
- compact but clear
- convention-driven
- compatible with existing public APIs
- secure by default

Agents should prefer:

- existing Pair classes
- existing ORM helpers
- existing utility methods
- existing naming conventions

Agents should avoid:

- rewriting stable logic without need
- adding abstractions before they are necessary
- designing for hypothetical future complexity

---

## Frontend expectations

Pair is not primarily a frontend-heavy framework.

Frontend behavior should usually remain:

- lightweight
- progressive
- build-free when possible
- compatible with server-rendered HTML

PairUI should be preferred over introducing external frontend frameworks for ordinary UI behavior.

Agents should avoid assuming that every interaction needs:

- a component framework
- client-side routing
- a JSON API layer
- state management libraries

---

## Database and ORM expectations

Pair uses an ActiveRecord-style ORM.

Agents should prefer:

- model methods
- relation helpers
- collections
- existing database abstractions

Agents should avoid introducing:

- repository layers for simple CRUD
- raw SQL where ORM support already exists
- unnecessary query abstraction layers

Use raw SQL only when clearly justified by performance or framework limitations.

---

## How to decide the right implementation style

Before implementing something, agents should ask internally:

1. Is there already a similar class in Pair?
2. Can this be solved by extending an existing component?
3. Is the change additive and backward-compatible?
4. Does this preserve Pair simplicity?
5. Am I importing a pattern from another framework that Pair does not need?

If the answer to the last question is "yes", the implementation approach should be reconsidered.

---

## Common mistakes agents should avoid

Frequent mistakes when agents work on lightweight frameworks like Pair:

- overengineering simple features
- introducing unnecessary interfaces
- adding service layers without real benefit
- moving too much logic to JavaScript
- breaking server-rendered assumptions
- using architecture copied from Laravel/Symfony without need
- performing large refactors instead of focused fixes

---

## Preferred contribution style

Good contributions to Pair usually look like this:

- focused
- conservative
- consistent with nearby code
- additive
- easy to review
- easy to roll back

Agents should aim for changes that a maintainer can quickly understand and merge.

---

## Summary

Pair should be treated as a **pragmatic, lightweight, server-rendered PHP framework**.

The best results usually come from these rules:

1. follow existing code first
2. prefer minimal safe changes
3. avoid imported patterns from larger frameworks
4. preserve progressive enhancement
5. keep architecture simple and readable

When uncertain, choose the solution that is **most consistent with the current Pair codebase**, not the one that is most fashionable in other ecosystems.
