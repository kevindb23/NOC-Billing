# Router module

The Router module is the installation-wide inventory and device-automation
boundary. It is not organization-based: routers, credentials, operations, and
permissions do not add or depend on `organization_id`.

Laravel remains the business and authorization layer. It stores router
inventory, encrypted credential profiles, operation history, and audit data.
The Python network-automation gateway is the only component that connects to a
device. React never connects to a router and never receives decrypted
credentials.

## Supported model

The selected vendor driver is independent from the selected transport. Initial
drivers are:

| Vendor | Driver | Typical transports |
| --- | --- | --- |
| Juniper | `juniper_router` | NETCONF, SSH, API, SNMP |
| MikroTik | `mikrotik_router` | API, SSH, SNMP |
| Cisco | `cisco_router` | SSH, NETCONF, API, SNMP |
| Linux/FRR | `linux_frr_router` | SSH, NETCONF, API, SNMP |

The preferred transport values are exactly `api`, `ssh`, `netconf`, and `snmp`.
`mock` is not accepted by the real-operation API or selected in the UI.

## Laravel API

The authenticated Laravel API uses the `/api/v1` base. Router inventory and
credential routes are permission protected:

| Method | Path | Permission |
| --- | --- | --- |
| `GET` | `/routers` | `routers.view` |
| `POST` | `/routers` | `routers.create` |
| `GET` | `/routers/{publicId}` | `routers.view` |
| `PUT` | `/routers/{publicId}` | `routers.update` |
| `DELETE` | `/routers/{publicId}` | `routers.delete` |
| `GET` | `/routers/{publicId}/credentials` | credential view permission |
| `PUT` | `/routers/{publicId}/credentials` | credential manage permission |
| `POST` | `/routers/{publicId}/operations` | operation-specific permission |
| `GET` | `/routers/{publicId}/operations` | `routers.operations.view` |
| `GET` | `/routers/{publicId}/operations/{operationPublicId}` | `routers.operations.view` |

The operation endpoint accepts only typed generic operations. Monitoring
operations require `routers.monitor`. Preview and validation require
`routers.configuration.preview`; apply, commit, and rollback require their
corresponding configuration permissions. An operation record captures the
credential profile identifier and version so credential rotation cannot silently
change an already queued request.

## Credentials and form behavior

Credentials are stored by Laravel using encrypted casts and are returned only
as safe metadata: profile name, transport/auth type, primary status, version,
and timestamps. Passwords, private keys, tokens, SNMP communities, and TLS
material are never returned in resources, URLs, browser storage, logs, or
operation results.

The form shows only fields relevant to the selected transport:

- SSH: username and password, optional private key/passphrase, port `22`, and
  host-key verification.
- NETCONF: username and password, port `830`, and the selected TLS verification
  material when TLS mode is used.
- API: API base URL, authentication mode, and the required API token.
- SNMP: version, port `161`, community or security fields for that version.

Changing transport clears unsaved fields for the previous transport. Editing a
router displays masked metadata and leaves secret inputs blank until an
explicit replacement is submitted.

## Generic operation lifecycle

The normal flow is:

```text
React -> Laravel auth/permission checks -> encrypted credential lookup
      -> queued RouterOperation -> Python gateway -> driver -> transport -> device
      <- normalized redacted result and audit/lifecycle update
```

The initial generic operations are:

- Monitoring: `test_connection`, `get_system_info`, `get_device_facts`,
  `get_interfaces`, `get_interface_status`, `get_routes`,
  `get_bgp_neighbors`, `get_traffic_counters`.
- Configuration: `validate_configuration`, `preview_configuration`,
  `apply_configuration`, `commit_configuration`, `rollback_configuration`.

Drivers advertise capabilities. Unsupported operations are rejected before a
device request. There is no arbitrary CLI command endpoint. Configuration
changes use typed request models and vendor-owned command/API builders.

## Session safety

The Python gateway keeps sessions in process memory, keyed by router, driver,
transport, and credential version. Healthy sessions are reused. Idle sessions
expire, disconnected sessions are re-established for safe reads, and writes
are never automatically replayed when the commit result is uncertain. A
credential version change invalidates the previous session.

See [NETWORK_AUTOMATION.md](NETWORK_AUTOMATION.md) for deployment, internal
service authentication, timeout configuration, and device-free validation.
