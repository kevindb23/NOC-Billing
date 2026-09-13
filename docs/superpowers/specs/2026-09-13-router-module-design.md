# Router Module Design

## Status

Approved direction for implementation planning.

## Goal

Build a working Router inventory module for the dedicated single-installation ISP system. The module must support MikroTik, Juniper, Cisco, Linux/FRR, and future vendors through a vendor-neutral driver boundary without changing billing, subscriber, or provisioning business logic.

## Architectural constraints

- This installation is not organization-based and must not add organization_id, organization middleware, tenant filtering, or organization-scoped uniqueness.
- MySQL remains the authoritative source of Router inventory.
- React communicates only with Laravel APIs; it never connects directly to routers, SSH, SNMP, Redis, or MySQL.
- Controllers and business services use capabilities and normalized DTOs, never vendor CLI/API syntax.
- Vendor drivers are selected by a stored driver identifier through RouterDriverRegistry.
- The first slice must be useful and safe: inventory CRUD, driver resolution, capability reporting, and a normalized connection-test result.
- Real device communication is not fabricated. Until a transport is configured, drivers return NOT_CONFIGURED or UNSUPPORTED.
- No disconnected tables are introduced. The routers table relates to the existing users table through created_by; future site/POP relations are deferred until those modules exist.

## Scope

Included:

- Router inventory persistence with no organization columns.
- Router CRUD API under /api/v1/routers.
- Generic Router model and validation.
- RouterDriverInterface, normalized result DTOs, RouterDriverRegistry, and RouterManager.
- Initial driver registrations for MikroTik, Juniper, Cisco, and Linux/FRR using safe unavailable/mock implementations.
- Capability-based operation checks.
- Connection-test endpoint returning normalized results.
- Router permissions in the existing Roles permission matrix.
- Router audit events.
- React Router inventory page with table, add/edit/view dialogs, status, vendor, driver, transport, capabilities, and connection-test state.
- Backend, frontend, and API authorization tests.

Excluded from this slice:

- Live SSH, REST, NETCONF, SNMP, or vendor CLI communication.
- Router configuration changes, BGP changes, VLAN/subinterface changes, route changes, or prefix-policy changes.
- Sites, POPs, BNGs, IPAM, VLAN, monitoring, alarms, or provisioning jobs.
- Storing router passwords, SNMP communities, private keys, or API tokens.

## Persistence design

Create one table: routers.

Relationships:

- routers.created_by references users.id with nullable nullOnDelete.
- The existing audit log remains the history mechanism; no router-specific audit table is created.

Fields:

- id bigint primary key.
- public_id ULID unique.
- name unique installation-wide.
- hostname nullable string.
- management_ip nullable validated IP address.
- vendor enum-like string: mikrotik, juniper, cisco, linux_frr, other.
- model nullable string.
- software_version nullable string.
- serial_number nullable string.
- driver string, for example mikrotik_router.
- preferred_transport enum-like string: api, ssh, netconf, snmp, mock.
- status enum-like string: active, inactive, maintenance, unknown.
- capabilities JSON object.
- last_contact_at nullable timestamp.
- last_synchronized_at nullable timestamp.
- notes nullable text.
- metadata nullable JSON object.
- created_by nullable foreign key to users.
- timestamps and soft deletes.

Driver definitions are code registrations, not database rows. This prevents an untrusted database value from instantiating arbitrary PHP classes.

## Driver architecture

The application uses these boundaries:

RouterController -> RouterManager -> RouterDriverRegistry -> RouterDriverInterface -> vendor driver

Initial normalized contract:

    interface RouterDriverInterface
    {
        public function identifier(): string;
        public function capabilities(): array;
        public function testConnection(Router $router): ConnectionResult;
        public function getSystemInfo(Router $router): DeviceStatusResult;
    }

RouterManager resolves drivers and rejects unknown identifiers. Drivers return normalized DTOs with stable fields such as status, message, vendor, driver, checked_at, and optional normalized device information.

Future operations are added only when needed:

- getInterfaces()
- getInterfaceStatus()
- getBgpNeighbors()
- getRoutes()
- getTrafficCounters()
- applyPrefixPolicy()
- configureVlan()
- configureSubinterface()

Unsupported operations are represented by capabilities and controlled results, not by vendor checks in controllers.

## API

Routes:

- GET /api/v1/routers
- POST /api/v1/routers
- GET /api/v1/routers/{publicId}
- PUT /api/v1/routers/{publicId}
- DELETE /api/v1/routers/{publicId}
- POST /api/v1/routers/{publicId}/connection-test
- GET /api/v1/routers/{publicId}/system-info

All list responses use the existing paginated { data: ... } format. Mutations return { data: ... } and a correlation ID where the current API pattern provides one.

Connection test response:

    {
        "data": {
            "status": "not_configured",
            "driver": "mikrotik_router",
            "capabilities": ["system_info"],
            "message": "No router transport is configured.",
            "checked_at": "2026-09-13T12:00:00Z"
        }
    }

## Permissions

Add these permissions to PermissionSeeder:

- routers.view
- routers.create
- routers.update
- routers.delete
- routers.test
- routers.export

The existing permission grouping automatically exposes the routers group in Roles. Backend middleware is authoritative; frontend visibility is only a convenience.

## Frontend

Replace the Router placeholder in NetworkModulePage with a dedicated RoutersPage using the existing shell and table/modal primitives.

Table columns:

- Router
- Vendor / model
- Management endpoint
- Driver / transport
- Status
- Last contact
- Actions

Add/edit modal:

- Name
- Hostname
- Management IP
- Vendor
- Model
- Software version
- Serial number
- Driver
- Preferred transport
- Status
- Notes

The modal must not expose credentials. Fixed vendor, driver, transport, and status values use simple dropdowns without search fields and use the established caret styling.

View modal:

- Identity and management metadata.
- Driver and capability table.
- Last contact/synchronization state.
- Connection-test result.
- Clear unconfigured/unsupported states.

Superdesign is used for the router list, add/edit modal, and view/connection-test states while preserving the existing operational billing UI language: dense tables, restrained colors, factual status copy, and no fabricated metrics.

## Audit and errors

Audit:

- router.created
- router.updated
- router.deleted
- router.connection_tested

Errors:

- Unknown driver: HTTP 422 with a controlled validation message.
- Unsupported capability: normalized unsupported result.
- Missing transport: normalized not_configured result.
- Missing Router public ID: HTTP 404.
- Delete is soft delete and never removes audit history.
- Device credentials must not appear in responses or logs.

## Acceptance criteria

- No new Router query or table contains organization_id.
- A Router can be created, listed, viewed, updated, and soft-deleted through the UI and API.
- MikroTik, Juniper, Cisco, and Linux/FRR resolve through the registry without controller branching.
- Unknown drivers are rejected safely.
- Connection test returns a normalized unavailable result when no transport is configured.
- Router permissions are visible and assignable in Roles.
- Audit events are recorded.
- Backend PHPUnit, frontend Vitest, type checks, and production build pass.
- Adding a future vendor requires a driver implementation and registry registration, without changing billing, subscriber, or Router CRUD code.
