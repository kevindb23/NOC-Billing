# Router Real Device Communication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the existing non-live Router drivers with a working Laravel-to-Python network-automation path for real multi-vendor monitoring and typed configuration operations.

**Architecture:** Laravel remains the authenticated orchestrator. It stores encrypted router credential profiles, authorizes generic operations, records operation/audit state, and dispatches requests to an internal Python FastAPI gateway. The Python gateway resolves a vendor driver and transport, reuses persistent device sessions, reconnects safely after disconnects, and returns normalized redacted results.

**Tech Stack:** Laravel/PHP, MySQL, Laravel queues and HTTP client, React/TypeScript/Vite, Python 3.11+, FastAPI, Pydantic, Netmiko, Paramiko, PyEZ, ncclient, httpx, PySNMP, pytest.

**Spec:** `docs/superpowers/specs/2026-09-13-router-real-device-communication-design.md`

## Global Constraints

- Laravel remains the vendor-neutral business and authorization layer.
- The Python gateway is the only component that connects to devices.
- Credentials are stored in MySQL using Laravel encrypted casts and are never serialized to React, URLs, logs, or operation results.
- Supported preferred transports are `api`, `ssh`, `netconf`, and `snmp`; `mock` is not selectable or accepted for real operations.
- Drivers are selected through a registry and expose capabilities.
- Transports own connection lifecycle; drivers own vendor commands, API payloads, and parsers.
- Persistent sessions remain in Python process memory and are never stored in MySQL or Redis.
- Configuration changes use typed operations; no arbitrary CLI command endpoint is allowed.
- No new table or API may add `organization_id`.
- Every implementation task follows TDD: write a failing test, run it, implement the smallest change, rerun the focused test, then run the relevant suite.
- Preserve unrelated existing worktree changes.

---

### Task 1: Create the Python automation gateway foundation

**Files:**
- Create: `network-automation/pyproject.toml`
- Create: `network-automation/src/network_automation/__init__.py`
- Create: `network-automation/src/network_automation/app.py`
- Create: `network-automation/src/network_automation/config.py`
- Create: `network-automation/src/network_automation/contracts/operations.py`
- Create: `network-automation/src/network_automation/contracts/results.py`
- Create: `network-automation/src/network_automation/contracts/devices.py`
- Create: `network-automation/src/network_automation/security/redaction.py`
- Create: `network-automation/tests/test_app.py`
- Create: `network-automation/tests/test_redaction.py`

**Interfaces:**
- Produces `OperationRequest`, `OperationResult`, `DeviceTarget`, and `DeviceCredentials` Pydantic models for all later Python tasks.
- Produces `create_app()` and `GET /health` for Laravel health checks.
- Produces redaction functions used by every transport, driver, and API handler.

- [ ] **Step 1: Write failing contract and health tests.**

```python
def test_operation_request_rejects_arbitrary_cli_commands():
    with pytest.raises(ValidationError):
        OperationRequest(operation="run_command", parameters={"command": "show secret"})

def test_health_endpoint_returns_ready():
    response = TestClient(create_app()).get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}
```

- [ ] **Step 2: Run the focused tests and verify they fail.**

Run: `cd network-automation && python -m pytest tests/test_app.py tests/test_redaction.py -q`

Expected: FAIL because the package, models, and application do not exist.

- [ ] **Step 3: Implement the contract models and FastAPI application.**

Define the allowed operation literal values, transport literal values, typed
device target fields, credential fields, correlation ID, and normalized result
shape. Reject unknown operations and prohibit raw credential serialization.
Implement `create_app()` with a health route and dependency injection points for
the driver and transport registries.

- [ ] **Step 4: Implement centralized redaction.**

Redact keys and nested values matching password, private key, token, community,
secret, cookie, authorization, and credential names. Preserve safe metadata and
correlation IDs. Add tests for nested dictionaries, lists, exception messages,
and command payloads.

