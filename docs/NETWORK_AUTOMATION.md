# Network automation deployment

The network-automation gateway is an internal FastAPI service. Laravel calls
it after authenticating the user, checking the Router permission, loading the
encrypted credential profile, and creating the operation record. The gateway
then selects a vendor driver and one of four transports: API, SSH, NETCONF, or
SNMP.

No `organization_id` is required. This deployment is installation-wide.

## Service authentication

Laravel sends `Authorization: Bearer <service-token>` to the gateway. Set the
same long random value in:

```text
backend/.env                 NETWORK_AUTOMATION_TOKEN
network-automation/.env      NETWORK_AUTOMATION_SERVICE_TOKEN
```

The token is runtime configuration only. Do not commit it, put it in a Docker
image, expose it to React, include it in a URL, or write it to operation logs.
`GET /health` is intentionally unauthenticated for container probes. Every
other gateway route fails closed with `503` when the token is missing and
returns `401` for an invalid token.

## Typed settings

The service reads these settings from `network-automation/.env` or the
deployment secret/configuration store:

| Setting | Default | Purpose |
| --- | ---: | --- |
| `NETWORK_AUTOMATION_BIND_HOST` | `127.0.0.1` | bind address; use `0.0.0.0` inside the container |
| `NETWORK_AUTOMATION_PORT` | `8000` | HTTP port |
| `NETWORK_AUTOMATION_SESSION_IDLE_TIMEOUT` | `300` | close unused sessions |
| `NETWORK_AUTOMATION_SESSION_CONNECT_TIMEOUT` | `15` | connection budget |
| `NETWORK_AUTOMATION_SESSION_COMMAND_TIMEOUT` | `30` | one device command budget |
| `NETWORK_AUTOMATION_SESSION_OVERALL_TIMEOUT` | `60` | complete operation budget |
| `NETWORK_AUTOMATION_API_REQUEST_TIMEOUT` | `30` | API transport request budget |
| `NETWORK_AUTOMATION_SNMP_REQUEST_TIMEOUT` | `5` | SNMP request budget |
| `NETWORK_AUTOMATION_WRITE_RETRIES` | `0` | unsafe writes are never replayed by default |
| `NETWORK_AUTOMATION_HOST_KEY_VERIFICATION` | `true` | verify SSH/NETCONF host keys |
| `NETWORK_AUTOMATION_TLS_VERIFICATION` | `true` | verify API/NETCONF TLS |
| `NETWORK_AUTOMATION_LOG_REDACTION` | `true` | keep sensitive values out of logs |

Timeouts and retry limits are typed and bounded. Host-key and TLS verification
default to secure values. Device credentials do not belong in these settings;
they are supplied for one operation by Laravel.

## Container

`network-automation/Dockerfile` builds a Python 3.11 image, installs the four
transport libraries, runs as a non-root `automation` user, exposes port 8000,
and probes `/health` with a Docker healthcheck. Example deployment:

```bash
docker build -t isp-network-automation ./network-automation
docker run --rm --env-file network-automation/.env \
  --network billing-internal -p 127.0.0.1:8000:8000 \
  isp-network-automation
```

Keep the gateway on the internal network shared with Laravel. Do not expose it
directly to browsers or the public internet.

## Session and retry policy

The gateway keeps live sessions in process memory. The session key includes the
router, driver, transport, and credential version. A healthy session is reused
until idle expiry or disconnect. A safe read may reconnect once after a stale
session. A configuration write is not automatically retried after an uncertain
result; the operation is reported as unknown so an operator can inspect the
device before deciding whether to retry.

Container restarts close all sessions. The next operation authenticates again,
which is intentional and avoids persisting live protocol sessions in MySQL or
Redis.

## Device-free validation

From the service directory:

```bash
python -m pip install -e '.[test]'
python -m pytest tests/test_configuration.py -q
python -m compileall -q src tests
```

The full transport/driver suite uses fakes and sanitized fixture payloads. No
command above contacts a router. A live smoke test is lab-only and must be
manually approved, use an isolated router, preserve host-key/TLS verification,
and verify the rollback procedure before configuration changes.
