# Task 4 Report

## Status

Complete; round 1 hardened.

Implemented organization-scoped Roles CRUD and permission matrix persistence. Added role listing with assignment and permission counts, role details with flat and authority-grouped permission IDs, transactional creation and updates, scoped duplicate-name and permission validation, assigned-role deletion protection, global-role mutation rejection, permission middleware on every route, and auditable role changes.

Audit events use the existing `AuditLogger`: `role.created`, `role.updated`, `role.permissions_updated`, and `role.deleted`. Snapshots contain role metadata and permission IDs only; no passwords or tokens are audited.

## Round 1 Fixes

- `GET /api/v1/roles` is now paginated and returns both organization-owned and global roles with `scope`, organization-scoped assignment counts, and permission counts.
- Global roles remain visible for read operations but return 422 for organization mutation attempts.
- Roles belonging to another organization are treated as not found for detail, update, and delete requests.
- Added regression coverage for pagination and scope, foreign-role denial, global deletion rejection, and transaction rollback when permission synchronization fails.

## TDD and Verification

- Focused red run: `php artisan test tests/Feature/RoleCrudTest.php` initially failed with 404 responses and missing role records because the routes/controller were absent.
- Focused green: `php artisan test tests/Feature/RoleCrudTest.php` — 10 passed, 37 assertions.
- Round 1 focused: `php artisan test tests/Feature/RoleCrudTest.php` — 14 passed, 54 assertions.
- Full backend: `php artisan test` — 43 passed, 174 assertions.
- Targeted Pint check and `git diff --check` passed after the round-1 changes.
- Targeted Pint check passed for all four new PHP files.
- `git diff --check` passed.
- No frontend files changed.

## Concerns

- No known concerns.