- [ ] **Step 5: Run the focused tests and commit.**

Run: `cd network-automation && python -m pytest tests/test_app.py tests/test_redaction.py -q`

Expected: PASS.

Commit: `git add network-automation && git commit -m "feat: scaffold router automation gateway"`

---

### Task 2: Add encrypted router credentials and operation persistence

**Files:**
- Create: `backend/database/migrations/2026_09_13_000021_create_router_credentials_table.php`
- Create: `backend/database/migrations/2026_09_13_000022_create_router_operations_table.php`
- Create: `backend/app/Models/RouterCredential.php`
- Create: `backend/app/Models/RouterOperation.php`
- Modify: `backend/app/Models/Router.php`
- Create: `backend/tests/Feature/RouterCredentialPersistenceTest.php`
- Create: `backend/tests/Feature/RouterOperationPersistenceTest.php`

**Interfaces:**
- `Router::credentials()` returns `HasMany<RouterCredential>`.
- `Router::operations()` returns `HasMany<RouterOperation>`.
- `RouterCredential` exposes `public_id`, metadata, and encrypted attributes only through guarded resource serialization.
- `RouterOperation` exposes normalized lifecycle fields and casts JSON payloads to arrays.

- [ ] **Step 1: Write failing migration/model tests.**

Test that credentials can be stored and read by Laravel internally, raw database
values differ from plaintext secrets, credentials are never present in a model's
array/JSON output, and an operation can reference a router and requesting user.
Test that deleting a router cascades credential profiles but restricts deletion
when operation history exists unless the chosen archive policy permits it.

- [ ] **Step 2: Run the focused tests and verify they fail.**

Run: `cd backend && php artisan test --filter='RouterCredentialPersistenceTest|RouterOperationPersistenceTest' --do-not-cache-result`

Expected: FAIL because the tables and models do not exist.

- [ ] **Step 3: Create the migrations.**

Create `router_credentials` with a foreign key to `routers`, ULID `public_id`,
encrypted text columns for username/password/private key/passphrase/token/
community/TLS material, encrypted JSON for SNMP security, `is_primary`, and
credential `version`. Add a unique primary-profile constraint strategy that is
valid for MySQL.

Create `router_operations` with router/user foreign keys, operation/driver/
transport, sanitized parameters, status, normalized result, stable error fields,
unique correlation ID, timestamps, and duration. Do not add organization data.

- [ ] **Step 4: Implement models and relationships.**

Use Laravel encrypted casts for all secret columns and `encrypted:array` for
structured security data. Hide all secret fields, define fillable non-secret
attributes explicitly, and add status/operation casts or constants. Ensure
operation snapshots do not accidentally include credentials.

- [ ] **Step 5: Run migrations and tests.**

Run: `cd backend && php artisan migrate --force --no-interaction`

Then run the focused test command from Step 2. Expected: PASS.

- [ ] **Step 6: Commit.**

Commit: `git add backend/database/migrations backend/app/Models backend/tests/Feature/RouterCredentialPersistenceTest.php backend/tests/Feature/RouterOperationPersistenceTest.php && git commit -m "feat: persist encrypted router credentials and operations"`

---

### Task 3: Validate transport-specific credentials in Laravel

**Files:**
- Create: `backend/app/Http/Requests/RouterCredentialRequest.php`
- Create: `backend/app/Services/RouterCredentialService.php`
- Modify: `backend/app/Http/Requests/StoreRouterRequest.php`
- Modify: `backend/app/Http/Requests/UpdateRouterRequest.php`
- Modify: `backend/app/Http/Controllers/Api/V1/RouterController.php`
- Modify: `backend/app/Models/Router.php`
- Create: `backend/tests/Feature/RouterCredentialApiTest.php`

**Interfaces:**
- `RouterCredentialService::storeOrReplace(Router $router, array $validated): RouterCredential`.
- `RouterCredentialService::metadata(RouterCredential $credential): array`.
- `RouterCredentialRequest::rulesForTransport(string $transport): array`.
- Router create/update accepts a nested `credential_profile` payload and returns only credential metadata.

