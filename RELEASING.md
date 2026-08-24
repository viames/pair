# Releasing Pair

This document defines the Git workflow for releasing Pair 4 from `main` and maintaining the previous Pair 3 line on `v3`.

## Branch strategy

- `main`: current stable Pair v4 branch.
- `v3`: Pair v3 maintenance branch.
- Short-lived work branches should start from the target maintenance branch or from `main`.

## Stable and Development Lines

- Keep Pair v4 fixes and compatible features on `main`.
- Keep Pair v3-compatible maintenance fixes on `v3`.
- Backport fixes from `main` to `v3` only when they are compatible with Pair v3.
- Keep docs, upgrade notes, and Composer metadata aligned with the branch being released.
- Reserve backward-incompatible public API changes for the next major version.

## Tagging rules

- Stable Pair 3 releases use normal semver tags: `3.0.0`, `3.0.1`, `3.1.0`.
- Stable Pair 4 releases use normal semver tags: `4.0.0`, `4.0.1`, `4.1.0`.
- Pair 4 pre-releases, when needed, use semver suffixes such as `4.1.0-beta.1` or `5.0.0-rc.1`.
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

## Stable Pair 4 Release Sequence

```sh
git checkout main
vendor/bin/phpunit -c phpunit.xml.dist
git tag 4.0.0
git push origin 4.0.0
```

Create the GitHub Release from the pushed tag and include user-facing upgrade, compatibility and rollback notes.

## Composer and Packagist notes

- Pair 3 consumers should install `^3.0`.
- Pair 4 consumers should install `^4.0`.
- `main` carries `extra.branch-alias.dev-main = 4.x-dev`.
- If you want a tracked development line for Pair 3, add `extra.branch-alias.dev-v3 = 3.x-dev` on `v3`.

## Release checklist

- Run the full test suite before tagging.
- Verify `README.md`, `composer.json`, and wiki links point to the correct branch.
- Confirm upgrade scripts and migration notes still match the published major version.
- Write GitHub Release notes with breaking changes, upgrade steps, and manual rollback instructions.
