import asyncio

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget
from network_automation.transports.netconf import NetconfTransport


class FakeManager:
    connected = True

    def dispatch(self, rpc):
        return {"rpc": rpc}

    def close_session(self):
        self.connected = False


def test_netconf_connects_through_injected_ncclient_factory():
    manager = FakeManager()
    captured = {}

    def factory(**kwargs):
        captured.update(kwargs)
        return manager

    target = DeviceTarget(
        driver="juniper_router",
        transport="netconf",
        hostname="router.example.test",
    )
    credentials = DeviceCredentials(username="automation", password="secret")
    transport = NetconfTransport(manager_factory=factory)

    async def scenario():
        session = await transport.connect(target, credentials)
        result = await session.execute("get_system_info", {"rpc": "<get-system-information/>"})
        await session.close()
        return result

    assert asyncio.run(scenario()) == {"rpc": "<get-system-information/>"}
    assert captured["hostkey_verify"] is True
    assert manager.connected is False
