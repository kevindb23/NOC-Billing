# Router module

The Router module is the inventory and vendor-neutral device boundary for this
single-installation deployment. Laravel owns the inventory and authorization;
the frontend calls only these APIs. The module does not configure routing,
BGP, VLANs, interfaces, prefixes, or other live device state in this slice.

This installation is not organization-based. Router records and queries do not
use `organization_id`, organization middleware, tenant filters, or
organization-scoped uniqueness. Router names are unique installation-wide.

## API base and authentication

The API base is `/api/v1`. All routes below require an authenticated Sanctum
Bearer token and the permission shown in the table. A missing permission
returns `403`. The API returns the existing `{ "data": ... }` envelope for
successful JSON responses.

## Router endpoints

| Method | Path | Permission | Success | Other documented responses |
| --- | --- | --- | --- | --- |
| `GET` | `/api/v1/routers` | `routers.view` | `200` paginated `{data: {data: [...], ...}}` | `422` for invalid `per_page`; `403` |
| `POST` | `/api/v1/routers` | `routers.create` | `201` with `{data: router}` | `422` for validation or unknown driver; `403` |
| `GET` | `/api/v1/routers/{publicId}` | `routers.view` | `200` with `{data: router}` | `404` for a missing or soft-deleted public ID; `403` |
| `PUT` | `/api/v1/routers/{publicId}` | `routers.update` | `200` with `{data: router}` | `404`; `422` for validation or unknown driver; `403` |
| `DELETE` | `/api/v1/routers/{publicId}` | `routers.delete` | `204` with no response body; deletion is soft | `404`; `403` |
| `POST` | `/api/v1/routers/{publicId}/connection-test` | `routers.test` | `200` with a normalized connection result | `404`; `422` for an unknown driver or an `unsupported` result; `403` |
| `GET` | `/api/v1/routers/{publicId}/system-info` | `routers.test` | `200` with a normalized device-status result | `404`; `422` for an unknown driver or an `unsupported` result; `403` |

`{publicId}` is the Router ULID in the `public_id` field, not the database
primary key. `GET /routers` accepts the existing pagination parameters,
including `per_page` from 1 through 100, and an optional search string.

The seeded permissions are `routers.view`, `routers.create`,
`routers.update`, `routers.delete`, `routers.test`, and `routers.export`.
`routers.export` is available to the role permission matrix, but there is no
Router export route in this initial API surface.

Unauthenticated requests return the application's normal `401` authentication
response. Validation errors use Laravel's normal `422` validation response.
The create response echoes `correlation_id` when the request supplies
`X-Request-Id`; Router audit events use that same request correlation value.

## Router resource

CRUD responses expose inventory fields such as `public_id`, `name`, `hostname`,
`management_ip`, `vendor`, `model`, `software_version`, `serial_number`,
`driver`, `preferred_transport`, `status`, `capabilities`,
`last_contact_at`, `last_synchronized_at`, `notes`, `created_by`, and the
timestamps. `metadata` is intentionally hidden from Router resources. The
`created_by` value is taken from the authenticated user and is not accepted as
client-controlled input.

The Router request boundary currently accepts these driver identifiers:

- `mikrotik_router`
- `juniper_router`
- `cisco_router`
- `linux_frr_router`

## Normalized action results

Every driver result uses one of the stable statuses `connected`,
`not_configured`, `unsupported`, or `failed`. No vendor CLI/API response is
passed through as the public contract.

### Connection test

`POST /api/v1/routers/{publicId}/connection-test` returns a `data` object with:

```json
{
  "status": "not_configured",
  "driver": "mikrotik_router",
  "capabilities": ["connection_test", "system_info"],
  "message": "No router transport is configured.",
  "checked_at": "2026-09-13T12:00:00+00:00",
  "details": null
}
```

The normalized connection fields are `status`, `driver`, `capabilities`,
`message`, `checked_at`, and nullable `details`. The first four drivers return
`not_configured` with the honest no-transport message; they do not fabricate a
successful connection.

### System information

`GET /api/v1/routers/{publicId}/system-info` returns a `data` object with:

```json
{
  "status": "not_configured",
  "driver": "mikrotik_router",
  "vendor": "mikrotik",
  "hostname": null,
  "model": null,
  "serial_number": null,
  "software_version": null,
  "uptime_seconds": null,
  "message": "No router transport is configured.",
  "checked_at": "2026-09-13T12:00:00+00:00",
  "details": null
}
```

The normalized device-status fields are `status`, `driver`, `vendor`,
`hostname`, `model`, `serial_number`, `software_version`, `uptime_seconds`,
`message`, `checked_at`, and nullable `details`.

`details` is optional and is redacted by the DTO boundary. Passwords, tokens,
private keys, certificates, communities, headers, and similar credential
material must never be returned in a result, response, audit event, or log.

## Driver flow

Router action handling follows this fixed boundary:

```text
RouterController
    -> RouterManager
        -> RouterDriverRegistry
            -> RouterDriverInterface
                -> selected vendor driver
```

The controller loads a Router by `public_id` and delegates the operation to
`RouterManager`. The manager asks `RouterDriverRegistry` to resolve the stored
driver identifier from an explicit code registration map. It then calls
`testConnection()` or `getSystemInfo()` on the interface and returns the
normalized DTO. Controllers do not branch on vendor names or construct vendor
commands. An unknown identifier is rejected as a controlled `422` response.

Capabilities describe which operations a driver supports. A missing transport
is `not_configured`; an operation absent from the driver's capabilities is
`unsupported` and is surfaced as `422` by the action endpoint.

## Credentials and transport boundary

No device credentials are stored or exposed in this initial slice. This means
no router password, SNMP community, private key, API token, or equivalent
secret is accepted as Router inventory, returned by the API, sent to the
frontend, or written to audit logs. The `preferred_transport` field is only
inventory metadata (`api`, `ssh`, `netconf`, `snmp`, or `mock`); it does not
configure or initiate a live connection. Real transport support is deferred.

The Router module is also independent of billing and subscribers. Adding or
using Router inventory cannot alter billing calculations, subscriber records,
or subscriber workflows.
