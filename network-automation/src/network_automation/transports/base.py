"""Shared transport contracts and safe error mapping.

Transport implementations intentionally own protocol-specific connection
details.  Drivers receive a :class:`DeviceSession` and never need to know
whether it is backed by SSH, NETCONF, HTTP, or SNMP.
"""

from __future__ import annotations

import inspect
from collections.abc import Mapping
from dataclasses import dataclass
from typing import Any, Protocol, runtime_checkable

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget
from network_automation.security.redaction import redact_exception


class TransportError(RuntimeError):
    """Stable, non-secret error raised by a transport boundary."""

    def __init__(
        self,
        code: str,
        message: str,
        *,
        stale_session: bool = False,
        uncertain_commit: bool = False,
        retryable: bool = False,
        cause: BaseException | None = None,
    ) -> None:
        self.code = code
        self.stale_session = stale_session
        self.uncertain_commit = uncertain_commit
        self.retryable = retryable
        self.cause = (
            {
                "type": type(cause).__name__,
                "message": redact_exception(cause),
            }
            if cause is not None
            else None
        )
        self.cause_metadata = self.cause
        super().__init__(redact_exception(message))


@runtime_checkable
class DeviceSession(Protocol):
    """A live protocol session used by a vendor driver."""

    async def execute(
        self,
        operation: str,
        parameters: Mapping[str, Any] | None = None,
    ) -> Any:
        """Execute a driver-selected operation on the already-open session."""

    async def close(self) -> None:
        """Release protocol resources."""


@runtime_checkable
class Transport(Protocol):
    """Connection lifecycle implemented by each protocol adapter."""

    identifier: str

    async def connect(
        self,
        target: DeviceTarget,
        credentials: DeviceCredentials,
    ) -> DeviceSession:
        ...

    async def is_healthy(self, session: DeviceSession) -> bool:
        ...

    async def close(self, session: DeviceSession) -> None:
        ...


@dataclass(frozen=True, slots=True)
class SessionKey:
    """Stable cache identity; secrets never form part of the key."""

    router_id: str
    driver_id: str
    transport: str
    credential_version: int


async def maybe_await(value: Any) -> Any:
    """Allow small test doubles and synchronous protocol clients alike."""

    if inspect.isawaitable(value):
        return await value
    return value


def map_library_exception(
    exception: BaseException,
    *,
    default_code: str = "transport_failed",
    stale_session: bool = False,
    uncertain_commit: bool = False,
) -> TransportError:
    """Map third-party exceptions to a stable, redacted transport error."""

    name = type(exception).__name__.lower()
    code = default_code
    if "auth" in name or "permission" in name or "credential" in name:
        code = "authentication_failed"
    elif "timeout" in name or "timedout" in name:
        code = "transport_timeout"
    elif "connection" in name or "socket" in name or "channel" in name:
        code = "connection_failed"

    return TransportError(
        code,
        f"{code}: {redact_exception(exception)}",
        stale_session=stale_session,
        uncertain_commit=uncertain_commit,
        cause=exception,
    )
