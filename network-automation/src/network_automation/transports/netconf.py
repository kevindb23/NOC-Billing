"""NETCONF transport backed by ncclient, with an optional PyEZ path."""

from __future__ import annotations

import asyncio
from collections.abc import Mapping
from typing import Any, Callable

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget

from .base import DeviceSession, TransportError, map_library_exception, maybe_await


def _secret(credentials: DeviceCredentials, name: str) -> str | None:
    value = getattr(credentials, name, None)
    return value.get_secret_value() if value is not None else None


class _NcclientSession:
    def __init__(self, manager: Any, *, use_threads: bool = True) -> None:
        self.manager = manager
        self.use_threads = use_threads
        self.closed = False

    async def _call(self, method: Any, *args: Any) -> Any:
        if self.use_threads:
            return await asyncio.to_thread(method, *args)
        return await maybe_await(method(*args))

    async def execute(self, operation: str, parameters: Mapping[str, Any] | None = None) -> Any:
        if self.closed:
            raise TransportError("session_stale", "The NETCONF session is closed.", stale_session=True)
        values = parameters or {}
        try:
            if operation == "test_connection":
                return "connected"
            if "rpc" in values and isinstance(values["rpc"], str):
                return await self._call(self.manager.dispatch, values["rpc"])
            if "filter" in values:
                return await self._call(self.manager.get, values["filter"])
            raise TransportError(
                "unsupported_operation",
                "The NETCONF transport requires a driver-provided operation payload.",
            )
        except TransportError:
            raise
        except Exception as error:
            raise map_library_exception(error, stale_session=_looks_stale(error)) from error

    async def close(self) -> None:
        if self.closed:
            return
        self.closed = True
        try:
            await self._call(self.manager.close_session)
        except Exception:
            return


class _PyEzSession:
    def __init__(self, device: Any) -> None:
        self.device = device
        self.closed = False

    async def execute(self, operation: str, parameters: Mapping[str, Any] | None = None) -> Any:
        if self.closed:
            raise TransportError("session_stale", "The NETCONF session is closed.", stale_session=True)
        values = parameters or {}
        try:
            if operation == "test_connection":
                return "connected"
            rpc = values.get("rpc")
            if isinstance(rpc, str):
                return await asyncio.to_thread(self.device.rpc, rpc)
            raise TransportError(
                "unsupported_operation",
                "The NETCONF transport requires a driver-provided operation payload.",
            )
        except TransportError:
            raise
        except Exception as error:
            raise map_library_exception(error, stale_session=_looks_stale(error)) from error

    async def close(self) -> None:
        if self.closed:
            return
        self.closed = True
        try:
            await asyncio.to_thread(self.device.close)
        except Exception:
            return


def _looks_stale(error: BaseException) -> bool:
    name = type(error).__name__.lower()
    message = str(error).lower()
    return any(value in name or value in message for value in ("closed", "session", "eof", "channel"))


class NetconfTransport:
    identifier = "netconf"

    def __init__(
        self,
        *,
        manager_factory: Callable[..., Any] | None = None,
        pyez_factory: Callable[..., Any] | None = None,
    ) -> None:
        self._manager_factory = manager_factory
        self._pyez_factory = pyez_factory

    async def connect(self, target: DeviceTarget, credentials: DeviceCredentials) -> DeviceSession:
        host = target.hostname or str(target.management_ip or "")
        if not host:
            raise TransportError("connection_failed", "A hostname or management IP is required.")
        kwargs: dict[str, Any] = {
            "host": host,
            "port": target.port or int(target.metadata.get("port", 830)),
            "username": credentials.username,
            "password": _secret(credentials, "password"),
            "hostkey_verify": bool(target.metadata.get("hostkey_verify", True)),
            "allow_agent": False,
            "look_for_keys": False,
            "timeout": float(target.metadata.get("connect_timeout", 15)),
            "device_params": {"name": target.metadata.get("netconf_device", target.driver)},
        }
        factory = self._manager_factory
        if factory is None:
            try:
                from ncclient import manager
            except ImportError:
                factory = None
            else:
                factory = manager.connect
        if factory is not None:
            try:
                if self._manager_factory is not None:
                    connection = await maybe_await(factory(**kwargs))
                    return _NcclientSession(connection, use_threads=False)
                connection = await asyncio.to_thread(factory, **kwargs)
                return _NcclientSession(connection)
            except Exception as error:
                raise map_library_exception(error) from error

        pyez_factory = self._pyez_factory
        try:
            if pyez_factory is None:
                from jnpr.junos import Device

                pyez_factory = Device
            device_kwargs = {
                "host": host,
                "port": kwargs["port"],
                "user": credentials.username,
                "passwd": kwargs["password"],
                "ssh_private_key_file": target.metadata.get("ssh_private_key_file"),
                "timeout": kwargs["timeout"],
            }
            device = pyez_factory(**{key: value for key, value in device_kwargs.items() if value is not None})
            await asyncio.to_thread(device.open)
            return _PyEzSession(device)
        except ImportError as error:
            raise TransportError(
                "transport_unavailable",
                "NETCONF transport requires ncclient or PyEZ to be installed.",
                cause=error,
            ) from error
        except TransportError:
            raise
        except Exception as error:
            raise map_library_exception(error) from error

    async def is_healthy(self, session: DeviceSession) -> bool:
        if getattr(session, "closed", False):
            return False
        try:
            if isinstance(session, _NcclientSession):
                return bool(getattr(session.manager, "connected", True))
            if isinstance(session, _PyEzSession):
                return bool(getattr(session.device, "connected", True))
            return True
        except Exception:
            return False

    async def close(self, session: DeviceSession) -> None:
        await maybe_await(session.close())
