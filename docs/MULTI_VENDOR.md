# Multi-vendor Router drivers

The Router module uses a vendor-neutral driver boundary so vendor behavior
stays out of inventory CRUD and the rest of the ISP billing application. The
initial installation registers four driver identifiers:

| Vendor | Driver identifier | Initial capabilities |
| --- | --- | --- |
| MikroTik | `mikrotik_router` | `connection_test`, `system_info` |
| Juniper | `juniper_router` | `connection_test`, `system_info` |
| Cisco | `cisco_router` | `connection_test`, `system_info` |
| Linux/FRR | `linux_frr_router` | `connection_test`, `system_info` |

These initial implementations are safe unavailable drivers. Until an approved
transport exists, connection tests and system-info requests return normalized
`not_configured` results. They never pretend that a router is reachable.

## Contract

Each driver implements `App\Contracts\RouterDriverInterface`:

```php
interface RouterDriverInterface
{
    public function identifier(): string;

    /** @return list<string> */
    public function capabilities(): array;

    public function testConnection(Router $router): ConnectionResult;

    public function getSystemInfo(Router $router): DeviceStatusResult;
}
```

`ConnectionResult` is the stable connection-test DTO. It serializes:

- `status`: `connected`, `not_configured`, `unsupported`, or `failed`;
- `driver`;
- `capabilities`;
- `message`;
- ISO-8601 `checked_at`;
- nullable `details`.

`DeviceStatusResult` is the stable system-info DTO. It serializes the same
status/driver/message/time/details fields plus normalized `vendor`, `hostname`,
`model`, `serial_number`, `software_version`, and `uptime_seconds` fields.
Vendor-specific field names and raw CLI/API payloads do not become controller
contracts. Optional details pass through DTO redaction and must contain no
secrets.

## Resolution flow

The request path is:

```text
RouterController
    -> RouterManager::driverFor()
        -> RouterDriverRegistry::resolve($router->driver)
            -> RouterDriverInterface implementation
```

`RouterDriverRegistry` has an explicit identifier-to-instance map. A value
from the database is only resolved through that map; it is never treated as a
PHP class name. `RouterManager` delegates `testConnection()` and
`getSystemInfo()` to the resolved driver. Router controllers therefore remain
vendor-blind and only serialize normalized DTOs.

Capabilities are the extension point for operations. A driver that does not
advertise an operation returns a normalized `unsupported` result. A driver
with a capability but no configured transport returns `not_configured`. An
unknown identifier is rejected before a driver operation and becomes a
controlled API `422`.

## Adding a future vendor

Add a vendor in this order:

1. Choose a stable identifier, add a driver class under
   `backend/app/Drivers/Router/`, and implement
   `RouterDriverInterface`. `identifier()` must return the chosen identifier.
2. Declare only the capabilities the driver actually supports. Implement each
   operation with `ConnectionResult` or `DeviceStatusResult`, including the
   normalized status, message, `checked_at`, and optional normalized fields.
   Do not return credentials or raw vendor payloads.
3. Add deterministic fixtures for the vendor's success, unavailable, failed,
   and unsupported responses. Fixtures should contain sanitized device data
   only; never use real passwords, tokens, communities, private keys, or
   certificates.
4. Register the driver under the identifier in
   `App\Services\RouterDriverRegistry`. Also add the identifier to the
   explicit Router request allow-lists so create/update validation accepts it.
5. Add contract tests that run against the new driver with the shared
   assertions used by the existing registry tests. At minimum, verify the
   identifier, declared capabilities, normalized DTO field names/statuses,
   deterministic fixtures, unknown/unsupported behavior, and credential
   redaction. Add an API test for the driver through the connection-test and
   system-info routes.

Adding a vendor must not require changes to billing code, subscriber code, or
the Router CRUD controller. The only Router-side changes are the driver,
registry/validation boundary, fixtures, and contract/API tests. CRUD continues
to persist inventory and delegate actions through `RouterManager` without
knowing vendor names.

## Scope and safety invariants

This is a single-installation module: driver resolution, Router queries, and
the API do not use `organization_id` or organization/tenant middleware.
MySQL remains the inventory authority, and Router records use the existing
`created_by` user relationship plus the shared audit log.

No device credentials are stored or exposed in this initial slice. Live SSH,
REST, NETCONF, SNMP, and vendor CLI communication are intentionally deferred;
the configured transport field is descriptive metadata only. Future transport
work must preserve the normalized DTO contract and the credential-redaction
invariant.
