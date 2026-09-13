# Router automation gateway

This internal FastAPI service is the device-communication boundary for the
installation-wide Router module. Laravel remains responsible for user
authentication, permissions, encrypted credential storage, operation records,
and API responses. The gateway is the only component that connects to routers.

## Supported boundary

The service resolves a vendor driver separately from a transport. The initial
drivers are Juniper, MikroTik, Cisco, and Linux/FRR. The only supported
transports are:

- `api` for vendor HTTP APIs;
- `ssh` using Netmiko/Paramiko;
- `netconf` using ncclient/PyEZ;
- `snmp` using PySNMP.

There is no `mock` transport in the real-operation boundary and there is no
arbitrary command endpoint. Drivers expose normalized generic operations such
as `get_system_info`, `get_interfaces`, `get_routes`, `validate_configuration`,
`preview_configuration`, `apply_configuration`, `commit_configuration`, and
`rollback_configuration`, subject to driver capabilities.

## Configuration and security

Copy `.env.example` into the deployment secret/configuration store. Set the
same long random value in `NETWORK_AUTOMATION_SERVICE_TOKEN` and Laravel's
`NETWORK_AUTOMATION_TOKEN`. Health checks are public; every other HTTP request
requires that internal bearer token. If it is missing, protected requests fail
closed with `503`.

Host-key and TLS verification are enabled by default. Write retries default to
zero. Session idle, connect, command, and overall timeouts are bounded and can
be adjusted through the typed environment settings. Device usernames,
passwords, keys, tokens, and SNMP communities are never configured in this
service's environment: Laravel decrypts them and sends them only for the
operation that needs them.

## Persistent sessions

Sessions are process-local and keyed by router, driver, transport, and
credential version. A healthy session is reused. Idle sessions are closed,
health checks detect disconnects, and safe reads may reconnect once. A write is
never blindly replayed after the device result becomes uncertain. Restarting
the container clears sessions and requires the next operation to authenticate
again.

## Local checks

From this directory, install the test dependencies and run the safe, device-
free checks:

```bash
python -m pip install -e '.[test]'
python -m pytest tests/test_configuration.py -q
python -m compileall -q src tests
```

The transport and driver tests use injected fakes and sanitized fixtures. They
do not contact a router. A live-device smoke test belongs only in an isolated
lab with approved credentials and must be run manually after confirming host
key/TLS verification and rollback procedures.

## Container deployment

Build and run the non-root image on an internal network shared with Laravel:

```bash
docker build -t isp-network-automation ./network-automation
docker run --rm --env-file network-automation/.env \
  --network billing-internal -p 127.0.0.1:8000:8000 \
  isp-network-automation
```

Do not publish this service directly to browsers or the public internet. Keep
the service token in the runtime secret store, not in the image, repository,
React bundle, URL, logs, or operation results.
