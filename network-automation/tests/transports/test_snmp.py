import asyncio

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget
from network_automation.transports.snmp import SnmpTransport


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
