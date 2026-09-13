"""Juniper router driver with XML/RPC and CLI operation mappings."""

from __future__ import annotations

from collections.abc import Mapping
from typing import Any

from network_automation.contracts.operations import OperationRequest

from .base import CONFIGURATION_OPERATIONS, READ_OPERATIONS, RouterDriver
from .configuration import InterfaceConfiguration
from .parsers import juniper


class JuniperDriver(RouterDriver):
    identifier = "juniper_router"
    vendor = "Juniper"
    _capabilities = frozenset(READ_OPERATIONS | CONFIGURATION_OPERATIONS)

    def read_payload(self, operation: str, transport: str) -> Mapping[str, Any]:
        rpc = {
            "get_system_info": "get-system-information",
            "get_device_facts": "get-system-information",
            "get_interfaces": "get-interface-information",
            "get_interface_status": "get-interface-information",
            "get_routes": "get-route-information",
            "get_bgp_neighbors": "get-bgp-neighbor-information",
            "get_traffic_counters": "get-interface-information",
        }.get(operation, "get-system-information")
        if transport == "netconf":
            return {"rpc": f"<{rpc}/>"}
        if transport == "api":
            return {"path": f"/junos/{operation}"}
        if transport == "snmp":
            return {"oid": "1.3.6.1.2.1.1.1.0"}
        return {"command": self._cli_command(operation)}

    def normalize(self, operation: str, payload: Any) -> Any:
        if operation == "test_connection":
            return {"connected": True}
        if operation in {"get_system_info", "get_device_facts"}:
            return juniper.system_info(payload)
        if operation in {"get_interfaces", "get_interface_status"}:
            return juniper.interfaces(payload)
        if operation == "get_routes":
            return juniper.routes(payload)
        if operation == "get_bgp_neighbors":
            return juniper.bgp_neighbors(payload)
        if operation == "get_traffic_counters":
            return juniper.traffic_counters(payload)
        return payload

    def configuration_payload(self, operation: str, configuration: InterfaceConfiguration) -> Mapping[str, Any]:
        return {
            "configuration": configuration.model_dump(exclude_none=True),
            "vendor_action": operation,
            "rpc": f"<{operation.replace('_', '-')}/>" if operation != "validate_configuration" else "<load-configuration/> ",
        }

    @staticmethod
    def _cli_command(operation: str) -> str:
        return {
            "test_connection": "show version | no-more",
            "get_system_info": "show version | no-more",
            "get_device_facts": "show chassis hardware | no-more",
            "get_interfaces": "show interfaces terse | no-more",
            "get_interface_status": "show interfaces terse | no-more",
            "get_routes": "show route | no-more",
            "get_bgp_neighbors": "show bgp summary | no-more",
            "get_traffic_counters": "show interfaces extensive | no-more",
        }.get(operation, "show version | no-more")
