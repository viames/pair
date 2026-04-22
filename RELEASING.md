# Releasing Pair

This document defines the Git workflow for maintaining Pair 3 while Pair 4 is developed on `main`.

## Branch strategy

- `v3`: current stable maintenance branch.
- `main`: Pair v4 alpha development branch.
- Short-lived work branches should start from the target maintenance branch or from `main`.

## Stable and Development Lines

- Keep Pair v3-compatible fixes on `v3`.
- Keep Pair v4 breaking or experimental work on `main`.
- Backport fixes from `main` to `v3` only when they are compatible with Pair v3.
- Keep docs, upgrade notes, and Composer metadata aligned with the branch being released.

## Tagging rules

- Stable Pair 3 releases use normal semver tags: `3.0.0`, `3.0.1`, `3.1.0`.
- Pair 4 pre-releases use semver pre-release tags: `4.0.0-alpha.1`, `4.0.0-beta.1`, `4.0.0-rc.1`.
- Tags must be created from a clean commit on the intended branch.
- Never move an existing published tag.

## Stable Pair 3 Release Sequence

```sh
git checkout v3
vendor/bin/phpunit -c phpunit.xml.dist
git tag 3.0.1
git push origin 3.0.1
```

Create the GitHub Release from the pushed tag and include user-facing upgrade or rollback notes.

## Pair 4 Pre-Release Sequence

```sh
git checkout main
vendor/bin/phpunit -c phpunit.xml.dist
git tag 4.0.0-alpha.1
git push origin 4.0.0-alpha.1
```

Use beta and release-candidate tags only after the public API and migration path are ready for that stability level.

## Composer and Packagist notes

- Stable Pair 3 consumers should install `^3.0`.
- Users testing the next major should install `dev-main`.
- `main` carries `extra.branch-alias.dev-main = 4.x-dev`.
- If you want a tracked development line for Pair 3, add `extra.branch-alias.dev-v3 = 3.x-dev` on `v3`.

## Release checklist

- Run the full test suite before tagging.
- Verify `README.md`, `composer.json`, and wiki links point to the correct branch.
- Confirm upgrade scripts and migration notes still match the published major version.
- Write GitHub Release notes with breaking changes, upgrade steps, and manual rollback instructions.
