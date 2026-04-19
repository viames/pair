# Releasing Pair

This document defines the clean Git workflow for keeping Pair 3 stable while opening Pair 4 as the next unstable development line.

## Branch strategy

- `v1`, `v2`, `v3`: maintenance branches for released major versions.
- `main`: next unreleased major version.
- Short-lived work branches should start from the target maintenance branch or from `main`.

## Pair 3 to Pair 4 transition

1. Cut `v3` from the current Pair v3 codebase once tests and docs are green.
2. Keep Pair v3 documentation and package metadata on `v3`.
3. Publish the first stable Pair 3 release from `v3` with tag `3.0.0`.
4. Create the GitHub Release from that tag and include upgrade notes for users coming from Pair 2.
5. After `3.0.0` is published, move `main` to Pair 4 development:
   - update the repository wording from Pair v3 to Pair v4 alpha
   - add Composer branch alias `dev-main => 4.x-dev`
   - keep breaking and experimental work only on `main`
6. Backport only Pair 3 compatible fixes to `v3`.

## Tagging rules

- Stable Pair 3 releases use normal semver tags: `3.0.0`, `3.0.1`, `3.1.0`.
- Pair 4 pre-releases use semver pre-release tags: `4.0.0-alpha.1`, `4.0.0-beta.1`, `4.0.0-rc.1`.
- Tags must be created from a clean commit on the intended branch.
- Never move an existing published tag.

## Suggested Git sequence

```sh
git checkout main
vendor/bin/phpunit -c phpunit.xml.dist

git branch v3
git push origin v3

git checkout v3
git tag 3.0.0
git push origin 3.0.0

git checkout main
# Update branch alias and repo wording to Pair v4 alpha here.
git push origin main
```

## Composer and Packagist notes

- Stable Pair 3 consumers should install `^3.0`.
- Users testing the next major should install `dev-main`.
- Once `main` becomes Pair 4, add `extra.branch-alias.dev-main = 4.x-dev` on `main`.
- If you want a tracked development line for Pair 3, add `extra.branch-alias.dev-v3 = 3.x-dev` on `v3`.

## Release checklist

- Run the full test suite before cutting the release branch and before tagging.
- Verify `README.md`, `composer.json`, and wiki links point to the correct branch.
- Confirm upgrade scripts and migration notes still match the published major version.
- Write GitHub Release notes with breaking changes, upgrade steps, and manual rollback instructions.
