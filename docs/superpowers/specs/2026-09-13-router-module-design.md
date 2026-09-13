# Router Module Design

## Status

Approved design for the first Router module slice.

## Goal

Build a working organization-scoped Router inventory module with a vendor-neutral driver boundary that follows `MASTER_V2.md` items 12 and 25, supports future vendors without controller changes, exposes an authenticated API, and appears in the existing Network and Roles areas.

## Scope

This slice delivers Router inventory and the driver abstraction boundary. It does not implement live Juniper, MikroTik, Cisco, or Linux/FRR device communication. Device communication remains an automation-engine phase concern, as required by `MASTER_V2.md`.

Included:

- Router inventory persistence and organization relationship.
- Normalized Router driver contract, registry, manager, and result DTOs.
- Portal-safe Juniper and MikroTik driver registrations that report an explicit unconfigured/not-connected result.
- CRUD and connection-test API endpoints under `/api/v1/routers`.
- Granular Router permissions and role-matrix visibility.
- Dense operational Router list, add/edit/view dialogs, capability display, and safe empty/unconfigured states.
- Backend and frontend tests plus migration/build verification.

Excluded:

- Site, POP, BNG, IPAM, VLAN, provisioning, monitoring, and alarm relationships because those tables/modules do not yet exist.
- Storing router passwords, SNMP communities, private keys, API tokens, or other device secrets in the database or React.
- Vendor CLI/API commands in Laravel.
- Pretending that a router is reachable when no automation transport is configured.

## Architectural Decisions

### Organization scope

The current application is organization-based: authenticated routes resolve an organization and existing billing/RBAC records use `organization_id`. The Router table and every Router query will follow this convention. The organization represents the dedicated ISP installation in the current application and is not treated as a SaaS tenant redesign.

### Persistence

Create one `routers` table related to `organizations`. It will contain:

- Internal primary key and ULID `public_id`.
- `organization_id` foreign key with cascade delete.
- `name`, `hostname`, and nullable `management_ip`.
- `vendor`, `model`, `software_version`, and nullable `serial_number`.
- `driver` and `preferred_transport` identifiers.
- `status`, `capabilities` JSON, `last_contact_at`, `last_synchronized_at`, `notes`, and `metadata` JSON.
- Timestamps and a soft-delete timestamp.

Site and POP foreign keys are deliberately deferred until their inventory modules exist. The schema will still have a real relationship to the existing organization record and will enforce organization-scoped uniqueness for the Router name.

### Driver boundary

Business code resolves a driver by the Router record's `driver` identifier through `RouterManager` and `RouterDriverRegistry`. No controller or React code branches on vendor names.

The initial contract is deliberately limited to operations needed by this slice:

- `capabilities(): array`.
- `testConnection(Router $router): ConnectionResult`.
- `getSystemInfo(Router $router): DeviceStatusResult`.

The contract is designed to grow with normalized methods such as interfaces, BGP neighbors, routes, traffic counters, prefix policy, VLAN, and subinterface operations when those features are actually required. Unsupported operations must be represented by capabilities rather than assumed.

Juniper and MikroTik registrations use portal-safe drivers. Until a transport is implemented, they return normalized `NOT_CONFIGURED`/unavailable results and never execute commands. Adding a future vendor requires a driver implementation and registry registration, not changes to Router CRUD or subscriber/billing logic.

### API and authorization

Routes:

- `GET /api/v1/routers` — paginated, organization-scoped list.
- `POST /api/v1/routers` — create inventory record.
- `GET /api/v1/routers/{publicId}` — view record and normalized driver details.
- `PUT /api/v1/routers/{publicId}` — update inventory metadata/configuration.
- `DELETE /api/v1/routers/{publicId}` — soft delete.
- `POST /api/v1/routers/{publicId}/connection-test` — run the driver boundary operation.

Permissions use the current resource/action naming convention:

- `routers.view`
- `routers.create`
- `routers.update`
- `routers.delete`
- `routers.test`
- `routers.export`

Backend authorization is authoritative. The frontend hides Router navigation when `routers.view` is absent for non-superadmins, but API authorization remains the enforcement point.

### UI

The Router page will reuse the existing shell, compact page heading, table, modal, button, field, badge, and alert primitives. It will use the existing neutral enterprise palette and dense NOC-style information hierarchy:

- Table columns: Router, Vendor/Model, Management endpoint, Driver/Transport, Status, Last contact, Actions.
- Plain selects for vendor, driver, transport, and status; no searchable dropdown for these fixed options.
- View modal contains identity, connection metadata, capabilities table, and normalized connection-test result.
- Add/edit modal never exposes credential fields.
- Actions use the existing icon-button conventions and explicit labels/tooltips for accessibility.
- Empty and unavailable states use factual copy such as “No routers configured” and “Driver not connected”; no fabricated health or traffic metrics.

## Data Flow

1. React calls `/api/v1/routers` with the authenticated organization session.
2. Laravel resolves the organization and validates the request.
3. `RouterController` queries only routers belonging to that organization.
4. For driver operations, `RouterController` delegates to `RouterManager`.
5. `RouterManager` resolves the driver from `RouterDriverRegistry` using the stored identifier.
6. The driver returns a normalized DTO/result; vendor-specific details do not leak into the API response.
7. React renders the normalized inventory/result data and does not connect to devices directly.

## Error Handling and Safety

- Unknown driver identifiers return a controlled 422 response and are never instantiated dynamically from request input.
- Unsupported capabilities return a controlled normalized unsupported result.
- Missing transport/configuration returns `NOT_CONFIGURED`, not a fake success.
- Cross-organization public IDs return not found.
- Device credentials never enter React, logs, or this schema.
- Destructive Router deletion is soft deletion and is organization-scoped.

## Verification Requirements

- Migration applies and rolls back cleanly.
- Router model casts JSON/timestamps and exposes organization relationship.
- Registry resolves Juniper and MikroTik drivers and rejects unknown identifiers.
- Contract tests assert normalized result shapes for both initial drivers.
- Feature tests cover organization isolation, CRUD validation, permissions, and connection-test authorization/result.
- Frontend tests cover Router navigation, table rendering, modal fields, and unconfigured driver state.
- Backend PHPUnit, frontend Vitest, TypeScript/lint, and production build pass.

