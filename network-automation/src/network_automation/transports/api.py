"""HTTP API transport backed by httpx connection pooling."""

from __future__ import annotations

import asyncio
from collections.abc import Mapping
from typing import Any, Callable
from urllib.parse import urlparse

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget

from .base import DeviceSession, TransportError, map_library_exception, maybe_await


def _secret(credentials: DeviceCredentials, name: str) -> str | None:
    value = getattr(credentials, name, None)
    return value.get_secret_value() if value is not None else None


def _base_url(target: DeviceTarget) -> str:
    value = target.metadata.get("api_url")
    if not isinstance(value, str) or not value.strip():
        host = target.hostname or str(target.management_ip or "")
        if not host:
            raise TransportError("connection_failed", "A hostname or management IP is required.")
        scheme = str(target.metadata.get("api_scheme", "https")).lower()
        port = target.port
        value = f"{scheme}://{host}{f':{port}' if port else ''}"
    parsed = urlparse(value)
    if parsed.scheme not in {"http", "https"} or not parsed.hostname or parsed.username or parsed.password:
        raise TransportError("invalid_endpoint", "The API endpoint must be an HTTP(S) URL without credentials.")
    if parsed.query or parsed.fragment:
        raise TransportError("invalid_endpoint", "The API endpoint must not contain a query or fragment.")
    return value.rstrip("/")


class _ApiSession:
    def __init__(self, client: Any) -> None:
        self.client = client
        self.closed = False

    async def execute(self, operation: str, parameters: Mapping[str, Any] | None = None) -> Any:
        if self.closed:
            raise TransportError("session_stale", "The API session is closed.", stale_session=True)
        values = parameters or {}
        if operation == "test_connection":
            path = "/health"
            method = "GET"
        elif operation == "get_system_info":
            path = "/system/info"
            method = "GET"
        else:
            path = values.get("path")
            method = str(values.get("method", "GET")).upper()
            if not isinstance(path, str) or not path.startswith("/"):
                raise TransportError(
                    "unsupported_operation",
                    "The API transport requires a driver-provided relative path.",
                )
            if method not in {"GET", "POST", "PUT", "PATCH", "DELETE"}:
                raise TransportError("unsupported_operation", "The API method is not supported.")
        request_kwargs: dict[str, Any] = {}
        if "query" in values and isinstance(values["query"], Mapping):
            request_kwargs["params"] = dict(values["query"])
        if "payload" in values:
            request_kwargs["json"] = values["payload"]
        try:
            response = await self.client.request(method, path, **request_kwargs)
            if getattr(response, "status_code", 200) == 401:
                raise TransportError("authentication_failed", "The API rejected the credentials.")
            if getattr(response, "status_code", 200) >= 500:
                raise TransportError("remote_service_failed", "The API reported a server failure.", stale_session=True)
            if hasattr(response, "raise_for_status"):
                response.raise_for_status()
            if hasattr(response, "json"):
                try:
                    return response.json()
                except Exception:
                    pass
            return getattr(response, "text", response)
        except TransportError:
            raise
        except Exception as error:
            raise map_library_exception(error, stale_session=_looks_stale(error)) from error

    async def close(self) -> None:
        if self.closed:
            return
        self.closed = True
        try:
            await maybe_await(self.client.aclose())
        except Exception:
            return


def _looks_stale(error: BaseException) -> bool:
    name = type(error).__name__.lower()
    message = str(error).lower()
    return any(value in name or value in message for value in ("connect", "closed", "pool", "reset"))


class ApiTransport:
    identifier = "api"

    def __init__(self, *, client_factory: Callable[..., Any] | None = None) -> None:
        self._client_factory = client_factory

    async def connect(self, target: DeviceTarget, credentials: DeviceCredentials) -> DeviceSession:
        base_url = _base_url(target)
        try:
            factory = self._client_factory
            if factory is None:
                import httpx

                factory = httpx.AsyncClient
            token = _secret(credentials, "token")
            headers = {"Authorization": f"Bearer {token}"} if token else {}
            verify: bool | str = bool(target.metadata.get("tls_verify", True))
            if target.metadata.get("tls_ca_path"):
                verify = str(target.metadata["tls_ca_path"])
            client = factory(
                base_url=base_url,
                headers=headers,
                verify=verify,
                timeout=float(target.metadata.get("request_timeout", 30)),
            )
            return _ApiSession(client)
        except ImportError as error:
            raise TransportError(
                "transport_unavailable",
                "API transport requires httpx to be installed.",
                cause=error,
            ) from error
        except TransportError:
            raise
        except Exception as error:
            raise map_library_exception(error) from error

    async def is_healthy(self, session: DeviceSession) -> bool:
        return not bool(getattr(session, "closed", False))

    async def close(self, session: DeviceSession) -> None:
        await maybe_await(session.close())
