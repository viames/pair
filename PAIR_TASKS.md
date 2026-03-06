# PAIR_TASKS.md — Task Guidelines for Pair Framework

This document guides AI coding agents when performing **complex tasks** in the Pair framework repository.

It complements:

- SKILL.md (entrypoint)
- GEMINI.md (coding standards and conventions)
- AGENTS.md (workflow)
- CODEX.md (Codex behavior)
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

# General task strategy

When working on a task:

1. Understand the goal of the change.
2. Inspect existing components related to the feature.
3. Follow established patterns.
4. Implement the **smallest safe change** that solves the task.
5. Avoid introducing new architectural concepts.
6. Maintain backward compatibility unless explicitly instructed otherwise.

---

# Bug fix tasks

When fixing a bug:

1. Identify the root cause.
2. Reproduce the problem if possible.
3. Inspect related components.
4. Implement the minimal fix.
5. Verify that existing behavior remains unchanged.

Prefer targeted fixes instead of broad refactors.

---

# Feature implementation tasks

When adding a feature:

1. Identify the appropriate framework layer:
   - controller
   - model
   - utility
   - frontend helper

2. Check whether a similar feature already exists.

3. Reuse existing utilities where possible.

4. Implement the feature in a way that:
   - does not break existing APIs
   - follows Pair patterns
   - remains simple and readable.

---

# Refactoring tasks

When refactoring code:

1. Confirm that the refactor does not change external behavior.
2. Prefer small localized refactors.
3. Avoid refactoring unrelated files.
4. Preserve public APIs whenever possible.

Refactors should focus on:

- clarity
- maintainability
- removing duplication.

---

# Performance tasks

When optimizing performance:

1. Identify the actual bottleneck.
2. Avoid speculative optimizations.
3. Focus on:
   - database queries
   - loops
   - repeated allocations
   - unnecessary ORM calls.

Prefer improving existing logic instead of rewriting components.

---

# Migration tasks

When implementing migrations or framework upgrades:

1. Preserve backward compatibility whenever possible.
2. Document behavior changes clearly.
3. Avoid removing existing APIs unless explicitly required.
4. Prefer deprecation over immediate removal.

---

# Documentation tasks

When updating documentation:

1. Ensure examples match the current framework behavior.
2. Keep documentation concise.
3. Prefer practical examples.
4. Avoid duplicating information across files.

---

# Safety checks before finishing a task

Agents should verify:

- The change follows Pair conventions.
- The change does not break backward compatibility.
- No secrets or sensitive data were introduced.
- No heavy dependencies were added.
- Code remains readable and simple.

---

# When to request clarification

Agents should request clarification only when:

- the task conflicts with existing architecture
- a change may break public APIs
- multiple design approaches exist with significant impact
- a new subsystem would be required

Otherwise prefer implementing a minimal safe solution.

---

# Summary

For any task, agents should:

1. Study the existing code.
2. Follow framework patterns.
3. Implement minimal changes.
4. Preserve compatibility.
5. Avoid unnecessary complexity.

Pair prioritizes **clarity, stability, and maintainability** over clever solutions.
