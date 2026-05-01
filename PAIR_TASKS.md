# PAIR_TASKS.md — Task Guidelines for Pair Framework

This document guides AI coding agents when performing **complex tasks** in the Pair framework repository.

It complements:

- AGENTS.md (entrypoint, workflow, and technical conventions)
- SKILL.md (quick-start and document map)
- PAIR_ARCHITECTURE.md (framework architecture)
- PAIR_PATTERNS.md (idiomatic coding patterns)

Agents should consult this document when implementing:

- new features
- bug fixes
- refactoring
- performance improvements
- migrations
- framework extensions

---

# When to Use This File

Open this document only when the task is not obviously local and low-risk.
For routine work, follow `AGENTS.md` and `SKILL.md` first.

This file adds task-shaping guidance for work that crosses layers, carries rollout risk, or needs broader verification.

---

## Cross-Cutting Checklist

Before implementing a larger task:

1. Define the affected layers and public surfaces.
2. Identify compatibility, rollout, and documentation risk.
3. Split the work into the smallest reviewable slices.
4. Decide the verification path before editing.
5. Update wiki or contract docs in the same change when public behavior moves.

---

## Bug Fix Tasks

When fixing a bug:

1. Identify the root cause.
2. Reproduce the problem if possible.
3. Check adjacent behavior and likely regression surface.
4. Prefer a root-cause fix over defensive patching around symptoms.
5. Verify that existing behavior remains unchanged.

---

## Feature Tasks

When adding a feature:

1. Identify the appropriate framework layer:
   - controller
   - model
   - utility
   - frontend helper

2. Check whether a similar feature already exists.

3. Reuse existing utilities where possible.

4. Prefer additive hooks, optional behavior, or narrow extensions over contract changes.
5. Keep new config keys, docs, and public examples in the same diff.

---

## Refactor Tasks

When refactoring code:

1. Confirm that the refactor does not change external behavior.
2. Prefer small localized refactors.
3. Avoid refactoring unrelated files.
4. Remove duplication only where it improves the owner component instead of creating a new abstraction by reflex.

---

## Performance Tasks

When optimizing performance:

1. Identify the actual bottleneck.
2. Avoid speculative optimizations.
3. Focus on:
   - database queries
   - loops
   - repeated allocations
   - unnecessary ORM calls.

4. Prefer improving existing logic instead of rewriting components.

---

## Migration and Upgrade Tasks

When implementing migrations or framework upgrades:

1. Separate schema risk, data risk, and rollout risk in your reasoning.
2. Check downstream models, forms, APIs, jobs, and exports before finalizing the change.
3. Prefer additive changes or deprecation paths over immediate removal.
4. Document rollback and deployment-order constraints clearly.

---

## Documentation Tasks

When updating documentation:

1. Ensure examples match the current framework behavior.
2. Update only the file that owns the topic.
3. Remove duplication instead of copying rules into multiple files.
4. Prefer practical examples only where they clarify the owning document.

---

## Before Finishing a Larger Task

Agents should verify:

- affected layers were all checked explicitly
- compatibility and rollout risk were documented
- verification matched the real risk of the task
- related docs were updated when public behavior changed
