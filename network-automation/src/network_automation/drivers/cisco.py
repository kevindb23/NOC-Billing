"""Cisco IOS/IOS-XE driver."""

from __future__ import annotations

from collections.abc import Mapping
from typing import Any

from .base import CONFIGURATION_OPERATIONS, READ_OPERATIONS, RouterDriver
from .configuration import InterfaceConfiguration
from .parsers import cisco


class CiscoDriver(RouterDriver):
    identifier = "cisco_router"
    vendor = "Cisco"
    _capabilities = frozenset(READ_OPERATIONS | CONFIGURATION_OPERATIONS)

    def read_payload(self, operation: str, transport: str) -> Mapping[str, Any]:
        if transport == "api":
            return {"path": f"/api/router/{operation}"}
        if transport == "snmp":
            return {"oid": "1.3.6.1.2.1.1.1.0"}
        return {"command": self._cli_command(operation)}

    def normalize(self, operation: str, payload: Any) -> Any:
        if operation == "test_connection":
            return {"connected": True}
        if operation in {"get_system_info", "get_device_facts"}:
            return cisco.system_info(payload)
        if operation in {"get_interfaces", "get_interface_status"}:
            return cisco.interfaces(payload)
        if operation == "get_routes":
            return cisco.routes(payload)
        if operation == "get_bgp_neighbors":
            return cisco.bgp_neighbors(payload)
        if operation == "get_traffic_counters":
            return cisco.traffic_counters(payload)
        return payload

    def configuration_payload(self, operation: str, configuration: InterfaceConfiguration) -> Mapping[str, Any]:
        return {
            "configuration": configuration.model_dump(exclude_none=True),
            "vendor_action": operation,
            "commands": self._configuration_commands(operation, configuration),
        }

    @staticmethod
    def _cli_command(operation: str) -> str:
        return {
            "test_connection": "show version",
            "get_system_info": "show version",
            "get_device_facts": "show inventory",
            "get_interfaces": "show interfaces description",
            "get_interface_status": "show interfaces status",
            "get_routes": "show ip route",
            "get_bgp_neighbors": "show ip bgp summary",
            "get_traffic_counters": "show interfaces counters",
        }.get(operation, "show version")

    @staticmethod
    def _configuration_commands(operation: str, configuration: InterfaceConfiguration) -> list[str]:
        values = [f"interface {configuration.interface}"]
        if configuration.description is not None:
            values.append(f"description {configuration.description}")
        if configuration.enabled is True:
            values.append("no shutdown")
        elif configuration.enabled is False:
            values.append("shutdown")
        if configuration.vlan_id is not None:
            values.append(f"encapsulation dot1Q {configuration.vlan_id}")
        return values if operation != "validate_configuration" else ["".join(values)]
