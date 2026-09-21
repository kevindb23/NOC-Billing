# Laravel Activation Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Move the Activation page to Laravel Blade/Livewire, serve it through Nginx and PHP-FPM, and remove Vite/Node from production without losing the existing activation workflow.

**Architecture:** Laravel owns the page, authorization, queries, validation, and mutations. Livewire handles server-backed interaction and Alpine handles local modal state. Nginx serves Laravel through PHP-FPM and the React frontend remains only as a temporary rollback reference until parity is verified.

**Tech Stack:** Laravel, Blade, Livewire, Alpine.js, PHP-FPM, Nginx, MySQL, Redis.

**Spec:** `docs/superpowers/specs/2026-09-21-laravel-activation-migration-design.md`

## Global Constraints

- No Vite or Node service is used in production.
- No database reset or destructive schema migration is allowed.
- Remote ACS, OLT, BNG, and RADIUS operations must not run during page rendering.
- Existing API endpoints remain available during staged migration.
- Credentials and raw device output must not be exposed in page errors or logs.

## Review Focus

- Unauthorized users cannot access or mutate activation records.
- Empty databases render a usable empty state instead of failing on missing relationships.
- Paginated records do not trigger N+1 queries.
- Preview failures do not create partial activation records.
- Deactivation and deletion preserve the existing remote cleanup order.

### Task 1: Establish Laravel page shell and runtime delivery

**Files:**
- Create/modify Laravel web route and Blade layout under `backend/routes/web.php` and `backend/resources/views/`.
- Modify `deploy/nginx-noc-billing.conf` and deployment documentation.
- Test `backend/tests/Feature/ActivationPageTest.php`.

- [ ] Add a protected `/activation` web route that renders a Laravel page shell.
- [ ] Add an authenticated feature test asserting the route is reachable and unauthorized users are redirected or rejected.
- [ ] Configure Nginx to serve the Laravel application and public assets through the detected PHP-FPM socket.
- [ ] Disable the Vite and artisan development-server services in deployment definitions.
- [ ] Verify `nginx -t`, PHP-FPM status, and the authenticated page response.

### Task 2: Add the Livewire activation history component

**Files:**
- Create `backend/app/Livewire/Activation/ActivationPage.php`.
- Create `backend/resources/views/livewire/activation/activation-page.blade.php`.
- Test `backend/tests/Feature/Livewire/ActivationPageTest.php`.

- [ ] Implement paginated history with explicit eager-loaded relationships matching the current API response.
- [ ] Add empty, loading, and error-safe states.
- [ ] Add tests for permission checks, pagination, and an empty database.
- [ ] Verify query count and response data with Laravel feature tests.

### Task 3: Migrate activation form, preview, and mutations

**Files:**
- Modify the Livewire component and Blade view from Task 2.
- Reuse `backend/app/Http/Controllers/Api/V1/ActivationController.php` domain behavior or extract a focused application service.
- Test `backend/tests/Feature/Livewire/ActivationWorkflowTest.php`.

- [ ] Implement validated subscriber, plan, ONT, OLT, preset, VLAN, and QinQ selections.
- [ ] Call preview logic without provisioning devices.
- [ ] Call the existing activation workflow for creation.
- [ ] Implement confirmation-protected deactivation and deletion using the existing cleanup order.
- [ ] Test validation failures, preview failures, successful creation, deactivation, and deletion.

### Task 4: Remove Vite and React runtime dependencies after parity

**Files:**
- Modify deployment scripts and service definitions under `deploy/`.
- Remove or archive frontend runtime entrypoints only after Tasks 1–3 pass.
- Update `README.md`.

- [ ] Remove Node/Vite installation and runtime-service requirements from the installer.
- [ ] Ensure Nginx serves the migrated Laravel pages on ports 80 and 3000 without a frontend proxy.
- [ ] Keep the React source outside the runtime path until the migrated page is accepted.
- [ ] Update installation and troubleshooting instructions.
- [ ] Run the complete Laravel test suite and verify the activation page manually through Nginx.

### Task 5: Commit and deployment verification

- [ ] Run `php artisan test` and the focused activation tests.
- [ ] Run `nginx -t` using the generated site configuration.
- [ ] Verify PHP-FPM, queue, session services, and Nginx status.
- [ ] Commit each completed task separately with focused messages.