- [ ] **Step 1: Write failing API validation tests.**

Cover these cases:

```php
it('requires username and password for ssh', function (): void {
    postJson('/api/v1/routers', routerPayload([
        'preferred_transport' => 'ssh',
        'credential_profile' => ['name' => 'primary'],
    ]))->assertUnprocessable();
});

it('requires username and password for netconf', function (): void {
    postJson('/api/v1/routers', routerPayload([
        'preferred_transport' => 'netconf',
        'credential_profile' => ['name' => 'primary'],
    ]))->assertUnprocessable();
});

it('never returns credential secrets', function (): void {
    $response = postJson('/api/v1/routers', routerPayload([
        'preferred_transport' => 'ssh',
        'credential_profile' => [
            'name' => 'primary', 'username' => 'netadmin', 'password' => 'secret',
        ],
    ]))->assertCreated();

    expect($response->json('data.credential_profile.password'))->toBeNull();
    expect(json_encode($response->json()))->not->toContain('secret');
});
```

Also test API token validation, SNMP community validation, update-without-
replacing-secret semantics, transport changes that require new credentials,
and rejection of `mock`.

- [ ] **Step 2: Run the focused tests and verify they fail.**

Run: `cd backend && php artisan test --filter=RouterCredentialApiTest --do-not-cache-result`

Expected: FAIL because credential validation and persistence are not wired.

- [ ] **Step 3: Implement transport-aware request validation.**

Allow only `api`, `ssh`, `netconf`, and `snmp`. For SSH and NETCONF require
username and password on create; allow blank secret fields on update only when a
matching stored secret exists. Validate ports and transport-specific fields.
Reject `mock` with a validation error.

- [ ] **Step 4: Implement the credential service and controller integration.**

Wrap router and credential creation/update in one transaction. Increment the
credential version when any secret changes. Return `credential_configured`,
profile metadata, and version, never decrypted values. Record audit snapshots
containing only non-secret metadata.

- [ ] **Step 5: Add the transport default migration.**

Create a migration that changes the database default from `mock` to `api` and
converts existing `mock` rows to `api` so old records cannot select an invalid
transport. Require credentials before a real operation can run.

- [ ] **Step 6: Run tests, migration, and commit.**

Run the focused test command, then:

```bash
cd backend && php artisan migrate --force --no-interaction
php artisan test --filter='RouterCredentialApiTest|RouterApiTest' --do-not-cache-result
```

Expected: PASS.

Commit: `git add backend && git commit -m "feat: validate transport credentials"`

---

### Task 4: Implement Python transport adapters and persistent sessions

**Files:**
- Create: `network-automation/src/network_automation/transports/base.py`
- Create: `network-automation/src/network_automation/transports/ssh.py`
- Create: `network-automation/src/network_automation/transports/netconf.py`
- Create: `network-automation/src/network_automation/transports/api.py`
- Create: `network-automation/src/network_automation/transports/snmp.py`
- Create: `network-automation/src/network_automation/session_manager.py`
- Create: `network-automation/src/network_automation/transports/registry.py`
- Create: `network-automation/tests/test_session_manager.py`
- Create: `network-automation/tests/test_transport_registry.py`
- Create: `network-automation/tests/transports/test_ssh.py`
- Create: `network-automation/tests/transports/test_netconf.py`
- Create: `network-automation/tests/transports/test_api.py`
- Create: `network-automation/tests/transports/test_snmp.py`

**Interfaces:**
- `Transport.connect(target, credentials) -> DeviceSession`.
- `Transport.is_healthy(session) -> bool`.
- `Transport.close(session) -> None`.
- `SessionManager.get_or_connect(target, credentials, transport) -> ManagedSession`.
- `SessionManager.invalidate(key) -> None`.
- `TransportRegistry.resolve(identifier) -> Transport`.

