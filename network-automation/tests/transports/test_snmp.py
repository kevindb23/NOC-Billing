import asyncio
import sys
import types

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget
from network_automation.transports.snmp import SnmpTransport


def test_snmp_awaits_async_pysnmp_target_factory(monkeypatch):
    calls = []

    class AsyncTarget:
        @classmethod
        async def create(cls, address, **kwargs):
            calls.append(("create", address, kwargs))
            return "udp-target"

    def get_cmd(*args):
        assert args[2] == "udp-target"
        return iter([(None, None, 0, [("1.3.6.1.2.1.1.3.0", "up")])])

    fake_package = types.ModuleType("pysnmp")
    fake_hlapi = types.ModuleType("pysnmp.hlapi")
    fake_asyncio = types.ModuleType("pysnmp.hlapi.asyncio")
    fake_asyncio.CommunityData = lambda value: value
    fake_asyncio.ContextData = lambda: object()
    fake_asyncio.ObjectIdentity = lambda value: value
    fake_asyncio.ObjectType = lambda value: value
    fake_asyncio.SnmpEngine = lambda: object()
    fake_asyncio.UdpTransportTarget = AsyncTarget
    fake_asyncio.get_cmd = get_cmd
    fake_package.hlapi = fake_hlapi
    fake_hlapi.asyncio = fake_asyncio
    monkeypatch.setitem(sys.modules, "pysnmp", fake_package)
    monkeypatch.setitem(sys.modules, "pysnmp.hlapi", fake_hlapi)
    monkeypatch.setitem(sys.modules, "pysnmp.hlapi.asyncio", fake_asyncio)

    target = DeviceTarget(driver="cisco_router", transport="snmp", management_ip="192.0.2.10")
    credentials = DeviceCredentials(community="private")

    async def scenario():
        session = await SnmpTransport().connect(target, credentials)
        return await session.execute("test_connection")

    assert asyncio.run(scenario()) == {"1.3.6.1.2.1.1.3.0": "up"}
    assert calls == [("create", ("192.0.2.10", 161), {"timeout": 5.0, "retries": 1})]


def test_snmp_transport_uses_injected_query_without_importing_pysnmp():
    calls = []

    def query_factory(host, community, target, oid):
        calls.append((host, community, oid))
        return {oid: "value"}

    target = DeviceTarget(
        driver="cisco_router",
        transport="snmp",
        management_ip="192.0.2.10",
    )
    credentials = DeviceCredentials(community="private")
    transport = SnmpTransport(query_factory=query_factory)

    async def scenario():
        session = await transport.connect(target, credentials)
        result = await session.execute("get_system_info")
        await session.close()
        return result

    result = asyncio.run(scenario())

    assert result == {"1.3.6.1.2.1.1.1.0": "value"}
    assert calls == [("192.0.2.10", "private", "1.3.6.1.2.1.1.1.0")]
