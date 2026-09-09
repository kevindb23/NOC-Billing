# ISP Billing Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build the first working, tenant-safe ISP billing vertical slice in `/var/www/html`, including Laravel APIs, MySQL migrations, React pages, CRUD operations, invoice/payment workflows, tests, and deployment documentation.

**Architecture:** Laravel 10 provides the REST API, authentication boundary, tenant context, validation, policies, billing services, and MySQL persistence. React + TypeScript + Vite provides the operator UI. Redis remains configured for future queues/cache; network automation is explicitly outside this release.

**Tech Stack:** PHP 8.1, Laravel 10, MySQL 8, Redis configuration, React, TypeScript, Vite, Tailwind CSS, PHPUnit/Pest-compatible Laravel tests, Vitest.

**Spec:** `/var/www/html/MASTER.md` and `/var/www/html/docs/DATABASE-DESIGN.md`.

## Global Constraints

- All tenant-owned records include `organization_id`.
- Tenant context is resolved server-side from the authenticated user and active organization.
- Financial money is stored in integer minor units with an ISO currency.
- Financial records are never silently deleted.
- API routes use `/api/v1` and return consistent JSON errors.
- No `.env`, credentials, tokens, logs, dependencies, or build output are committed.
- No network-vendor automation is implemented in this milestone.

### Task 1: Repository and application foundations

**Files:**
- Create: `backend/` Laravel application
- Create: `frontend/` React/Vite application
- Create: `.gitignore`
- Create: `README.md`
- Modify: `docs/DATABASE-DESIGN.md`

**Steps:**

- [ ] Scaffold Laravel 10 and React/Vite projects.
- [ ] Configure backend environment defaults, database connection, API routing, CORS, and Redis settings.
- [ ] Configure frontend API base URL, TypeScript, Tailwind, and a compact operations layout.
- [ ] Add repository ignore rules for `.env*`, `vendor/`, `node_modules/`, logs, storage runtime files, and build output.
- [ ] Verify backend boots with `php artisan about` and frontend builds with `npm run build`.

### Task 2: Database migrations and models

**Files:**
- Create: `backend/database/migrations/*_create_billing_tables.php`
- Create: `backend/app/Models/*.php`
- Create: `backend/database/seeders/DatabaseSeeder.php`
- Create: `backend/tests/Feature/DatabaseSchemaTest.php`

**Steps:**

- [ ] Write failing schema tests for organization, customer, billing account, plan version, service, subscription, invoice, payment, allocation, ledger, and audit relationships.
- [ ] Run the schema test and confirm it fails because migrations/models do not exist.
- [ ] Implement migrations with foreign keys, tenant-aware unique constraints, indexes, status fields, and minor-unit money fields.
- [ ] Implement Eloquent relationships and casts.
- [ ] Add a development-only seeder for one organization, one operator, plans, and non-production billing examples.
- [ ] Run migrations and schema tests successfully.

### Task 3: Authentication, tenant context, and policies

**Files:**
- Create: `backend/app/Http/Middleware/ResolveOrganization.php`
- Create: `backend/app/Policies/*Policy.php`
- Create: `backend/app/Http/Requests/*Request.php`
- Create: `backend/routes/api.php`
- Create: `backend/tests/Feature/TenantIsolationTest.php`

**Steps:**

- [ ] Write failing tests proving users can only read and mutate records belonging to their active organization.
- [ ] Run the tests and confirm they fail before tenant middleware and policies exist.
- [ ] Implement authentication, organization membership resolution, policy checks, and validation requests.
- [ ] Return correlation IDs and consistent validation/authorization errors.
- [ ] Run tenant isolation tests and confirm cross-tenant access is denied.

### Task 4: CRUD APIs

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/*Controller.php`
- Create: `backend/app/Http/Resources/*Resource.php`
- Create: `backend/app/Services/Billing/*Service.php`
- Create: `backend/tests/Feature/Api/*CrudTest.php`

**Steps:**

- [ ] Write failing API tests for customer, billing account, plan, service, subscription, invoice, and payment CRUD.
- [ ] Run the tests and confirm the expected missing-route failures.
- [ ] Implement paginated list, search, filter, create, update, show, and status operations.
- [ ] Implement server-side subscription and invoice calculations.
- [ ] Implement payment allocation transactions with duplicate/idempotency protection.
- [ ] Run all API CRUD tests.

### Task 5: React operations dashboard

**Files:**
- Create: `frontend/src/app/*`
- Create: `frontend/src/components/*`
- Create: `frontend/src/features/customers/*`
- Create: `frontend/src/features/billing/*`
- Create: `frontend/src/pages/*`
- Create: `frontend/src/tests/*`

**Steps:**

- [ ] Write failing component tests for navigation, customer creation, invoice list, and payment form states.
- [ ] Run the tests and confirm they fail before page components exist.
- [ ] Implement authenticated shell, sidebar navigation, data tables, forms, status badges, validation errors, empty states, and detail pages.
- [ ] Connect pages to `/api/v1` and prevent fake production metrics/data.
- [ ] Run component tests and build the frontend.

### Task 6: Verification and documentation

**Files:**
- Modify: `README.md`
- Create: `docs/ARCHITECTURE.md`
- Create: `docs/DEPLOYMENT.md`
- Create: `docs/UNINSTALL.md`

**Steps:**

- [ ] Run backend migrations, tests, lint/static checks available in the scaffold, and frontend tests/build.
- [ ] Verify the critical workflow: login → customer → billing account → plan → service → subscription → invoice → payment.
- [ ] Document native Linux/Nginx/PHP-FPM deployment using `wget` for the installer or release artifact.
- [ ] Document backup, restore, uninstall, and purge procedures without committing secrets.
- [ ] Inspect the final diff, initialize Git, commit, and prepare the GitHub push through the connected GitHub integration.
