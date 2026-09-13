# Single-Installation Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove organization scoping from the current Laravel/React ISP billing system while preserving data integrity and making the application operate as one dedicated ISP installation.

**Architecture:** Flatten organization-owned records into installation-wide records. Keep existing business foreign keys and permission names, remove organization resolution and organization predicates, and retain the existing UI/API behavior without introducing a replacement tenant abstraction.

**Tech Stack:** Laravel, PHP, MySQL/SQLite tests, React, TypeScript, Vite, PHPUnit, Vitest.

**Spec:** `docs/superpowers/specs/2026-09-13-single-installation-migration-design.md`

## Global Constraints

- Do not edit already-applied migrations; add a forward migration.
- The migration must fail closed when multiple organizations or conflicting flattened settings/roles exist.
- Preserve customer, billing, invoice, payment, statement, allocation, and audit target data.
- Never retain `organization_id` in any database table after the migration.
- Do not add replacement tenant or organization scoping.
- Backend authorization remains authoritative.
- Run tests before claiming completion.

### Task 1: Establish migration preflight and regression tests

**Files:**
- Create: `backend/tests/Feature/SingleInstallationMigrationTest.php`
- Create: `backend/tests/Feature/GlobalInstallationApiTest.php`
- Modify: `backend/phpunit.xml` only if the existing test environment needs an explicit SQLite migration setting

**Interfaces:**
- Consumes the existing migration chain and current auth helpers.
- Produces failing tests for one-installation preflight, global API access, and cross-organization removal expectations.

- [ ] **Step 1: Write failing tests**

Add tests that:

1. Create the current schema, seed one installation, and assert the flattening migration removes `organization_id` from representative tables including `customers`, `plans`, `invoices`, `roles`, `role_assignments`, and `audit_logs`.
2. Seed two organizations before the flattening migration and assert the migration throws a clear exception before dropping columns.
3. Authenticate a user after flattening and assert a customer/list API request succeeds without `ResolveOrganization` or an organization attribute.
4. Assert the role permission API still returns granular permissions without organization fields.

- [ ] **Step 2: Run the focused tests and verify they fail for the missing migration/application behavior**

Run:

```bash
cd /var/www/html/backend
php artisan test tests/Feature/SingleInstallationMigrationTest.php tests/Feature/GlobalInstallationApiTest.php
```

Expected: FAIL because the flattening migration and global request flow do not exist.

- [ ] **Step 3: Commit the test-only change**

```bash
git add backend/tests/Feature/SingleInstallationMigrationTest.php backend/tests/Feature/GlobalInstallationApiTest.php
git commit -m "test: define single-installation migration behavior"
```

### Task 2: Add the guarded database flattening migration

**Files:**
- Create: `backend/database/migrations/2026_09_13_000018_remove_organization_scoping.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes the existing tables and the preflight tests from Task 1.
- Produces a forward migration that leaves all non-organization business relationships intact and removes every `organization_id` column.

- [ ] **Step 1: Write the migration preflight implementation behind the failing tests**

Implement a migration that:

- Counts rows in `organizations` and throws a `RuntimeException` when more than one exists.
- Verifies organization-scoped settings and role records will not collide after flattening.
- Drops organization-based unique indexes and indexes before removing columns.
- Drops organization foreign keys before columns.
- Removes `organization_id` from every table that has it, including `organization_user`, `roles`, `role_assignments`, all billing tables, settings tables, `gcash_manual_payments`, `personal_access_tokens`, and `audit_logs`.
- Drops `organization_user` and `organizations` after dependent columns are removed.
- Recreates global unique indexes for identifiers previously unique per organization.
- Uses `Schema::hasTable`/`Schema::hasColumn` guards so the migration is safe against the current applied migration set.

The migration must use database-driver-compatible schema operations for MySQL and SQLite tests.

- [ ] **Step 2: Update the seeder to create one global installation without organization ownership**

Remove organization creation, organization pivot assignment, and every `organization_id` value from seed data. Keep the administrator role, billing cycle, plan, plan version, user, and permission seed data functional.

- [ ] **Step 3: Run migration and focused tests**

Run:

```bash
cd /var/www/html/backend
php artisan test tests/Feature/SingleInstallationMigrationTest.php
php artisan migrate:fresh --seed --database=sqlite
```

Expected: the migration test passes and the fresh SQLite schema has no `organization_id` columns.

- [ ] **Step 4: Commit the database change**

```bash
git add backend/database/migrations/2026_09_13_000018_remove_organization_scoping.php backend/database/seeders/DatabaseSeeder.php
git commit -m "feat: flatten database to single installation"
```

### Task 3: Remove organization resolution from authentication and API routing

**Files:**
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/CustomerController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/BillingController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/AuditLogController.php`
- Modify: `backend/app/Services/AuditLogger.php`
- Delete or make unused: `backend/app/Http/Middleware/ResolveOrganization.php`

**Interfaces:**
- Consumes global database schema from Task 2.
- Produces authenticated `/api/v1` routes that query the installation-wide dataset and return the same response envelope.

- [ ] **Step 1: Add failing API assertions for organization-free requests**

Extend `GlobalInstallationApiTest` to cover customer, plans, subscriptions, billing statements, invoices, payments, and audit logs without organization attributes or `where('organization_id', ...)` conditions.

- [ ] **Step 2: Remove `ResolveOrganization` from the authenticated route group**

Keep `auth:sanctum`; remove only the organization middleware. Preserve all permission middleware and endpoint paths.

- [ ] **Step 3: Flatten controller queries and generated records**

Remove organization lookups, `organization_id` assignments, organization-scoped counts, and organization predicates from the controllers listed above. Keep cross-resource ownership validation through existing foreign keys and related model IDs.

- [ ] **Step 4: Run focused API tests**

