import asyncio

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget
from network_automation.transports.ssh import SshTransport


class FakeNetmiko:
    def __init__(self):
        self.disconnected = False
        self.kwargs = None

    def find_prompt(self):
        return "router#"

    def is_alive(self):
        return not self.disconnected

    def send_command(self, command):
        return f"output:{command}"

    def disconnect(self):
        self.disconnected = True


def test_ssh_uses_netmiko_with_strict_host_key_defaults():
    connection = FakeNetmiko()
    captured = {}

    def factory(**kwargs):
        captured.update(kwargs)
        return connection

    target = DeviceTarget(
        driver="juniper_router",
        transport="ssh",
        hostname="router.example.test",
        metadata={"netmiko_device_type": "juniper_junos"},
    )
    credentials = DeviceCredentials(username="automation", password="secret")
    transport = SshTransport(netmiko_factory=factory)

    async def scenario():
        session = await transport.connect(target, credentials)
        result = await session.execute("test_connection")
        await session.close()
        return result

    assert asyncio.run(scenario()) == "router#"
    assert captured["ssh_strict"] is True
    assert captured["system_host_keys"] is True
    assert connection.disconnected is True
