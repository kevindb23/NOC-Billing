import pytest

from network_automation.transports import TransportRegistry
from network_automation.transports.api import ApiTransport
from network_automation.transports.base import TransportError


def test_registry_lazily_resolves_only_supported_transports():
    registry = TransportRegistry()

    assert isinstance(registry.resolve("api"), ApiTransport)
    assert {"api", "ssh", "netconf", "snmp"} == set(
        registry.resolve(identifier).identifier for identifier in ("api", "ssh", "netconf", "snmp")
    )


def test_registry_rejects_removed_mock_transport():
    registry = TransportRegistry()

    with pytest.raises(KeyError):
        registry.resolve("mock")


def test_registry_allows_injected_transport_doubles():
    class FakeTransport:
        identifier = "ssh"

    transport = FakeTransport()
    registry = TransportRegistry([transport])

    assert registry.resolve("SSH") is transport


def test_transport_error_redacts_secret_shaped_messages():
    error = TransportError("connection_failed", "password=do-not-log token=also-secret")

    assert "do-not-log" not in str(error)
    assert "also-secret" not in str(error)
