# Single-Installation Migration Design

## Goal

Convert the current organization-scoped Laravel/React application into one installation-wide ISP system, matching `MASTER_V2.md`, by removing `organization_id` from application tables and eliminating organization-resolution logic without losing existing billing, RBAC, settings, or audit behavior.

## Scope

Included:

- Remove organization ownership from all current domain, settings, RBAC, token, and audit records.
- Replace organization-scoped queries with installation-wide queries.
- Remove `ResolveOrganization` from authenticated API routes.
- Simplify roles and role assignments to global installation roles.
- Keep existing setting model/table names for this migration to avoid an unnecessary data rename; remove their organization ownership and treat them as installation-wide records. A naming cleanup can be a separate non-destructive change.
- Remove organization data from API payloads and frontend types/tests.
- Preserve existing billing relationships, numbering, permissions, and user access behavior.
- Add a reversible migration strategy and regression coverage.

Excluded:

- Introducing a replacement tenant or organization abstraction.
- Changing billing business rules, invoice semantics, or Router driver architecture.
- Dropping data from business tables. Existing records must remain available after the migration.

## Architectural Decisions

### Single installation

The deployment contains one ISP installation. All records are global within that installation. Users, roles, billing records, settings, customers, network inventory, and audit logs no longer need an organization foreign key.

### Database migration

Do not edit already-applied migrations. Add a new migration that:

1. Verifies that the database contains at most one organization and that organization-owned settings do not contain conflicting rows.
2. Removes organization-based composite indexes and foreign keys.
3. Drops `organization_id` from all domain, settings, RBAC, audit, and token tables.
4. Removes `organization_user` and `organizations` after dependent references are gone.
5. Adds global unique constraints where the previous uniqueness was scoped by organization.

The migration must fail before destructive schema changes if multiple organizations or conflicting records are detected. Because organization ownership is intentionally discarded, the migration is documented as irreversible: its `down()` method must fail with an explicit message instead of pretending it can restore lost ownership semantics.

### Application layer

- Remove the organization middleware from `routes/api.php`.
- Remove request organization lookups and every `where('organization_id', ...)` predicate.
- Remove organization fields from `$fillable`, relationships, validation rules, request payloads, and resource presenters.
- Keep permission names and granular actions unchanged unless they were organization-only concepts.
- Make roles global; `RoleController`, role requests, `User`, and role-assignment queries no longer distinguish global versus organization roles.
- Make branding, email, notifications, PayMongo, GCash, and billing settings installation-wide. User-specific settings may retain `user_id`.
- Keep authentication and API-token ownership tied to the authenticated user, not an organization.

### Data integrity

Foreign keys between business tables remain. Only organization ownership foreign keys are removed. Existing business IDs, public IDs, billing numbers, payment allocations, statement links, and audit target references remain intact.

## Migration Safety

Before applying the migration:

- Check migration status and database connectivity.
- Count organizations and organization-owned rows.
- Confirm whether any global and organization-scoped role/settings records conflict after flattening.
- Require a database backup before applying the destructive migration.
- Run the migration in a transaction where supported and fail closed on preflight conflicts.

## Verification

- Fresh migrations and migration rollback pass on SQLite test setup.
- Production MySQL migration preflight reports a single installation and completes without orphaned foreign keys.
- Feature tests prove authenticated APIs work without `ResolveOrganization`.
- Billing workflow, GCash, settings, users/roles, API tokens, and audit logging tests pass after flattening.
- Frontend tests prove sessions, roles, settings, and resource pages no longer expect organization fields.
- PHP tests, frontend tests, TypeScript, lint, and production build pass.
