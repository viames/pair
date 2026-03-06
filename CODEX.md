# CODEX.md — Pair Framework

Guidelines specifically for OpenAI Codex agents working on this repository.

This file complements:

- SKILL.md (entrypoint)
- GEMINI.md (architecture and conventions)
- AGENTS.md (workflow and PR format)
- PAIR_ARCHITECTURE.md (framework architecture)

Codex agents should follow these documents in that order.

---

# Codex agent behavior

Codex runs tasks inside an isolated environment where it can:

- read repository files
- edit code
- run commands
- execute tests
- generate pull requests

Tasks typically involve:

- bug fixes
- feature implementation
- code explanations
- test generation
- documentation updates

Codex should operate conservatively and prefer **small safe changes**.

---

# Repository navigation

Important directories:

/src  
Core framework source code

/assets  
Frontend utilities (PairUI and related scripts)

/translations  
Localization files

/tests  
Unit and integration tests

---

# Coding expectations

Codex must follow framework conventions described in GEMINI.md.

Important rules:

- Tabs for indentation
- One class per file
- Filename must match class name
- CamelCase for classes
- camelCase for variables
- Prefer readable code over clever code

---

# Implementation strategy

When implementing a change:

1. Identify the closest existing component
2. Study its patterns
3. Implement the smallest possible change
4. Maintain backward compatibility
5. Update or add tests when necessary

Avoid introducing new architectural patterns.

---

# Framework-specific advice

## ORM

Pair uses ActiveRecord.

Prefer using:

- relationship helpers
- collections
- built-in ORM utilities

Avoid manual SQL queries when ORM helpers exist.

---

## Routing

Default route pattern:

/<module>/<action>/<params>

Example:

/user/login

Module structure:

/modules/user/controller.php
/modules/user/model.php
/modules/user/viewLogin.php
/modules/user/layouts/login.php

---

# Testing instructions

If tests exist:

Run them before completing the task.

Typical workflow:

1. implement change
2. run tests
3. fix failures
4. confirm tests pass

Never modify tests simply to make failing code pass.

---

# Safe changes

Prefer:

- additive changes
- extension of existing classes
- reuse of framework utilities

Avoid:

- modifying public APIs
- rewriting core components
- introducing new dependencies

---

# Pull request guidelines

Each Codex-generated change should include:

- What changed
- Why it changed
- Files modified
- Tests run
- Risk assessment
- Manual test steps
- Limitations or follow-up work

PRs should remain small and focused.

---

# When uncertain

If the correct approach is unclear:

1. inspect similar components
2. follow existing patterns
3. prefer the simplest solution

Ask for clarification **only when the change may break backward compatibility or introduce a new subsystem**.
