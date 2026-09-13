import asyncio
import json
from pathlib import Path
from typing import Any

import pytest
from pydantic import ValidationError

from network_automation.contracts.devices import DeviceTarget
from network_automation.contracts.operations import OperationRequest
from network_automation.drivers.registry import DriverRegistry


FIXTURES = Path(__file__).parents[1] / "fixtures"
READ_OPERATIONS = (
    "test_connection",
    "get_system_info",
    "get_device_facts",
    "get_interfaces",
    "get_interface_status",
    "get_routes",
    "get_bgp_neighbors",
    "get_traffic_counters",
)


def run(coro: Any) -> Any:
    return asyncio.run(coro)


def make_request(driver: str, operation: str, parameters: dict[str, Any] | None = None) -> OperationRequest:
    return OperationRequest(
        operation=operation,
        device=DeviceTarget(
            router_id="router-1",
            driver=driver,
            transport="ssh",
            hostname="router.example.test",
        ),
        parameters=parameters or {},
        correlation_id=f"test-{driver}-{operation}",
    )


class FixtureSession:
    def __init__(self, payload: str | dict[str, Any]) -> None:
        self.payload = payload
        self.calls: list[tuple[str, dict[str, Any]]] = []

    async def execute(self, operation: str, parameters: dict[str, Any] | None = None) -> Any:
        self.calls.append((operation, parameters or {}))
        if operation == "test_connection":
            return "connected"
        return self.payload


def fixture(vendor: str) -> str:
    return (FIXTURES / vendor / "show-data.txt").read_text()


@pytest.mark.parametrize(
    ("driver_id", "vendor"),
    [
        ("juniper_router", "juniper"),
        ("mikrotik_router", "mikrotik"),
        ("cisco_router", "cisco"),
        ("linux_frr", "linux_frr"),
    ],
)
def test_each_driver_normalizes_interface_keys_independently_of_vendor(driver_id: str, vendor: str) -> None:
    driver = DriverRegistry().resolve(driver_id)
    result = run(driver.execute(make_request(driver_id, "get_interfaces"), FixtureSession(fixture(vendor))))

    assert result.status == "succeeded"
    assert result.operation == "get_interfaces"
    assert result.details is not None
    assert result.details["data"]
    assert set(result.details["data"][0]) == {
        "name",
        "description",
        "admin_status",
        "oper_status",
        "mtu",
        "speed",
        "mac_address",
        "ip_addresses",
    }


@pytest.mark.parametrize("driver_id", ["juniper_router", "mikrotik_router", "cisco_router", "linux_frr"])
@pytest.mark.parametrize("operation", READ_OPERATIONS)
def test_generic_read_operations_are_declared_and_return_safe_results(driver_id: str, operation: str) -> None:
    driver = DriverRegistry().resolve(driver_id)
    result = run(driver.execute(make_request(driver_id, operation), FixtureSession(fixture(driver_id.replace("_router", "")))))

    assert result.operation == operation
    assert result.status == "succeeded"
    assert result.details is not None
    assert "data" in result.details
    assert "password" not in result.model_dump_json().lower()


def test_unsupported_operation_is_rejected_before_session_execution() -> None:
    driver = DriverRegistry().resolve("mikrotik_router")
    session = FixtureSession(fixture("mikrotik"))

    with pytest.raises(Exception) as error:
        run(driver.execute(make_request("mikrotik_router", "commit_configuration"), session))

    assert getattr(error.value, "code", None) == "unsupported_operation"
    assert session.calls == []


@pytest.mark.parametrize("operation", ["validate_configuration", "preview_configuration", "apply_configuration"])
def test_typed_configuration_rejects_raw_command_strings(operation: str) -> None:
    with pytest.raises(ValidationError):
        make_request("juniper_router", operation, {"command": "delete interfaces ge-0/0/0"})


def test_typed_configuration_builds_vendor_payload_without_raw_commands() -> None:
    driver = DriverRegistry().resolve("juniper_router")
    session = FixtureSession("validated")
    request = make_request(
        "juniper_router",
        "preview_configuration",
        {"configuration": {"interface": "ge-0/0/0", "description": "uplink", "enabled": True}},
    )

    result = run(driver.execute(request, session))

    assert result.status == "succeeded"
    assert session.calls[0][1]["configuration"]["interface"] == "ge-0/0/0"
    assert "command" not in session.calls[0][1]
