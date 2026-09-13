"""SSH transport backed by Netmiko with a Paramiko fallback."""

from __future__ import annotations

import asyncio
import io
from collections.abc import Mapping
from typing import Any, Callable

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget

from .base import DeviceSession, TransportError, map_library_exception, maybe_await


def _endpoint(target: DeviceTarget) -> str:
    return target.hostname or str(target.management_ip or "")


def _secret(credentials: DeviceCredentials, name: str) -> str | None:
    value = getattr(credentials, name, None)
    return value.get_secret_value() if value is not None else None


def _key_options(private_key: str | None, passphrase: str | None) -> dict[str, Any]:
    """Turn encrypted credential material into safe SSH library options."""

    if not private_key:
        return {}
    options: dict[str, Any] = {"passphrase": passphrase} if passphrase else {}
    if "PRIVATE KEY" not in private_key.upper():
        options["key_file"] = private_key
        return options
    try:
        import paramiko
    except ImportError as error:
        raise TransportError(
            "transport_unavailable",
            "SSH private-key authentication requires Paramiko to be installed.",
            cause=error,
        ) from None

    key_error: BaseException | None = None
    for key_type in (paramiko.RSAKey, paramiko.Ed25519Key, paramiko.ECDSAKey, paramiko.DSSKey):
        try:
            options["pkey"] = key_type.from_private_key(
                io.StringIO(private_key),
                password=passphrase,
            )
            return options
        except Exception as error:
            key_error = error
    raise map_library_exception(
        key_error or ValueError("invalid private key"),
        default_code="authentication_failed",
    ) from None


class _NetmikoSession:
    def __init__(self, connection: Any, *, use_threads: bool = True) -> None:
        self.connection = connection
        self.use_threads = use_threads
        self.closed = False

    async def _call(self, method: Any, *args: Any) -> Any:
        if self.use_threads:
            return await asyncio.to_thread(method, *args)
        return await maybe_await(method(*args))

    async def execute(self, operation: str, parameters: Mapping[str, Any] | None = None) -> Any:
        if self.closed:
            raise TransportError("session_stale", "The SSH session is closed.", stale_session=True)
        values = parameters or {}
        try:
            if operation == "test_connection":
                return await self._call(self.connection.find_prompt)
            command = values.get("command")
            if isinstance(values.get("commands"), list) and operation in {
                "apply_configuration",
                "commit_configuration",
                "rollback_configuration",
            }:
                return await self._call(self.connection.send_config_set, values["commands"])
            if not isinstance(command, str) or not command.strip():
                raise TransportError(
                    "unsupported_operation",
                    "The SSH transport requires a driver-provided operation payload.",
                )
            return await self._call(self.connection.send_command, command)
        except TransportError:
            raise
        except Exception as error:
            raise map_library_exception(error, stale_session=_looks_stale(error)) from None

    async def close(self) -> None:
        if self.closed:
            return
        self.closed = True
        try:
            await self._call(self.connection.disconnect)
        except Exception:
            return


class _ParamikoSession:
    def __init__(self, client: Any) -> None:
        self.client = client
        self.closed = False

    async def execute(self, operation: str, parameters: Mapping[str, Any] | None = None) -> Any:
        if self.closed:
            raise TransportError("session_stale", "The SSH session is closed.", stale_session=True)
        if operation == "test_connection":
            return "connected"
        command = (parameters or {}).get("command")
        if not isinstance(command, str) or not command.strip():
            raise TransportError(
                "unsupported_operation",
                "The SSH transport requires a driver-provided operation payload.",
            )
        try:
            _, stdout, _ = await asyncio.to_thread(self.client.exec_command, command)
            return await asyncio.to_thread(stdout.read)
        except Exception as error:
            raise map_library_exception(error, stale_session=_looks_stale(error)) from None

    async def close(self) -> None:
        if self.closed:
            return
        self.closed = True
        try:
            await asyncio.to_thread(self.client.close)
        except Exception:
            return