```bash
cd /var/www/html/backend
php artisan test tests/Feature/GlobalInstallationApiTest.php tests/Feature/BillingWorkflowTest.php
```

- [ ] **Step 5: Commit the API routing change**

```bash
git add backend/routes/api.php backend/app/Http/Controllers/Api/V1/AuthController.php backend/app/Http/Controllers/Api/V1/CustomerController.php backend/app/Http/Controllers/Api/V1/BillingController.php backend/app/Http/Controllers/Api/V1/AuditLogController.php backend/app/Services/AuditLogger.php backend/app/Http/Middleware/ResolveOrganization.php
git commit -m "refactor: remove organization API scoping"
```

### Task 4: Flatten models, settings, RBAC, tokens, and payment integrations

**Files:**
- Modify: all models in `backend/app/Models/` containing `organization_id`
- Modify: `backend/app/Http/Controllers/Api/V1/RoleController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/UserController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/ApiTokenController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/BrandingController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/EmailController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/NotificationController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/PaymongoController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/GcashController.php`
- Modify: `backend/app/Http/Requests/StoreRoleRequest.php`
- Modify: `backend/app/Http/Requests/UpdateRoleRequest.php`
- Modify: `backend/app/Http/Requests/StoreUserRequest.php`
- Modify: `backend/app/Http/Requests/UpdateUserRequest.php`
- Modify: `backend/app/Services/OrganizationPermissionService.php`
- Modify or remove: `backend/app/Models/Organization.php`

**Interfaces:**
- Consumes the global tables from Task 2 and global request flow from Task 3.
- Produces global role/permission/settings/token behavior with unchanged endpoint contracts except removed organization fields.

- [ ] **Step 1: Write failing RBAC/settings/token tests**

Extend existing tests to assert roles have no `organization_id` or `scope`, role assignment works globally, branding/settings load without an organization, API tokens list/create/delete by user, and GCash/payment settings retain their current behavior.

- [ ] **Step 2: Flatten model fillable/casts/relationships**

Remove organization fields and organization relationships from all models. Keep relationships between billing entities, users, settings, roles, payments, statements, and audit logs.

- [ ] **Step 3: Simplify role and user authorization**

Remove organization-aware role filters and pivot constraints. A user’s roles and permissions are installation-wide. Preserve permission names and superadmin behavior.

- [ ] **Step 4: Flatten settings and token controllers**

Replace organization-keyed `firstOrNew`/`firstOrCreate` calls with installation-wide records, preserving user-specific keys where applicable. Remove organization fields from token creation and lookup.

- [ ] **Step 5: Run focused tests**

```bash
cd /var/www/html/backend
php artisan test tests/Feature/PermissionApiTest.php tests/Feature/UserRoleAuthorizationTest.php tests/Feature/ApiTokenTest.php tests/Feature/EmailTest.php tests/Feature/NotificationSettingsTest.php tests/Feature/PaymongoTest.php
```

- [ ] **Step 6: Commit the application flattening**

```bash
git add backend/app backend/tests/Feature
git commit -m "refactor: make authorization and settings installation-wide"
```

### Task 5: Remove organization assumptions from frontend session, roles, and settings

**Files:**
- Modify: `frontend/src/lib/usersRoles.ts`
- Modify: `frontend/src/components/PermissionMatrix.tsx`
- Modify: `frontend/src/components/RoleForm.tsx`
- Modify: `frontend/src/components/UsersPage.tsx`
- Modify: `frontend/src/components/RolesPage.tsx`
- Modify: `frontend/src/components/App.tsx` only if session/branding response types reference organization data
- Modify: affected settings and resource components identified by TypeScript errors
- Modify: affected frontend tests under `frontend/src/**/*.test.tsx`

**Interfaces:**
- Consumes the organization-free API response contract from Tasks 3-4.
- Produces the same navigation and forms without organization fields or organization scope labels.

- [ ] **Step 1: Add failing frontend assertions**

Update role/user tests to expect global roles without `organization_id` and assert settings/pages render from global API responses.

- [ ] **Step 2: Remove organization fields from frontend types and forms**

Remove `organization_id`, organization scope labels, and organization-specific role form inputs while preserving permission matrix behavior.

- [ ] **Step 3: Run focused frontend tests**

```bash
cd /var/www/html/frontend
npm test -- --run src/components/UsersRolesPage.test.tsx
```

- [ ] **Step 4: Commit frontend changes**

```bash
git add frontend/src
git commit -m "refactor: remove organization fields from frontend"
```

### Task 6: Verify migration, source cleanup, and full application behavior

**Files:**
- Modify: affected tests/docs only when verification exposes a real contract mismatch.

- [ ] **Step 1: Run a source-level organization reference scan**

Run:

```bash
cd /var/www/html
rg -n "organization_id|ResolveOrganization|organization\(" backend/app backend/database backend/routes frontend/src
```

Expected: no database field, request attribute, route middleware, query predicate, model fillable field, or frontend type remains. Legacy migration history may retain historical `organization_id` definitions, but runtime code must not.

- [ ] **Step 2: Verify the live database preflight and backup**

Run the project-approved MySQL backup and read-only counts. Do not apply the destructive migration unless the database has at most one organization and the backup completes.

- [ ] **Step 3: Apply and verify production-schema migration**

```bash
cd /var/www/html/backend
php artisan migrate --force
php artisan test
```

- [ ] **Step 4: Verify frontend quality**

```bash
cd /var/www/html/frontend
npm test -- --run
npm run lint
npm run build
```

- [ ] **Step 5: Confirm no organization-owned runtime path remains**

Run the API smoke checks for login, `/auth/me`, customers, billing resources, roles, settings, tokens, and GCash. Confirm the Router module is not implemented until it can be created against the global schema.

