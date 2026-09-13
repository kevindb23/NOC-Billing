from pathlib import Path

import pytest

from network_automation.contracts.devices import DeviceTarget
from network_automation.contracts.operations import OperationRequest
from network_automation.drivers.registry import DriverRegistry, UnsupportedDriverError


FIXTURES = Path(__file__).parents[1] / "fixtures"


def request(driver: str, operation: str = "get_interfaces") -> OperationRequest:
    return OperationRequest(
        operation=operation,
        device=DeviceTarget(
            router_id="router-1",
            driver=driver,
            transport="ssh",
            hostname="router.example.test",
        ),
    )


@pytest.mark.parametrize(
    "driver_id",
    ["juniper_router", "mikrotik_router", "cisco_router", "linux_frr"],
)
def test_registry_resolves_every_supported_vendor(driver_id: str) -> None:
    driver = DriverRegistry().resolve(driver_id)
    assert driver.identifier == driver_id
    assert "get_interfaces" in driver.capabilities()


def test_registry_rejects_unknown_driver_with_stable_error() -> None:
    with pytest.raises(UnsupportedDriverError) as error:
        DriverRegistry().resolve("unknown_router")

    assert error.value.code == "unsupported_driver"


def test_registry_can_be_extended_without_vendor_branches() -> None:
    registry = DriverRegistry()
    driver = registry.resolve("juniper_router")
    registry.register(driver)

    assert registry.resolve("juniper_router") is driver


def test_fixture_directory_contains_all_vendor_payloads() -> None:
    for vendor in ("juniper", "mikrotik", "cisco", "linux_frr"):
        files = list((FIXTURES / vendor).glob("*"))
        assert files, f"missing deterministic fixture for {vendor}"
