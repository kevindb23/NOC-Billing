# Task 3 implementation report

## Scope

Implemented transport-specific router credential validation and metadata-only
credential handling in the Laravel backend. The implementation keeps router
and credential records installation-wide, without adding `organization_id` to
either schema or credential payload.

## Implementation

- Added `RouterCredentialRequest` transport rules for `api`, `ssh`, `netconf`,
  and `snmp`, including port bounds and transport-specific fields.
- Added `RouterCredentialService` to create or replace the primary profile,
  encrypt secrets through model casts, preserve blank update secrets, preserve
  connection metadata, increment versions only when secrets change, and
  expose only non-secret metadata.
- Updated router create/update requests to validate nested profiles, reject
  `mock`, require SSH/NETCONF username and password on create, and require
  credentials when changing transport.
- Updated router create/update controller actions to persist router and
  credentials transactionally and to return credential configuration status,
  metadata, and version without decrypted values.
- Added non-secret credential snapshots to router audits.
- Added connection metadata storage and changed the routers default transport
  from `mock` to `api`, converting existing `mock` rows during migration.
- Added API acceptance coverage for transport requirements, validation,
  secret-free responses/audits, update preservation, versioning, and mock
  rejection, including an explicit-transport blank-secret regression case.

## Validation

Passed:

```text
vendor/bin/phpunit tests/Feature/RouterCredentialApiTest.php tests/Feature/RouterApiTest.php --do-not-cache-result
23 tests, 248 assertions, OK

PHP lint for every changed/created PHP file
No syntax errors

git diff --check
Passed
```

The requested Artisan test command could not run because this Laravel install
does not register the `test` command:

```text
Command "test" is not defined. Did you mean one of these?
```

The requested Artisan migration command reached the application but could not
connect to its configured MySQL database:

```text
SQLSTATE[HY000] [2002] Unknown error while connecting
(Connection: mysql, database: forge)
```

The full PHPUnit suite was also attempted. It reported 105 tests with 51
environment/setup errors and 2 unrelated failures outside this task, so the
focused Task 3 and Router CRUD suite is the reliable verification gate here.

## Concerns

- A normal Artisan migration verification still requires a reachable MySQL
  service or a worktree environment configured for the intended database.
- The repository-wide suite has pre-existing environment and unrelated
  baseline failures; they were not changed as part of Task 3.

## Review fixes

- Updated `GET /routers` and `GET /routers/{publicId}` to return the same
  secret-free credential configuration, profile, version, and safe connection
  metadata as create/update responses. The index eager-loads primary
  credentials and transforms the paginator collection without per-router
  credential queries.
- Added `port`, `api_base_url`, `auth_mode`, and `snmp_version` to the
  metadata-only credential resource, with an allowlist that excludes all other
  connection metadata.
- Transport changes now discard old transport metadata before applying only
  fields valid for the new transport and its default port.
- Update audit snapshots now include the old safe credential profile and
  credential version, while continuing to exclude secrets.
- Made the transport-default migration use a transaction for the data update
  and compensating default restoration around MySQL's non-transactional DDL;
  rollback also reverses the mock-to-api data conversion.
- Added regression coverage for GET resources, eager loading, metadata
  allowlisting, transport replacement, old audit snapshots, and migration
  rollback.
