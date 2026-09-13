# Task 6 Report: Router permissions, role visibility, and auditing

## Status

Complete - Round 1 review fixes applied.

## Changes

- Added routers.view, routers.create, routers.update, routers.delete, routers.test, and routers.export to PermissionSeeder.
- Confirmed the existing permission catalog groups the seeded permissions under Routers, which makes them available to the Roles permission matrix.
- Verified all seven Router API routes use the matching permission middleware:
  - routers.view for list/show
  - routers.create for create
  - routers.update for update
  - routers.delete for soft delete
  - routers.test for connection test and system information
- Added audit events:
  - router.created
  - router.updated
  - router.deleted
  - router.connection_tested
  - router.system_info_requested
- Router audit entries include the authenticated actor, action, Router target type and ID, request correlation ID, result, and lifecycle state snapshots.
- Router audit snapshots exclude metadata so device credentials cannot be persisted in audit payloads.
- No organization_id or organization-scoped Router logic was added.
- Router metadata accepts only approved flat operational keys and is hidden from every Router API resource.
- Credential-bearing metadata is rejected on create and update and cannot be stored through the Router model setter.
- Failed connection-test and system-info driver actions are audited with a sanitized failed result before returning HTTP 422.
- Router lifecycle mutations and their audit entries run in database transactions.
- Roles API resources now expose router permissions under Routers instead of System.

## Verification

- Focused Router, permission, and Roles API tests: 13 passed, 183 assertions.
- Full backend test suite: 94 passed, 690 assertions.
- Task-scoped Pint check: 7 files passed.
- git diff --check: passed.

## Concerns

- The repository-wide Pint check still reports 68 pre-existing style issues across 166 files. The Round 1 files pass the scoped Pint check; unrelated files were not reformatted.
- The approved Router API plan defines seven endpoints and does not currently define a Router export endpoint. The routers.export permission is seeded and exposed for the planned export capability without adding an unplanned endpoint.
