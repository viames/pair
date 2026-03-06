# GitHub Copilot instructions for Pair

Before suggesting or modifying code in this repository, read and follow these files:

1. GEMINI.md — coding standards, architecture, security rules
2. AGENTS.md — workflow and output expectations
3. PAIR_ARCHITECTURE.md — framework design and request lifecycle
4. PAIR_PATTERNS.md — idiomatic Pair coding patterns
5. PAIR_TASKS.md — task strategy for bugfixes, features, refactors, migrations
6. CODEX.md — additional agent-oriented execution guidance
7. SKILL.md — repository entrypoint and priority map

Important rules:
- Keep diffs minimal.
- Preserve backward compatibility unless explicitly requested otherwise.
- Reuse existing Pair patterns and utilities.
- Avoid introducing unnecessary dependencies.
- Prefer server-rendered and progressively enhanced solutions.
- Do not import Laravel/Symfony-style patterns unless the repository already uses them.