def _looks_stale(error: BaseException) -> bool:
    name = type(error).__name__.lower()
    message = str(error).lower()
    return any(value in name or value in message for value in ("closed", "channel", "broken pipe", "eof"))


class SshTransport:
    identifier = "ssh"

    def __init__(
        self,
        *,
        netmiko_factory: Callable[..., Any] | None = None,
        paramiko_client_factory: Callable[[], Any] | None = None,
    ) -> None:
        self._netmiko_factory = netmiko_factory
        self._paramiko_client_factory = paramiko_client_factory

    async def connect(self, target: DeviceTarget, credentials: DeviceCredentials) -> DeviceSession:
        host = _endpoint(target)
        if not host:
            raise TransportError("connection_failed", "A hostname or management IP is required.")
        kwargs: dict[str, Any] = {
            "device_type": target.metadata.get("netmiko_device_type", target.driver),
            "host": host,
            "port": target.port or int(target.metadata.get("port", 22)),
            "username": credentials.username,
            "password": _secret(credentials, "password"),
            "timeout": float(target.metadata.get("connect_timeout", 15)),
            "auth_timeout": float(target.metadata.get("connect_timeout", 15)),
            # Strict host-key checking is the safe default.  A deployment may
            # explicitly opt out for a controlled lab through target metadata.
            "ssh_strict": bool(target.metadata.get("ssh_strict", True)),
            "system_host_keys": bool(target.metadata.get("system_host_keys", True)),
        }
        kwargs.update(
            _key_options(
                _secret(credentials, "private_key"),
                _secret(credentials, "passphrase"),
            )
        )
        factory = self._netmiko_factory
        if factory is None:
            try:
                from netmiko import ConnectHandler
            except ImportError:
                factory = None
            else:
                factory = ConnectHandler
        if factory is not None:
            try:
                if self._netmiko_factory is not None:
                    connection = await maybe_await(factory(**kwargs))
                    return _NetmikoSession(connection, use_threads=False)
                connection = await asyncio.to_thread(factory, **kwargs)
                return _NetmikoSession(connection)
            except Exception as error:
                raise map_library_exception(error) from None

        try:
            client_factory = self._paramiko_client_factory
            if client_factory is None:
                import paramiko

                client_factory = paramiko.SSHClient
                reject_policy = paramiko.RejectPolicy
            else:
                reject_policy = None
            client = client_factory()
            if hasattr(client, "load_system_host_keys") and kwargs["system_host_keys"]:
                await asyncio.to_thread(client.load_system_host_keys)
            if reject_policy is not None and kwargs["ssh_strict"] and hasattr(client, "set_missing_host_key_policy"):
                await asyncio.to_thread(client.set_missing_host_key_policy, reject_policy())
            connect_kwargs = {
                "hostname": host,
                "port": kwargs["port"],
                "username": credentials.username,
                "password": kwargs["password"],
                "timeout": kwargs["timeout"],
                "look_for_keys": False,
                "allow_agent": False,
            }
            if "pkey" in kwargs:
                connect_kwargs["pkey"] = kwargs["pkey"]
            elif "key_file" in kwargs:
                connect_kwargs["key_filename"] = kwargs["key_file"]
            if kwargs.get("passphrase"):
                connect_kwargs["passphrase"] = kwargs["passphrase"]
            await asyncio.to_thread(client.connect, **connect_kwargs)
            return _ParamikoSession(client)
        except ImportError as error:
            raise TransportError(
                "transport_unavailable",
                "SSH transport requires Netmiko or Paramiko to be installed.",
                cause=error,
            ) from None
        except TransportError:
            raise
        except Exception as error:
            raise map_library_exception(error) from None

    async def is_healthy(self, session: DeviceSession) -> bool:
        if getattr(session, "closed", False):
            return False
        try:
            if isinstance(session, _NetmikoSession):
                return bool(await asyncio.to_thread(session.connection.is_alive))
            if isinstance(session, _ParamikoSession):
                transport = session.client.get_transport()
                return bool(transport and transport.is_active())
            return True
        except Exception:
            return False

    async def close(self, session: DeviceSession) -> None:
        await maybe_await(session.close())
