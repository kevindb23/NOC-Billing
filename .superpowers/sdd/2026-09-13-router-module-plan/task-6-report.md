# Task 6 Report: Router permissions, role visibility, and auditing

## Status

Complete.

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

## Verification

- Focused Router and permission tests: 14 passed, 197 assertions.
- Full backend test suite: 91 passed, 619 assertions.
- Task-scoped Pint check: 4 files passed.
- git diff --check: passed.

## Concerns

- The repository-wide Pint check still reports 67 pre-existing style issues across 164 files. The Router Task 6 files pass the scoped Pint check; unrelated files were not reformatted.
- The approved Router API plan defines seven endpoints and does not currently define a Router export endpoint. The routers.export permission is seeded and exposed for the planned export capability without adding an unplanned endpoint.
