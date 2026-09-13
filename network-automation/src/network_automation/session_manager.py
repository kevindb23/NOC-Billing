"""In-memory persistent device session management.

The manager is deliberately process-local.  It keeps credentials and live
connections out of MySQL/Redis and makes the retry policy explicit: only a
safe read may be retried after a stale connection.  A write that may have
reached a device is surfaced as an unknown result instead of being replayed.
"""

from __future__ import annotations

import asyncio
import time
from collections.abc import Mapping
from dataclasses import dataclass
from typing import Any

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget
from network_automation.transports.base import (
    DeviceSession,
    SessionKey,
    Transport,
    TransportError,
    maybe_await,
)


@dataclass(slots=True)
class ManagedSession:
    key: SessionKey
    target: DeviceTarget
    credentials: DeviceCredentials
    transport: Transport
    session: DeviceSession
    created_at: float
    last_used_at: float

    def touch(self, now: float | None = None) -> None:
        self.last_used_at = time.monotonic() if now is None else now


class SessionManager:
    """Cache and lifecycle owner for transport sessions."""

    def __init__(
        self,
        *,
        connect_timeout: float = 15.0,
        idle_timeout: float = 300.0,
        command_timeout: float = 30.0,
        overall_timeout: float = 60.0,
    ) -> None:
        for name, value in (
            ("connect_timeout", connect_timeout),
            ("idle_timeout", idle_timeout),
            ("command_timeout", command_timeout),
            ("overall_timeout", overall_timeout),
        ):
            if value <= 0:
                raise ValueError(f"{name} must be greater than zero")

        self.connect_timeout = connect_timeout
        self.idle_timeout = idle_timeout
        self.command_timeout = command_timeout
        self.overall_timeout = overall_timeout
        self._sessions: dict[SessionKey, ManagedSession] = {}
        self._locks: dict[SessionKey, asyncio.Lock] = {}
        self._closed = False
        self._closing = False

    @property
    def sessions(self) -> dict[SessionKey, ManagedSession]:
        """Read-only-by-convention view useful for diagnostics and tests."""

        return self._sessions

    def key_for(self, target: DeviceTarget) -> SessionKey:
        metadata = target.metadata
        router_id = target.router_id or metadata.get("router_id") or target.hostname or str(target.management_ip or "")
        if not router_id:
            raise ValueError("router_id or a device hostname is required for a session")
        raw_version = target.credential_version if target.credential_version else metadata.get("credential_version", 0)
        try:
            credential_version = int(raw_version)
        except (TypeError, ValueError) as error:
            raise ValueError("credential_version must be an integer") from error
        if credential_version < 0:
            raise ValueError("credential_version must not be negative")
        return SessionKey(
            router_id=str(router_id),
            driver_id=target.driver,
            transport=target.transport,
            credential_version=credential_version,
        )

    async def get_or_connect(
        self,
        target: DeviceTarget,
        credentials: DeviceCredentials,
        transport: Transport,
    ) -> ManagedSession:
        """Return a healthy cached session or connect exactly once per key."""

        key = self.key_for(target)
        self._ensure_available()
        await self._invalidate_other_credential_versions(key)
        lock = self._lock_for(key)
        async with lock:
            self._ensure_available()
            return await self._get_or_connect_locked(target, credentials, transport, key)

    async def execute(
        self,
        target: DeviceTarget,
        credentials: DeviceCredentials,
        transport: Transport,
        operation: str,
        parameters: Mapping[str, Any] | None = None,
        *,
        safe_to_retry: bool,
    ) -> Any:
        """Execute once, with one reconnect only for explicitly safe reads."""

        async def run_once(managed: ManagedSession) -> Any:
            try:
                result = await asyncio.wait_for(
                    maybe_await(managed.session.execute(operation, parameters)),
                    timeout=self.command_timeout,
                )
                managed.touch()
                return result
            except TransportError:
                raise
            except (BrokenPipeError, ConnectionError, EOFError, OSError, TimeoutError) as error:
                raise TransportError(
                    "session_stale",
                    "The device session became unavailable.",
                    stale_session=True,
                    cause=error,
                ) from None

        key = self.key_for(target)
        self._ensure_available()
        await self._invalidate_other_credential_versions(key)
        lock = self._lock_for(key)

        async def execute_with_policy() -> Any:
            self._ensure_available()
            managed = await self._get_or_connect_locked(target, credentials, transport, key)
            try:
                return await run_once(managed)
            except TransportError as error:
                await self._invalidate_locked(managed.key)
                if not safe_to_retry or not error.stale_session:
                    if not safe_to_retry and error.stale_session:
                        raise TransportError(
                            "write_result_unknown",
                            "The write result is unknown; it was not retried.",
                            uncertain_commit=True,
                            cause=error,
                        ) from None
                    raise
                replacement = await self._get_or_connect_locked(target, credentials, transport, key)
                return await run_once(replacement)

        try:
            async with lock:
                return await asyncio.wait_for(execute_with_policy(), timeout=self.overall_timeout)
        except asyncio.TimeoutError as error:
            await self.invalidate(key)
            if not safe_to_retry:
                raise TransportError(
                    "write_result_unknown",
                    "The write result is unknown; it was not retried.",
                    uncertain_commit=True,
                    cause=error,
                ) from None
            raise TransportError(
                "operation_timeout",
                "The device operation timed out.",
                cause=error,
            ) from None

    async def invalidate(self, key: SessionKey) -> None:
        """Close and remove the cached session for a key."""

        lock = self._locks.setdefault(key, asyncio.Lock())
        async with lock:
            await self._invalidate_locked(key)

    async def close(self) -> None:
        """Close all sessions; safe to call during worker shutdown more than once."""

        if self._closed:
            return
        self._closing = True
        locks = [(key, self._locks[key]) for key in sorted(self._locks, key=lambda item: item.__repr__())]
        acquired: list[asyncio.Lock] = []
        try:
            for _, lock in locks:
                await lock.acquire()
                acquired.append(lock)
            self._closed = True
            entries = list(self._sessions.items())
            self._sessions.clear()
            for key, entry in entries:
                await self._close_entry(key, entry)
        finally:
            for lock in reversed(acquired):
                lock.release()

    async def _close_entry(self, key: SessionKey, entry: ManagedSession) -> None:
        if self._sessions.get(key) is entry:
            self._sessions.pop(key, None)
        try:
            await maybe_await(entry.transport.close(entry.session))
        except Exception:
            # Closing is best effort.  Never allow a cleanup failure to hide
            # the original stale/invalidating operation.
            return

    async def _invalidate_locked(self, key: SessionKey) -> None:
        entry = self._sessions.pop(key, None)
        if entry is not None:
            await self._close_entry(key, entry)

    def _lock_for(self, key: SessionKey) -> asyncio.Lock:
        return self._locks.setdefault(key, asyncio.Lock())

    def _ensure_available(self) -> None:
        if self._closed or self._closing:
            raise RuntimeError("session manager is closed")

    async def _get_or_connect_locked(
        self,
        target: DeviceTarget,
        credentials: DeviceCredentials,
        transport: Transport,
        key: SessionKey,
    ) -> ManagedSession:
        now = time.monotonic()
        existing = self._sessions.get(key)
        if existing is not None:
            if now - existing.last_used_at >= self.idle_timeout:
                await self._close_entry(key, existing)
            else:
                try:
                    healthy = await asyncio.wait_for(
                        maybe_await(transport.is_healthy(existing.session)),
                        timeout=self.command_timeout,
                    )
                except Exception:
                    healthy = False
                if healthy:
                    existing.touch(now)
                    return existing
                await self._close_entry(key, existing)

        started = time.monotonic()
        session = await asyncio.wait_for(
            maybe_await(transport.connect(target, credentials)),
            timeout=self.connect_timeout,
        )
        managed = ManagedSession(
            key=key,
            target=target,
            credentials=credentials,
            transport=transport,
            session=session,
            created_at=started,
            last_used_at=time.monotonic(),
        )
        self._sessions[key] = managed
        return managed

    async def _invalidate_other_credential_versions(self, key: SessionKey) -> None:
        """Ensure replacing credentials cannot leave an old live session behind."""

        previous = [
            (old_key, entry)
            for old_key, entry in self._sessions.items()
            if old_key.router_id == key.router_id
            and old_key.driver_id == key.driver_id
            and old_key.transport == key.transport
            and old_key.credential_version != key.credential_version
        ]
        for old_key, entry in previous:
            lock = self._lock_for(old_key)
            async with lock:
                current = self._sessions.get(old_key)
                if current is entry:
                    await self._close_entry(old_key, entry)
