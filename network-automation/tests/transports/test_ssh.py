import asyncio
import traceback

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget
from network_automation.transports.ssh import SshTransport
from network_automation.transports.base import TransportError


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


def test_ssh_passes_private_key_and_passphrase_to_transport_factory():
    connection = FakeNetmiko()
    captured = {}

    def factory(**kwargs):
        captured.update(kwargs)
        return connection

    target = DeviceTarget(
        driver="juniper_router",
        transport="ssh",
        hostname="router.example.test",
    )
    credentials = DeviceCredentials(
        username="automation",
        private_key="/run/secrets/router-key",
        passphrase="key-passphrase",
    )

    async def scenario():
        await SshTransport(netmiko_factory=factory).connect(target, credentials)

    asyncio.run(scenario())

    assert captured["key_file"] == "/run/secrets/router-key"
    assert captured["passphrase"] == "key-passphrase"


def test_ssh_transport_error_does_not_retain_secret_exception_or_traceback():
    def factory(**kwargs):
        raise RuntimeError("password=super-secret")

    target = DeviceTarget(driver="juniper_router", transport="ssh", hostname="router.example.test")
    credentials = DeviceCredentials(username="automation", password="secret")

    captured_error = None
    try:
        asyncio.run(SshTransport(netmiko_factory=factory).connect(target, credentials))
    except TransportError as error:
        captured_error = error
        rendered = traceback.format_exc()
    else:
        raise AssertionError("expected TransportError")

    assert captured_error is not None
    assert "super-secret" not in str(captured_error)
    assert "super-secret" not in rendered
    assert captured_error.cause is not None
    assert not isinstance(captured_error.cause, BaseException)
    assert captured_error.__cause__ is None
