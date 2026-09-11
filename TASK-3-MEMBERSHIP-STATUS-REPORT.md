# Task 3 Membership Status Scope Report

Status: complete

Organization membership status is now tenant-scoped. User POST/PUT status values update only the active organization membership pivot, while the global `users.status` remains unchanged. DELETE deactivates only the selected membership; an active membership in another organization keeps the global account active.

Authentication accepts `X-Organization-Id` and requires an active membership in that selected organization. Audit transitions use `user.deactivated` for DELETE and membership status changes to inactive, `user.activated` for active restoration, and `user.updated` for non-status edits. Audit snapshots include actor and organization context and exclude passwords and tokens.

## Verification

- TDD red regressions reproduced global-status mutation and organization-agnostic login.
- Focused: `php artisan test tests/Feature/UserCrudTest.php` — 17 passed, 142 assertions.
- Full backend: `php artisan test` — 51 passed, 333 assertions.
- `git diff --check` — passed.
- No frontend files changed.
