# GitHub Copilot instructions for Pair

Before suggesting or modifying code in this repository, read and follow these files:

1. AGENTS.md - primary entrypoint, workflow, and output expectations
2. SKILL.md - quick-start and document map
3. PAIR_ARCHITECTURE.md - framework design and request lifecycle when architecture is relevant
4. PAIR_PATTERNS.md - idiomatic Pair coding patterns when local shape is unclear
5. PAIR_TASKS.md - task strategy for larger bugfixes, features, refactors, and migrations

Important rules:
- Keep diffs minimal.
- Preserve backward compatibility unless explicitly requested otherwise.
- Reuse existing Pair patterns and utilities.
- Avoid introducing unnecessary dependencies.
- Prefer server-rendered and progressively enhanced solutions.
- Do not import Laravel/Symfony-style patterns unless the repository already uses them.
