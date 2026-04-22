# GitHub Copilot instructions for Pair

Before suggesting or modifying code in this repository, read and follow these files:

1. AGENTS.md - primary entrypoint, workflow, and output expectations
2. SKILL.md - quick-start and document map
3. GEMINI.md - coding standards, architecture, and security rules
4. PAIR_ARCHITECTURE.md - framework design and request lifecycle
5. PAIR_PATTERNS.md - idiomatic Pair coding patterns
6. PAIR_TASKS.md - task strategy for bugfixes, features, refactors, migrations
7. CODEX.md - additional agent-oriented execution guidance

Important rules:
- Keep diffs minimal.
- Preserve backward compatibility unless explicitly requested otherwise.
- Reuse existing Pair patterns and utilities.
- Avoid introducing unnecessary dependencies.
- Prefer server-rendered and progressively enhanced solutions.
- Do not import Laravel/Symfony-style patterns unless the repository already uses them.
