"""Linux/FRR driver using iproute2 and vtysh-safe typed operations."""

from __future__ import annotations

from collections.abc import Mapping
from typing import Any

from .base import READ_OPERATIONS, RouterDriver
from .configuration import InterfaceConfiguration
from .parsers import linux_frr


class LinuxFrrDriver(RouterDriver):
    identifier = "linux_frr"
    vendor = "Linux/FRR"
    _capabilities = frozenset(READ_OPERATIONS | {"validate_configuration", "preview_configuration", "apply_configuration"})

    def read_payload(self, operation: str, transport: str) -> Mapping[str, Any]:
        if transport == "api":
            return {"path": f"/api/frr/{operation}"}
        if transport == "snmp":
            return {"oid": "1.3.6.1.2.1.1.1.0"}
        return {"command": self._cli_command(operation)}

    def normalize(self, operation: str, payload: Any) -> Any:
        if operation == "test_connection":
            return {"connected": True}
        if operation in {"get_system_info", "get_device_facts"}:
            return linux_frr.system_info(payload)
        if operation in {"get_interfaces", "get_interface_status"}:
            return linux_frr.interfaces(payload)
        if operation == "get_routes":
            return linux_frr.routes(payload)
        if operation == "get_bgp_neighbors":
            return linux_frr.bgp_neighbors(payload)
        if operation == "get_traffic_counters":
            return linux_frr.traffic_counters(payload)
        return payload

    def configuration_payload(self, operation: str, configuration: InterfaceConfiguration) -> Mapping[str, Any]:
        return {
            "configuration": configuration.model_dump(exclude_none=True),
            "vendor_action": operation,
            "commands": self._configuration_commands(configuration),
        }

    @staticmethod
    def _cli_command(operation: str) -> str:
        return {
            "test_connection": "show version",
            "get_system_info": "show version",
            "get_device_facts": "show version",
            "get_interfaces": "show interface",
            "get_interface_status": "show interface",
            "get_routes": "show ip route",
            "get_bgp_neighbors": "show bgp summary",
            "get_traffic_counters": "show interface",
        }.get(operation, "show version")

    @staticmethod
    def _configuration_commands(configuration: InterfaceConfiguration) -> list[str]:
        values = [f"interface {configuration.interface}"]
        if configuration.description is not None:
            values.append(f"description {configuration.description}")
        if configuration.enabled is True:
            values.append("no shutdown")
        elif configuration.enabled is False:
            values.append("shutdown")
        if configuration.vlan_id is not None:
            values.append(f"encapsulation dot1q {configuration.vlan_id}")
        return values