- [ ] **Step 1: Write failing session reuse/reconnect tests.**

Use fake transports and sessions to prove the first operation connects once,
the second operation reuses the session, an unhealthy session reconnects, a
credential version change invalidates the old session, idle sessions expire,
and unsafe writes are not retried after an uncertain result.

- [ ] **Step 2: Run the focused tests and verify they fail.**

Run: `cd network-automation && python -m pytest tests/test_session_manager.py tests/test_transport_registry.py -q`

Expected: FAIL because registries and session manager do not exist.

- [ ] **Step 3: Implement the transport protocol and session manager.**

Key sessions by `router_id + driver_id + transport + credential_version`.
Protect each key with an async lock. Apply connect, idle, command, and overall
timeouts. Reconnect once for safe read operations after a stale-session error;
never automatically retry a write with unknown commit state. Close sessions on
invalidation and worker shutdown.

- [ ] **Step 4: Implement real transport adapters behind testable wrappers.**

Use Netmiko/Paramiko for SSH, ncclient/PyEZ for NETCONF, httpx for API, and
PySNMP for SNMP. Keep vendor-independent connection setup in transports. Honor
host-key and TLS verification defaults. Convert library exceptions into the
stable error codes from the spec.

- [ ] **Step 5: Run transport tests and commit.**

Run: `cd network-automation && python -m pytest tests/test_session_manager.py tests/test_transport_registry.py tests/transports -q`

Expected: PASS using mocked libraries and fake sessions; no real device is
required.

Commit: `git add network-automation && git commit -m "feat: add persistent router transports"`

---

### Task 5: Implement vendor drivers and generic operations

**Files:**
- Create: `network-automation/src/network_automation/drivers/base.py`
- Create: `network-automation/src/network_automation/drivers/registry.py`
- Create: `network-automation/src/network_automation/drivers/juniper.py`
- Create: `network-automation/src/network_automation/drivers/mikrotik.py`
- Create: `network-automation/src/network_automation/drivers/cisco.py`
- Create: `network-automation/src/network_automation/drivers/linux_frr.py`
- Create: `network-automation/src/network_automation/drivers/parsers/juniper.py`
- Create: `network-automation/src/network_automation/drivers/parsers/mikrotik.py`
- Create: `network-automation/src/network_automation/drivers/parsers/cisco.py`
- Create: `network-automation/src/network_automation/drivers/parsers/linux_frr.py`
- Create: `network-automation/src/network_automation/drivers/configuration.py`
- Create: `network-automation/tests/drivers/test_registry.py`
- Create: `network-automation/tests/drivers/test_generic_operations.py`
- Create: `network-automation/tests/fixtures/juniper/*`
- Create: `network-automation/tests/fixtures/mikrotik/*`
- Create: `network-automation/tests/fixtures/cisco/*`
- Create: `network-automation/tests/fixtures/linux_frr/*`

**Interfaces:**
- `Driver.capabilities() -> set[str]`.
- `Driver.execute(request, session) -> OperationResult`.
- `DriverRegistry.resolve(driver_id) -> Driver`.
- Each parser returns normalized system/interface/route/BGP/traffic structures.

- [ ] **Step 1: Write failing registry, capability, and fixture tests.**

Verify each driver resolves by identifier, declares only supported operations,
rejects unsupported operations, parses representative fixture payloads, and
returns the same normalized keys regardless of vendor.

- [ ] **Step 2: Run the focused tests and verify they fail.**

Run: `cd network-automation && python -m pytest tests/drivers -q`

Expected: FAIL because drivers, parsers, and fixtures do not exist.

- [ ] **Step 3: Implement read-only generic operations.**

Implement `test_connection`, `get_system_info`, `get_device_facts`,
`get_interfaces`, `get_interface_status`, `get_routes`, `get_bgp_neighbors`,
and `get_traffic_counters`. Each driver delegates connection lifecycle to the
session manager and keeps vendor command/API/parser logic local to the driver.

