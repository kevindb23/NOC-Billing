"""MikroTik RouterOS driver."""

from __future__ import annotations

from collections.abc import Mapping
from typing import Any

from .base import READ_OPERATIONS, RouterDriver
from .configuration import InterfaceConfiguration
from .parsers import mikrotik


class MikroTikDriver(RouterDriver):
    identifier = "mikrotik_router"
    vendor = "MikroTik"
    _capabilities = frozenset(READ_OPERATIONS | {"validate_configuration", "preview_configuration", "apply_configuration"})

    def read_payload(self, operation: str, transport: str) -> Mapping[str, Any]:
        path = {
            "get_system_info": "/rest/system/resource",
            "get_device_facts": "/rest/system/resource",
            "get_interfaces": "/rest/interface",
            "get_interface_status": "/rest/interface",
            "get_routes": "/rest/ip/route",
            "get_bgp_neighbors": "/rest/routing/bgp/session",
            "get_traffic_counters": "/rest/interface",
        }.get(operation, "/rest/system/resource")
        if transport == "api":
            return {"path": path}
        if transport == "snmp":
            return {"oid": "1.3.6.1.2.1.1.1.0"}
        return {"command": self._cli_command(operation)}

    def normalize(self, operation: str, payload: Any) -> Any:
        if operation == "test_connection":
            return {"connected": True}
        if operation in {"get_system_info", "get_device_facts"}:
            return mikrotik.system_info(payload)
        if operation in {"get_interfaces", "get_interface_status"}:
            return mikrotik.interfaces(payload)
        if operation == "get_routes":
            return mikrotik.routes(payload)
        if operation == "get_bgp_neighbors":
            return mikrotik.bgp_neighbors(payload)
        if operation == "get_traffic_counters":
            return mikrotik.traffic_counters(payload)
        return payload

    def configuration_payload(self, operation: str, configuration: InterfaceConfiguration) -> Mapping[str, Any]:
        return {
            "configuration": configuration.model_dump(exclude_none=True),
            "vendor_action": operation,
            "path": "/rest/interface",
        }

    @staticmethod
    def _cli_command(operation: str) -> str:
        return {
            "test_connection": "/system/resource/print",
            "get_system_info": "/system/resource/print",
            "get_device_facts": "/system/routerboard/print",
            "get_interfaces": "/interface/print detail",
            "get_interface_status": "/interface/print detail",
            "get_routes": "/ip/route/print detail",
            "get_bgp_neighbors": "/routing/bgp/session/print detail",
            "get_traffic_counters": "/interface/monitor-traffic",
        }.get(operation, "/system/resource/print")
