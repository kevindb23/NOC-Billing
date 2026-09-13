import asyncio

import pytest

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget
from network_automation.session_manager import SessionManager
from network_automation.transports.base import TransportError


def target(version: int = 1) -> DeviceTarget:
    return DeviceTarget(
        router_id="router-1",
        driver="juniper_router",
        transport="ssh",
        hostname="router.example.test",
        credential_version=version,
    )


class FakeSession:
    def __init__(self, *, stale_on_execute: bool = False) -> None:
        self.closed = False
        self.calls = 0
        self.stale_on_execute = stale_on_execute

    async def execute(self, operation, parameters=None):
        self.calls += 1
        if self.stale_on_execute:
            self.stale_on_execute = False
            raise TransportError("session_stale", "stale", stale_session=True)
        return {"operation": operation, "calls": self.calls}

    async def close(self):
        self.closed = True


class FakeTransport:
    identifier = "ssh"

    def __init__(self, *, stale_first: bool = False) -> None:
        self.connect_calls = 0
        self.close_calls = 0
        self.sessions = []
        self.healthy = True
        self.stale_first = stale_first

    async def connect(self, target, credentials):
        self.connect_calls += 1
        session = FakeSession(stale_on_execute=self.stale_first and self.connect_calls == 1)
        self.sessions.append(session)
        return session

    async def is_healthy(self, session):
        return self.healthy and not session.closed

    async def close(self, session):
        self.close_calls += 1
        await session.close()


def run(coro):
    return asyncio.run(coro)


def test_first_connection_is_reused_for_a_second_operation():
    manager = SessionManager()
    transport = FakeTransport()
    credentials = DeviceCredentials(username="automation", password="secret")

    async def scenario():
        first = await manager.get_or_connect(target(), credentials, transport)
        second = await manager.get_or_connect(target(), credentials, transport)
        return first, second

    first, second = run(scenario())

    assert first.session is second.session
    assert transport.connect_calls == 1


def test_unhealthy_session_is_closed_and_reconnected():
    manager = SessionManager()
    transport = FakeTransport()
    credentials = DeviceCredentials(username="automation", password="secret")

    async def scenario():
        first = await manager.get_or_connect(target(), credentials, transport)
        transport.healthy = False
        second = await manager.get_or_connect(target(), credentials, transport)
        return first, second

    first, second = run(scenario())

    assert first.session is not second.session
    assert first.session.closed is True
    assert transport.connect_calls == 2


def test_credential_version_change_closes_the_previous_session():
    manager = SessionManager()
    transport = FakeTransport()
    credentials = DeviceCredentials(username="automation", password="secret")

    async def scenario():
        first = await manager.get_or_connect(target(1), credentials, transport)
        second = await manager.get_or_connect(target(2), credentials, transport)
        return first, second

    first, second = run(scenario())

    assert first.session is not second.session
    assert first.session.closed is True
    assert second.key.credential_version == 2
    assert len(manager.sessions) == 1


def test_idle_session_expires_before_reuse():
    manager = SessionManager(idle_timeout=0.001)
    transport = FakeTransport()
    credentials = DeviceCredentials(username="automation", password="secret")

    async def scenario():
        first = await manager.get_or_connect(target(), credentials, transport)
        await asyncio.sleep(0.01)
        second = await manager.get_or_connect(target(), credentials, transport)
        return first, second

    first, second = run(scenario())

    assert first.session is not second.session
    assert first.session.closed is True
    assert transport.connect_calls == 2


def test_safe_read_reconnects_once_after_a_stale_session():
    manager = SessionManager()
    transport = FakeTransport(stale_first=True)
    credentials = DeviceCredentials(username="automation", password="secret")

    result = run(
        manager.execute(
            target(),
            credentials,
            transport,
            "get_system_info",
            safe_to_retry=True,
        )
    )

    assert result["calls"] == 1
    assert transport.connect_calls == 2
    assert transport.sessions[0].closed is True


def test_write_with_uncertain_result_is_not_retried():
    manager = SessionManager()
    transport = FakeTransport(stale_first=True)
    credentials = DeviceCredentials(username="automation", password="secret")

    with pytest.raises(TransportError) as error:
        run(
            manager.execute(
                target(),
                credentials,
                transport,
                "apply_configuration",
                parameters={"configuration": "driver-owned"},
                safe_to_retry=False,
            )
        )

    assert error.value.code == "write_result_unknown"
    assert error.value.uncertain_commit is True
    assert transport.connect_calls == 1
    assert transport.sessions[0].closed is True


def test_manager_close_closes_all_cached_sessions():
    manager = SessionManager()
    transport = FakeTransport()
    credentials = DeviceCredentials(username="automation", password="secret")

    async def scenario():
        await manager.get_or_connect(target(1), credentials, transport)
        await manager.get_or_connect(target(2), credentials, transport)
        await manager.close()

    run(scenario())

    assert manager.sessions == {}
    assert transport.close_calls == 2