- [ ] **Step 4: Implement typed configuration operations.**

Add validated request models for `validate_configuration`,
`preview_configuration`, `apply_configuration`, `commit_configuration`, and
`rollback_configuration`. Implement only configuration shapes required by the
first Router UI (interface status/description and VLAN/subinterface where the
driver declares support). Reject arbitrary command strings. Mark commit and
rollback capabilities independently.

- [ ] **Step 5: Run driver tests and commit.**

Run: `cd network-automation && python -m pytest tests/drivers -q`

Expected: PASS with deterministic fixtures and mocked sessions.

Commit: `git add network-automation && git commit -m "feat: add multi-vendor router drivers"`

---

### Task 6: Connect Laravel operations to the Python gateway

**Files:**
- Create: `backend/app/Services/NetworkAutomationClient.php`
- Create: `backend/app/Services/RouterOperationService.php`
- Create: `backend/app/Jobs/ExecuteRouterOperation.php`
- Create: `backend/app/Http/Requests/StoreRouterOperationRequest.php`
- Modify: `backend/app/Http/Controllers/Api/V1/RouterController.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/config/services.php`
- Create: `backend/tests/Feature/RouterOperationApiTest.php`
- Create: `backend/tests/Unit/NetworkAutomationClientTest.php`

**Interfaces:**
- `NetworkAutomationClient::execute(array $payload): array` posts to the configured internal gateway.
- `RouterOperationService::createAndDispatch(User $user, Router $router, array $data): RouterOperation`.
- `ExecuteRouterOperation::handle(NetworkAutomationClient $client): void`.
- `POST /api/v1/routers/{publicId}/operations` creates an operation.
- `GET /api/v1/routers/{publicId}/operations` lists operation history.
- `GET /api/v1/routers/{publicId}/operations/{operationPublicId}` returns status/result.
- Existing connection-test and system-info routes delegate to the generic operation service for compatibility.

- [ ] **Step 1: Write failing Laravel gateway and API tests.**

Fake the internal gateway and test that authorized requests create an operation,
send only the selected credential payload in memory, persist normalized results,
return correlation IDs, reject missing credentials/capabilities, and expose no
secret values. Test that configuration permissions are separate from monitoring
permissions and that failed gateway responses become stable operation errors.

- [ ] **Step 2: Run the focused tests and verify they fail.**

Run: `cd backend && php artisan test --filter='RouterOperationApiTest|NetworkAutomationClientTest' --do-not-cache-result`

Expected: FAIL because the routes, service, job, and client do not exist.

- [ ] **Step 3: Implement the configured internal HTTP client.**

Use Laravel's HTTP client with a base URL, service authentication header,
connect timeout, request timeout, and no credential logging. Validate the
gateway's normalized result schema before persisting it.

- [ ] **Step 4: Implement operation creation and execution.**

Load the primary credential profile, decrypt it only in the job process, build a
sanitized request payload, dispatch the queue job, and update queued/running/
succeeded/failed fields. Update `routers.last_contact_at` only after a real
successful connection/result. Never retry an operation automatically when the
operation is a write with unknown commit state.

- [ ] **Step 5: Add the generic routes and compatibility adapters.**

Add operation create/list/show routes and make current connection-test and
system-info routes call the same service with the corresponding generic
operation. Return `202` for queued operations and a safe `422`/`503` response
for validation or gateway availability failures.

- [ ] **Step 6: Run tests and commit.**

Run the focused command from Step 2 and the existing Router test group.

Commit: `git add backend/app backend/routes backend/config backend/tests && git commit -m "feat: dispatch router operations to automation gateway"`

---

### Task 7: Add permissions and role provisioning

**Files:**
- Modify: `backend/database/seeders/PermissionSeeder.php`
- Create: `backend/database/migrations/2026_09_13_000024_add_router_operation_permissions.php`
- Modify: `backend/tests/Feature/RouterPermissionApiTest.php`
- Modify: `backend/tests/Feature/RouterRoleApiTest.php`

