# Database metadata efficiency

## Versioned schema metadata cache

Foreign-key and inverse foreign-key lookups join the complete constraint identity
(schema, table and constraint name) and retain their request-local cache.
`describeTable()` and `describeColumn()` also reuse the complete column description,
including a missing-column result, without caching application data.
To share these metadata reads between requests, set `PAIR_SCHEMA_CACHE_VERSION`
to a non-empty schema release identifier in the application `.env`. The default
is empty: existing applications keep live database lookups.

The configured Pair cache store is used, separated by host, database, database
user and schema version. Entries contain only column descriptions and foreign-key metadata and expire
after one hour. Empty results are cached; cache errors fall back to SQL. No query
results, users, permissions or application rows are stored.

**Change the version for every schema or database-grant change**, including DDL
executed outside Pair. Pair DDL also rotates this namespace's cache generation;
this is additional protection, not a replacement for release versioning. Do not
share one version across differing schemas. A connection that executes `USE` or
creates/drops temporary tables bypasses shared metadata for its remaining life.
No global application cache flush is needed. To disable it, remove the version
and restart long-running PHP workers. The cache must be private and writable by
the application user; use the established cache path/prefix per application.

```dotenv
PAIR_SCHEMA_CACHE_VERSION="schema-2026-10-28"
```

The low-level `Pair\Cache\SchemaCache` is an internal metadata helper; application
code should continue using `Database::describeTable()`, `Database::describeColumn()`,
`Database::getForeignKeys()` and `Database::getInverseForeignKeys()`.
