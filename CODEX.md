# CODEX.md — Pair Framework v4

Codex-specific note for this repository.

Read in order:

1. `AGENTS.md`
2. `SKILL.md`
3. `GEMINI.md`

If the task is small and local, stop there. Open deeper docs only when the task needs framework internals, implementation-pattern guidance, or Codex-specific clarification.

---

## Codex-Specific Stance

- Do not restate workflow, change-hygiene, or coding rules already owned by `AGENTS.md` and `GEMINI.md`.
- Prefer repository code and tests over generic model priors when they disagree.
- When multiple local patterns exist, follow the closest namespace or component example.
- Ask for clarification only when the change risks breaking public APIs or introducing a new subsystem.

## Next Docs

- `PAIR_ARCHITECTURE.md`: framework internals and lifecycle details
- `PAIR_PATTERNS.md`: nearby code shape and examples
- `PAIR_TASKS.md`: broader or higher-risk tasks
