"""SNMP transport backed by the PySNMP high-level API."""

from __future__ import annotations

import asyncio
import inspect
from collections.abc import Mapping
from typing import Any, Callable

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget

from .base import DeviceSession, TransportError, map_library_exception, maybe_await


def _secret(credentials: DeviceCredentials, name: str) -> str | None:
    value = getattr(credentials, name, None)
    return value.get_secret_value() if value is not None else None


class _SnmpSession:
    def __init__(self, query: Callable[[str], Any]) -> None:
        self._query = query
        self.closed = False

    async def execute(self, operation: str, parameters: Mapping[str, Any] | None = None) -> Any:
        if self.closed:
            raise TransportError("session_stale", "The SNMP session is closed.", stale_session=True)
        values = parameters or {}
        if operation == "test_connection":
            oid = "1.3.6.1.2.1.1.3.0"
        elif operation == "get_system_info":
            oid = "1.3.6.1.2.1.1.1.0"
        else:
            oid = values.get("oid")
            if not isinstance(oid, str) or not oid.strip():
                raise TransportError(
                    "unsupported_operation",
                    "The SNMP transport requires a driver-provided OID.",
                )
        try:
            return await maybe_await(self._query(oid))
        except TransportError:
            raise
        except Exception as error:
            raise map_library_exception(error, stale_session=_looks_stale(error)) from error

    async def close(self) -> None:
        self.closed = True


def _looks_stale(error: BaseException) -> bool:
    name = type(error).__name__.lower()
    message = str(error).lower()
    return any(value in name or value in message for value in ("timeout", "transport", "socket", "engine"))


class SnmpTransport:
    identifier = "snmp"

    def __init__(
        self,
        *,
        engine_factory: Callable[[], Any] | None = None,
        target_factory: Callable[..., Any] | None = None,
        query_factory: Callable[..., Any] | None = None,
    ) -> None:
        self._engine_factory = engine_factory
        self._target_factory = target_factory
        self._query_factory = query_factory

    async def connect(self, target: DeviceTarget, credentials: DeviceCredentials) -> DeviceSession:
        host = target.hostname or str(target.management_ip or "")
        community = _secret(credentials, "community")
        if not host or not community:
            raise TransportError("authentication_failed", "SNMP requires a management endpoint and community.")
        try:
            if self._query_factory is not None:
                query_factory = self._query_factory
                return _SnmpSession(lambda oid: query_factory(host, community, target, oid))

            from pysnmp.hlapi.asyncio import (
                CommunityData,
                ContextData,
                ObjectIdentity,
                ObjectType,
                SnmpEngine,
                UdpTransportTarget,
                get_cmd,
            )

            engine_factory = self._engine_factory or SnmpEngine
            target_factory = self._target_factory or UdpTransportTarget
            engine = engine_factory()
            port = target.port or int(target.metadata.get("port", 161))
            timeout = float(target.metadata.get("request_timeout", 5))
            retries = int(target.metadata.get("retries", 1))
            try:
                udp_target = target_factory.create((host, port), timeout=timeout, retries=retries)
            except AttributeError:
                udp_target = target_factory((host, port), timeout=timeout, retries=retries)
            community_data = CommunityData(community)
            context = ContextData()

            async def query(oid: str) -> Any:
                result = get_cmd(
                    engine,
                    community_data,
                    udp_target,
                    context,
                    ObjectType(ObjectIdentity(oid)),
                )
                if inspect.isawaitable(result):
                    result = await result
                else:
                    result = next(result)
                error_indication, error_status, error_index, bindings = result
                if error_indication:
                    raise TransportError("snmp_error", "The SNMP agent returned an error.")
                if error_status:
                    raise TransportError("snmp_error", "The SNMP agent rejected the request.")
                return {str(name): str(value) for name, value in bindings}

            return _SnmpSession(query)
        except ImportError as error:
            raise TransportError(
                "transport_unavailable",
                "SNMP transport requires PySNMP to be installed.",
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
