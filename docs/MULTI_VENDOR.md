# Multi-vendor router drivers

Vendor behavior is isolated in Python drivers. Laravel controllers and the
React UI use generic operations and capability metadata; they do not branch on
Juniper, MikroTik, Cisco, or Linux/FRR command syntax.

## Driver registry

The gateway registers these initial drivers:

| Vendor | Identifier | Scope |
| --- | --- | --- |
| Juniper | `juniper_router` | normalized monitoring and typed configuration |
| MikroTik | `mikrotik_router` | normalized monitoring and typed configuration |
| Cisco | `cisco_router` | normalized monitoring and typed configuration |
| Linux/FRR | `linux_frr_router` | normalized monitoring and typed configuration |

Each driver declares capabilities and implements the same operation boundary.
Transport lifecycle is supplied by the transport registry, not duplicated in a
vendor driver.

## Driver/transport separation

```text
OperationRequest
  -> DriverRegistry.resolve(driver)
      -> driver builds typed vendor payload and parses the response
  -> TransportRegistry.resolve(transport)
      -> transport owns connect, health, execute, reconnect, and close
```

The only transports in this boundary are `api`, `ssh`, `netconf`, and `snmp`.
Netmiko/Paramiko, ncclient/PyEZ, httpx, and PySNMP are implementation details
of those adapters. A driver can support multiple transports without exposing
protocol-specific details in Laravel.

## Normalized operations

Read operations include connection tests, device facts, interfaces, interface
status, routes, BGP neighbors, and traffic counters. Configuration operations
are explicit: validate, preview, apply, commit, and rollback. Each request is
typed and capability checked. Arbitrary `run_command` requests are rejected.

Results contain normalized status, driver, transport, correlation ID, message,
and redacted details. Raw credentials and sensitive vendor payloads never cross
the public API boundary.

## Adding another vendor

1. Add a driver implementing the shared driver contract and choose a stable
   identifier.
2. Declare only operations that are genuinely supported.
3. Keep vendor command builders and response parsers inside that driver.
4. Register the driver and add it to Laravel's explicit validation allow-list.
5. Add sanitized fixture tests for success, failure, unsupported operations, and
   redaction.
6. Add capability and permission coverage before exposing the operation in the
   UI.

Adding a vendor must not require a new database table or `organization_id`.
Router inventory, encrypted credential profiles, and operation history remain
installation-wide and use the existing router relationships.

## Testing rule

Driver and transport tests use fake sessions and sanitized fixtures. They do
not contact a live router. Lab-only smoke tests must use an isolated device,
approved credentials, host-key/TLS verification, bounded timeouts, and a tested
rollback path.
