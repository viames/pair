# CODEX.md — Pair Framework

Guidelines specifically for OpenAI Codex agents working on this repository.

This file complements:

- SKILL.md (entrypoint)
- GEMINI.md (architecture and conventions)
- AGENTS.md (workflow and PR format)
- PAIR_ARCHITECTURE.md (framework architecture)

Codex agents should follow these documents in that order.

When the task is small, stop after `SKILL.md`, `AGENTS.md`, and `GEMINI.md` unless uncertainty remains.

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

If two plausible implementations exist, prefer the one that is:

1. closer to nearby Pair code
2. smaller in diff size
3. less likely to affect public APIs

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

# When uncertain

If the correct approach is unclear:

1. inspect similar components
2. follow existing patterns
3. prefer the simplest solution

Ask for clarification **only when the change may break backward compatibility or introduce a new subsystem**.