**Interfaces:**
- Permissions: `routers.credentials.view`, `routers.credentials.manage`, `routers.operations.view`, `routers.monitor`, `routers.configuration.preview`, `routers.configuration.apply`, `routers.configuration.commit`, `routers.configuration.rollback`.

- [ ] **Step 1: Write failing permission tests.**

Verify a monitoring user can run read operations but cannot apply configuration,
a credential manager can replace profiles but cannot commit configuration, and
superadmin behavior remains unchanged. Verify role permission groups include the
new Router operation permissions.

- [ ] **Step 2: Run tests and verify failure.**

Run: `cd backend && php artisan test --filter='RouterPermissionApiTest|RouterRoleApiTest' --do-not-cache-result`

Expected: FAIL because new permission names do not exist.

- [ ] **Step 3: Add seed/migration permissions and route middleware.**

Seed the new permissions idempotently, provision them for the existing
administrator role, and protect each endpoint with the narrowest permission.

- [ ] **Step 4: Run tests and commit.**

Run the focused permission tests plus the complete backend Router feature group.

Commit: `git add backend/database backend/tests/Feature/RouterPermissionApiTest.php backend/tests/Feature/RouterRoleApiTest.php && git commit -m "feat: authorize router operations"`

---

### Task 8: Implement the transport-aware Router form

**Files:**
- Create: `frontend/src/components/RouterCredentialFields.tsx`
- Create: `frontend/src/components/RouterOperationPanel.tsx`
- Modify: `frontend/src/components/RoutersPage.tsx`
- Modify: `frontend/src/components/RoutersPage.test.tsx`

**Interfaces:**
- `RouterCredentialFields` receives `transport`, `values`, `configured`, and an `onChange` callback.
- `RouterOperationPanel` receives router, capabilities, permissions, and operation callbacks.
- Router form submit sends inventory fields plus a nested credential profile without displaying returned secrets.

- [ ] **Step 1: Write failing React tests.**

Test that selecting SSH shows username/password/port, selecting NETCONF shows
username/password/port, selecting API shows API credential fields, selecting
SNMP shows SNMP fields, and `MOCK` is absent. Test that changing transport clears
unsaved transport-only fields. Test that an edit displays configured metadata
but never a secret value. Test operation buttons render only when permissions
and returned capabilities allow them.

- [ ] **Step 2: Run the focused tests and verify failure.**

Run: `cd frontend && npm test -- --run src/components/RoutersPage.test.tsx`

Expected: FAIL because the dynamic credential fields and operation panel do not
exist and `MOCK` is still in the current static options.

- [ ] **Step 3: Implement the dedicated credential fields.**

Use simple native dropdowns with the established caret styling. Mask existing
secrets, never preload decrypted values, mark SSH/NETCONF username/password
required, default SSH port to 22 and NETCONF port to 830, and show optional key
fields only for SSH. Use the modal's wider grid so fields do not create an
unintended scrollbar.

- [ ] **Step 4: Wire create/edit persistence and safe errors.**

Submit nested credential data, display validation messages beside the relevant
transport field, refresh the router row after success, and show a safe message
with the correlation ID for gateway/operation errors.

- [ ] **Step 5: Add generic monitoring/configuration controls.**

Add connection test, system information, interfaces, routes, BGP, and traffic
actions based on capabilities. Add preview/apply/commit/rollback controls only
when permission and capability checks both pass. Display operation status and
normalized data in tables; never show raw secrets or arbitrary device output.

- [ ] **Step 6: Run frontend tests and build.**

Run:

```bash
cd frontend
npm test -- --run
npm run build
```

Expected: all tests pass and the production build succeeds.

