# Composer installation checks

From the framework checkout, after `composer install`:

```sh
composer test:install
```

This separate integration check needs Composer and the PHP `zip` extension as
development tools. It creates actual `composer archive` ZIP files, installs them
into disposable consuming projects and runs the generated Composer proxy plus
the direct package executable. It verifies normal/custom vendor directories,
a separate bin directory, all three generators, PHP syntax and dist exclusions.
It does not load an application's `.env`.

The runtime dependency `psr/log` is archived from the framework's installed
vendor tree. Packagist is disabled and Composer uses a private home/cache, so
package resolution is offline and does not use the maintainer's credentials.

## Include the starter

Clone `viames/pair_boilerplate` alongside the framework, then run:

```sh
composer test:install -- ../pair_boilerplate
```

The check additionally archives the starter and runs real `composer create-project`
with stable local artifacts. The fixture versions are **4.1.12** for Pair and
**4.0.0** for the starter: they identify the coordinated release baseline in this isolated repository.
Running the test never publishes or changes repository versions/tags.

The starter archive must omit its old template lock file. A newly created app
resolves compatible stable dependencies and receives its own reproducible
`composer.lock`. Framework dev dependencies are also archived for Composer's
fresh dependency resolution, even though the generated project is installed
with `--no-dev`.

The check exercises Composer hooks with no configuration, both CLI entrypoints,
the initial installer GET and the starter's PHPUnit suite using the framework's
PHPUnit executable. DB-dependent starter unit cases remain skipped until an
application environment is configured.

## Optional fresh-database test

Set explicit fixture-only credentials to run the starter's guarded database test:

```sh
export PAIR_INSTALL_DB_HOST=127.0.0.1
export PAIR_INSTALL_DB_NAME=pair_test_starter
export PAIR_INSTALL_DB_USER=pair_test
export PAIR_INSTALL_DB_PASS=fixture-password
composer test:install -- ../pair_boilerplate
```

The account should have grants only on the disposable schema. The name must
begin with `pair_test_`; an existing populated schema is rejected before any
writes or cleanup. The test imports the actual schema/seed, creates and verifies
the generated administrator credential, checks passkey storage and migration markers, loads the generated `.env` through
Pair, authenticates the administrator through the framework and rejects repeated installation.
The schema claimed empty by this run is dropped afterwards.
With the generated `.env` in place, the check also reruns the starter PHPUnit
suite so its database-dependent controller cases are exercised rather than skipped.

This test does not update existing apps, test external email delivery or prove
browser login/accessibility behavior. Full browser acceptance remains a release
check. For connection/transaction behavior across supported server versions,
use the separate [MySQL suite](../MySql/README.md).

## Diagnostics

Temporary workspaces are removed on success and failure. Set `PAIR_INSTALL_KEEP=1`
to retain one for inspection; with the optional DB test it can contain generated
fixture configuration. Never commit these directories. `COMPOSER_BINARY` can
select another Composer executable. The test itself runs generated PHP commands
with the current `PHP_BINARY`.
