# Router Real Device Communication Design

**Date:** 2026-09-13  
**Status:** Approved architecture; implementation specification for review  
**Scope:** Real multi-vendor router monitoring and configuration through a Python network-automation service

## Goal

Replace the Router module's placeholder drivers with a production-oriented,
vendor-neutral communication boundary that supports real device monitoring and
configuration for Juniper, MikroTik, Cisco, Linux/FRR, and future vendors.

The system must support API, SSH, NETCONF, and SNMP transports. Device
credentials remain in the database, encrypted at rest, and are never exposed to
React or returned from public APIs.

## Requirements from `MASTER_V2.md`

- Laravel remains the orchestrator and business system.
- Vendor drivers are resolved through a manager and registry.
- Transport is separate from the driver.
- Generic operations are used instead of vendor branches in controllers.
- Vendor command builders and parsers remain inside vendor implementations.
- Drivers expose capabilities; unsupported operations are not presented or run.
- Frontend code never connects directly to devices.
- Device credentials must never be exposed in frontend responses or logs.
- The design is installation-wide and does not add `organization_id`.

## Architecture

```text
React
  |
  v
Laravel API and RouterManager
  |  authorization, capabilities, operation records, audit
  v
Python Network Automation Gateway
  |
  +-- DriverRegistry
  |     +-- JuniperDriver
  |     +-- MikroTikDriver
  |     +-- CiscoDriver
  |     +-- LinuxFrrDriver
  |
  +-- TransportRegistry
        +-- ApiTransport
        +-- SshTransport
        +-- NetconfTransport
        +-- SnmpTransport
```

Laravel owns user authentication, permission checks, router inventory,
encrypted credential persistence, operation records, audit records, and API
responses. The Python service owns device sessions, driver execution,
transport handling, vendor command generation, response parsing, retries, and
connection health.

The Python service is an internal service. Laravel authenticates service
requests using a configured service credential or signed request mechanism.
The Python service does not expose a public browser-facing endpoint.

## Database model

### `router_credentials`

Create a one-to-many credential profile table so a router can later have a
primary profile and a rotation candidate without changing the router table.

Fields:

- `id` bigint primary key
- `public_id` ULID unique public identifier
- `router_id` foreign key to `routers.id`, cascade on router deletion
- `name` string
- `auth_type` enum-like string: `password`, `private_key`, `token`, `community`, `mixed`
- `username` nullable encrypted text
- `password` nullable encrypted text
- `private_key` nullable encrypted text
- `private_key_passphrase` nullable encrypted text
- `api_token` nullable encrypted text
- `snmp_community` nullable encrypted text
- `snmp_security` nullable encrypted JSON
- `known_hosts` nullable encrypted text
- `tls_ca_certificate` nullable encrypted text
- `tls_client_certificate` nullable encrypted text
- `tls_client_key` nullable encrypted text
- `is_primary` boolean default false
- `version` unsigned integer default 1
- `last_used_at` nullable timestamp
- `created_at`, `updated_at`

Only one active primary profile is allowed per router. API resources expose
credential metadata only: profile name, auth type, primary status, version, and
timestamps. Secret values are never serialized.

### `router_operations`

Persist both monitoring and configuration operation lifecycle state.

Fields:

- `id` bigint primary key
- `public_id` ULID unique public identifier
- `router_id` foreign key to `routers.id`, restrict deletion while operations exist
- `requested_by` nullable foreign key to `users.id`, set null on user deletion
- `operation` string
- `transport` string
- `driver` string
- `parameters` JSON, sanitized before persistence
- `status` enum-like string: `queued`, `running`, `succeeded`, `failed`, `cancelled`
- `result` nullable JSON, normalized and redacted
- `error_code` nullable string
- `error_message` nullable text, redacted
- `correlation_id` string unique
- `started_at` nullable timestamp
- `finished_at` nullable timestamp
- `duration_ms` nullable unsigned integer
- `created_at`, `updated_at`

Configuration request parameters must never contain raw credentials. Sensitive
configuration values must be redacted before the request is persisted.

### Existing `routers` fields

Reuse the existing driver, preferred transport, status, capability, and last
contact fields. The preferred transport dropdown remains limited to `api`,
`ssh`, `netconf`, and `snmp`; `mock` is removed from the UI and cannot be used
for real-operation API requests.

## Generic operation contract

The Laravel-to-Python request contract is:

```json
{
  "operation": "get_interfaces",
  "router_id": "01...",
  "driver": "juniper_router",
  "transport": "netconf",
  "credential_profile_id": "01...",
  "parameters": {},
  "correlation_id": "router-op-..."
}
```

Initial generic operations:

Read operations:

- `test_connection`
- `get_system_info`
- `get_device_facts`
- `get_interfaces`
- `get_interface_status`
- `get_routes`
- `get_bgp_neighbors`
- `get_traffic_counters`

Configuration operations:

- `validate_configuration`
- `preview_configuration`
- `apply_configuration`
- `commit_configuration`
- `rollback_configuration`

Configuration operations are capability-gated. The first implementation must
not expose a generic arbitrary-command endpoint. Each configuration operation
accepts a typed, validated request model and is translated by the vendor
driver's command builder or API adapter.

Normalized response:

```json
{
  "status": "succeeded",
  "operation": "get_interfaces",
  "router_id": "01...",
  "device": {
    "vendor": "Juniper",
    "model": "MX"
  },
  "data": [],
  "warnings": [],
  "error": null,
  "started_at": "2026-09-13T12:00:00Z",
  "finished_at": "2026-09-13T12:00:01Z"
}
```