Commit: `git add frontend/src/components/RoutersPage.tsx frontend/src/components/RouterCredentialFields.tsx frontend/src/components/RouterOperationPanel.tsx frontend/src/components/RoutersPage.test.tsx && git commit -m "feat: add transport credentials and router operations UI"`

---

### Task 9: Add service deployment configuration and operational documentation

**Files:**
- Create: `network-automation/.env.example`
- Create: `network-automation/README.md`
- Create: `network-automation/Dockerfile`
- Modify: `backend/.env.example`
- Modify: `docs/ROUTER_MODULE.md`
- Modify: `docs/MULTI_VENDOR.md`
- Create: `docs/NETWORK_AUTOMATION.md`
- Create: `network-automation/tests/test_configuration.py`

**Interfaces:**
- Environment configuration includes gateway bind address, Laravel service auth, session timeouts, transport timeouts, retry limits, and log redaction mode.
- Deployment documentation explains local fake-device testing and lab-only live-device smoke tests.

- [ ] **Step 1: Write failing configuration tests.**

Verify required service-auth configuration fails closed when missing, secure host
key/TLS defaults are enabled, write retries default to zero, and session idle/
connect/command timeouts have bounded positive defaults.

- [ ] **Step 2: Implement configuration and container files.**

Add typed settings, a non-root Python container, healthcheck, no secret defaults,
and a documented internal network connection to Laravel. Do not put device
credentials in the Python environment.

- [ ] **Step 3: Update documentation.**

Document driver/transport separation, persistent session lifecycle, credential
form behavior, generic operations, capability gating, configuration safety,
permission names, and the commands for fake-device tests.

- [ ] **Step 4: Run configuration tests and commit.**

Run: `cd network-automation && python -m pytest tests/test_configuration.py -q`

Commit: `git add network-automation/.env.example network-automation/README.md network-automation/Dockerfile backend/.env.example docs/ROUTER_MODULE.md docs/MULTI_VENDOR.md docs/NETWORK_AUTOMATION.md network-automation/tests/test_configuration.py && git commit -m "docs: document router automation deployment"`

---

### Task 10: Full verification and live-safe handoff

**Files:**
- Modify only files required by failing verification tests.
- Test: `backend/tests/Feature/RouterCredentialPersistenceTest.php`
- Test: `backend/tests/Feature/RouterCredentialApiTest.php`
- Test: `backend/tests/Feature/RouterOperationApiTest.php`
- Test: `frontend/src/components/RoutersPage.test.tsx`
- Test: `network-automation/tests/`

- [ ] **Step 1: Run Python verification.**

Run: `cd network-automation && python -m pytest -q`

Expected: all Python tests pass without contacting a real device.

- [ ] **Step 2: Run Laravel verification.**

Run:

```bash
cd backend
php artisan migrate --force --no-interaction
php artisan test --do-not-cache-result
find app -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: migrations complete, all backend tests pass, and PHP lint reports no
syntax errors.

- [ ] **Step 3: Run frontend verification.**

Run:

```bash
cd frontend
npm test -- --run
npm run build
```

Expected: all frontend tests pass and the production build succeeds.

- [ ] **Step 4: Run repository hygiene checks.**

Run: `git diff --check`

Search for secrets and forbidden behavior:

```bash
rg -n "password|private_key|api_token|snmp_community|run_command|organization_id" network-automation backend/app/Http backend/app/Services frontend/src/components/RoutersPage.tsx
```

Review every match to confirm secrets are encrypted/masked and no arbitrary
command or new organization dependency was introduced.

- [ ] **Step 5: Run opt-in lab smoke tests only when configured.**

Require explicit environment variables for a lab router target and a separate
confirmation flag. Test connection, one read operation, preview, and one
approved configuration operation. Never run these tests against production by
default.

- [ ] **Step 6: Review and hand off.**

Use the verification results to report exact pass/fail evidence, migrations
applied, service startup commands, configured transports, and any driver
capabilities not yet supported. Do not claim real-device readiness without a
successful configured lab smoke test.
