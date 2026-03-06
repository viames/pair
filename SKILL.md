---
name: "Pair Framework"
slug: "pair"
version: "3.3"
description: "Entry skill for assistants working on the Pair framework repository."
tags:
  - php
  - pair
  - framework
  - review
language: "en"
---

# Pair Framework — SKILL

Use this skill as the **entrypoint** for assistants working in this repository.

This file intentionally remains short and points to canonical documentation.

---

## Scope

Framework development in this repository:

- src/
- assets/
- translations/
- tests

The goal is to produce **safe, minimal, reviewable changes**.

Backward compatibility must be preserved unless explicitly requested otherwise.

---

## Capabilities of the Pair framework

The framework provides:

- MVC routing system
- ActiveRecord ORM
- migration tools
- push notification system
- PWA helpers
- PairUI frontend helper library
- optional integrations with external services

Agents should reuse existing framework components instead of reimplementing similar functionality.

---

## Canonical references

Agents must consult the following files:

1. GEMINI.md  
   Architecture, coding standards, and security rules.

2. AGENTS.md  
   Workflow rules and output expectations.

3. CODEX.md  
   Additional guidelines for Codex agents.

4. PAIR_ARCHITECTURE.md  
   Framework architecture and design philosophy.

5. PAIR_CONTEXT.md  
   Strategic context for understanding what Pair is and what it is not.

6. PAIR_PATTERNS.md  
   Idiomatic coding patterns for Pair framework components.

7. PAIR_TASKS.md  
   Guidelines for handling complex tasks (features, bugfixes, refactors, migrations).

---

## Conflict resolution order

If guidance conflicts:

1. Existing repository code
2. Tests
3. GEMINI.md
4. AGENTS.md
5. CODEX.md
6. PAIR_ARCHITECTURE.md
7. PAIR_CONTEXT.md
8. PAIR_PATTERNS.md
9. PAIR_TASKS.md
10. SKILL.md

---

## Minimal runbook

1. Read GEMINI.md first.
2. Read AGENTS.md.
3. Read CODEX.md when working in Codex environments.
4. Read PAIR_ARCHITECTURE.md to understand framework design.
5. Read PAIR_CONTEXT.md to avoid importing patterns from unrelated frameworks.
6. Use PAIR_PATTERNS.md to implement idiomatic Pair code.
7. Use PAIR_TASKS.md for guidance on complex tasks.
8. Explore the repository structure.
9. Implement the smallest viable change.
10. Provide output using the format defined in AGENTS.md.
