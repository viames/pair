# MySQL integration tests

The separate suite exercises the real `Pair\Orm\Database` connection, Unicode
round trips (including emoji and supplementary characters), quoted and prepared
writes, the query builder, JSON, generated columns, matched-row counts, schema
metadata, foreign keys, transactions/savepoints and framework error mapping.
It does not run as part of `composer test` and never uses an application's `.env`.
CI runs it on PHP 8.4/8.5 with MySQL 8.0, 8.4 and 26.10.

## Provision an isolated schema

Use an administrative MySQL session to create a **disposable** schema and a user
with privileges on that schema only. Never point the suite at application data.
For example, on a local server:

```sql
CREATE DATABASE pair_test_integration CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pair_test'@'localhost' IDENTIFIED BY 'choose-a-local-test-password';
GRANT ALL PRIVILEGES ON pair_test_integration.* TO 'pair_test'@'localhost';
```

Then run from the framework checkout, supplying the password through your local
environment rather than a tracked file:

```sh
export PAIR_TEST_DB_HOST=127.0.0.1
export PAIR_TEST_DB_NAME=pair_test_integration
export PAIR_TEST_DB_USER=pair_test
read -rs PAIR_TEST_DB_PASS
export PAIR_TEST_DB_PASS
export PAIR_TEST_MYSQL_VERSION=26.10
composer test:mysql
```

`PAIR_TEST_MYSQL_VERSION` is optional locally; when supplied, it verifies the
server release prefix, so a test accidentally targeting another server fails.
The database name must begin with `pair_test_`; invalid or missing names fail
before connecting. For an isolated Unix socket, `PAIR_TEST_DB_HOST` can be
`localhost;unix_socket=/absolute/path/mysql.sock`, using the existing PDO DSN
host fragment. The test schema must already exist.

Tests create only `pair_mysql_parents` and `pair_mysql_records`, and remove only
tables successfully created by that test. Setup fails if those tables already
exist; it does not overwrite or drop them. Use separate schemas for concurrent
runs. After an interrupted run, inspect any remaining fixtures before removing
them or provision another disposable schema.

## Limits

This suite checks framework database behavior. It does not validate application
SQL, existing table collations, SQL modes, migrations, dumps, replication or a
server upgrade. Test each application against a restored local/staging copy
before upgrading a production server. Reverting framework code does not reverse
a MySQL data-directory upgrade; that requires the appropriate original server
and a verified pre-upgrade backup.