## Driver and transport contracts

Drivers implement normalized operations and declare capabilities. Drivers do
not duplicate connection lifecycle logic.

Conceptual Python interfaces:

```python
class RouterDriver(Protocol):
    identifier: str

    def capabilities(self) -> set[str]: ...
    def execute(self, request: OperationRequest,
                session: DeviceSession) -> OperationResult: ...
```

```python
class Transport(Protocol):
    identifier: str

    def connect(self, target: DeviceTarget,
                credentials: DeviceCredentials) -> DeviceSession: ...
    def is_healthy(self, session: DeviceSession) -> bool: ...
    def close(self, session: DeviceSession) -> None: ...
```

Initial Python package mapping:

- SSH: Netmiko first, Paramiko for lower-level cases where needed
- Juniper NETCONF: PyEZ and/or ncclient according to operation support
- REST/API: httpx or requests with connection pooling
- SNMP: PySNMP
- Parsing: typed vendor parsers, with deterministic fixture tests

The exact package used is an implementation detail of the transport or driver;
Laravel never depends on Python package names.

## Persistent sessions and reconnects

The Python service owns an in-memory connection manager. Laravel does not hold
live device sessions and sessions are never stored in MySQL or Redis.

Session cache key:

```text
router_id + driver_id + transport + credential_version
```

Behavior:

1. The first operation authenticates and creates a session.
2. Later operations reuse a healthy session.
3. Idle sessions expire after a configured idle timeout.
4. Health checks detect closed or unusable sessions.
5. A failed operation caused by a stale session closes it, reconnects once, and
   retries only when the operation is safe to retry.
6. Configuration writes are never blindly retried after an unknown commit
   result.
7. Credential version changes invalidate all matching sessions.
8. Sessions are closed during worker shutdown.
9. Per-router locking serializes configuration operations.
10. A configurable per-router session limit prevents resource exhaustion.

Persistent sessions make subsequent commands fast while preserving a safe
first-command/reconnect path.

## Configuration safety

Configuration operations must include:

- a separate permission from read-only monitoring;
- capability checks;
- typed input validation;
- vendor-specific command/API generation inside the driver;
- preview or validation before apply when supported;
- explicit commit/apply behavior;
- no automatic retry after uncertain write completion;
- operation and audit records;
- redacted before/after metadata;
- rollback support where the driver declares it;
- concurrency locking per router.

The service rejects unsupported operations with a normalized capability error.
It rejects arbitrary CLI strings and unrecognized configuration fields.

## Error handling

Errors are normalized into stable codes:

- `unsupported_driver`
- `unsupported_transport`
- `unsupported_operation`
- `credentials_missing`
- `authentication_failed`
- `host_key_rejected`
- `tls_verification_failed`
- `connection_timeout`
- `command_timeout`
- `device_unreachable`
- `device_rejected_request`
- `parse_failed`
- `configuration_validation_failed`
- `configuration_commit_unknown`
- `rate_limited`
- `automation_service_unavailable`

The browser receives a safe human-readable message and correlation ID. Raw
vendor payloads, passwords, private keys, tokens, communities, and sensitive
command content are not returned.

## API and permissions

Laravel adds authenticated endpoints for:

- credential profile create/update/delete;
- test connection;
- capability discovery;
- operation create;
- operation status/result;
- operation cancellation where safe;
- operation history.

Permission groups include separate abilities for:

- viewing routers;
- managing routers;
- managing router credentials;
- running router monitoring operations;
- previewing configuration;
- applying configuration;
- committing configuration;
- rolling back configuration;
- viewing router operation history.

React displays only operations allowed by both permissions and driver
capabilities. The router view uses generic operation labels and normalized
results rather than vendor-specific controller logic.

## Monitoring model

Manual monitoring actions are supported first through the operation endpoint.
The same operation contract can later be dispatched by scheduled polling jobs.

Polling must use bounded concurrency, per-router rate limits, timeout budgets,
and last-known-state timestamps. A failed poll must not overwrite a previous
healthy result with fabricated values; it records the failure and updates the
last-contact/error metadata.

## Testing strategy

Laravel tests:

- encrypted credential storage and secret redaction;
- credential resources never serialize secrets;
- permission checks for every operation class;
- capability checks before dispatch;
- request validation and rejection of arbitrary commands;
- operation lifecycle persistence;
- normalized automation-service success and failure responses;
- audit records for configuration changes;
- no `organization_id` dependency.

Python tests:

- transport contract tests with fake sessions;
- persistent session reuse;
- idle expiration;
- reconnect after disconnect;
- credential-version invalidation;
- safe retry rules;
- per-router write locking;
- driver registry resolution;
- capability filtering;
- Juniper, MikroTik, Cisco, and Linux/FRR parser fixtures;
- API, SSH, NETCONF, and SNMP adapter tests using mocked devices;
- configuration preview/validate/apply/commit/rollback flows;
- redaction tests for logs and responses.

End-to-end tests use a local fake device service and must not require access to
production routers or real credentials. Live-device smoke tests, if added,
must be opt-in and run only against an explicitly configured lab target.

## Rollout boundaries

The first implementation must deliver the Python service boundary, encrypted
credential profiles, persistent session manager, real connection testing, and
the initial generic read operations. Configuration operations must include the
typed safety boundary and a working driver implementation before being exposed
in the UI.

No arbitrary command execution, Telnet fallback, browser-to-device connection,
or credential exposure is part of this scope.

