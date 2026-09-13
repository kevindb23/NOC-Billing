# Task 2 report: Router persistence model

## Status

Completed.

## Implemented

- Added `routers` migration `2026_09_13_000020_create_routers_table.php`.
- Kept the schema single-installation and excluded `organization_id`.
- Added a unique ULID `public_id` and installation-wide unique `name`.
- Added router endpoint and multi-vendor fields:
  - `hostname`
  - `management_ip`
  - `vendor`
  - `model`
  - `software_version`
  - `serial_number`
  - `driver`
  - `preferred_transport`
  - `status`
  - `capabilities`
  - `metadata`
  - contact and synchronization timestamps
  - notes
- Added nullable `created_by` foreign key to `users.id` with `nullOnDelete`.
- Added timestamps and soft deletes.
- Added indexes for `vendor`, `driver`, `status`, `management_ip`, and `last_contact_at`.
- Added `App\Models\Router` using `HasPublicId`, `HasFactory`, and `SoftDeletes`.
- Added casts for JSON fields, contact timestamps, synchronization timestamps, and `deleted_at`.
- Kept generated `public_id` and actor-controlled `created_by` out of `$fillable`.
- Added `creator()` and polymorphic `auditLogs()` relationships.
- Added the specified `RouterApiTest` persistence feature test.

## Verification

Focused command:

```bash
XDG_CONFIG_HOME=/tmp/isp-billing-config php artisan test --filter=RouterApiTest
```

Result: 1 test passed, 21 assertions.

Additional checks:

- PHP syntax checks passed for all three task files.
- `git diff --check` passed for all three task files.

## Concerns

- The migration was verified through the test suite's fresh SQLite database. It was not applied to a live MySQL database as part of this task.
- Router driver implementations, API routes/controllers, permissions, and frontend work are intentionally outside Task 2.
