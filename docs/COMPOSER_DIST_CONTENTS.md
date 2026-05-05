# Composer Dist Contents

This document defines what should be shipped inside `vendor/viames/pair` when web projects install Pair through Composer.

## Include In Composer Dist

Composer dist archives must include the files that a Pair web application can use directly:

- `src/`: framework runtime classes loaded through Composer autoload.
- `assets/`: JavaScript and CSS copied into web projects by `scripts/copy-assets.php`.
- `translations/`: framework translation files.
- `migrations/`: framework schema migrations that applications may need to apply.
- `bin/pair`: Composer binary entry point.
- `scripts/copy-assets.php`: asset installation helper.
- `scripts/update-version-from-tag.php`: application version helper used by consuming Composer scripts.
- `scripts/upgrade-to-v2.php`, `scripts/upgrade-to-v3.php`, `scripts/upgrade-to-v4.php`: application upgrade helpers exposed by Composer scripts.
- `scripts/benchmark-v4.php`: Pair v4 migration/performance validation helper exposed by Composer scripts.
- `composer.json`, `README.md`, `UPGRADE_V4.md`, `SECURITY.md`, and `LICENSE`: package metadata and project-facing reference material.

## Keep Only In Git

Repository-only material should stay in Git but not be copied into `vendor/viames/pair`:

- `.github/` and CI configuration.
- `tests/`, `phpunit.xml.dist`, and PHPUnit cache files.
- `docs/`, including mobile stack and packaging notes.
- `mobile/`, including `PairMobileKit` and `PairMobileAndroid`.
- `vendor/`, because Composer installs Pair dependencies in the consuming project.
- internal planning files such as `PAIR_*`, `RELEASING.md`, and agent/tool instructions.
- release-maintenance scripts such as `scripts/update-version-from-release.php`.
- local editor, environment, and generated files.

The mobile stacks are useful source assets for native app development, but they are not required by PHP web applications using Pair 4. They should be consumed from the Pair Git repository or from future dedicated package distribution channels, not from a web project's Composer vendor directory.
