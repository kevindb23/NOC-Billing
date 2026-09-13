"""MikroTik RouterOS XML/API response parser."""

from __future__ import annotations

import xml.etree.ElementTree as ET
from typing import Any

from .common import interface, neighbor, number, route, traffic


def _root(payload: Any) -> ET.Element:
    if isinstance(payload, ET.Element):
        return payload
    return ET.fromstring(str(payload))


def _value(item: ET.Element, name: str, default: str | None = None) -> str | None:
    return item.attrib.get(name, default)


def system_info(payload: Any) -> dict[str, Any]:
    root = _root(payload)
    item = root.find("system") or root
    return {
        "hostname": _value(item, "identity"),
        "model": _value(item, "model"),
        "version": _value(item, "version"),
        "serial_number": _value(item, "serial-number"),
        "vendor": "MikroTik",
    }


def interfaces(payload: Any) -> list[dict[str, Any]]:
    root = _root(payload)
    values = []
    for item in root.findall(".//interface"):
        values.append(
            interface(
                _value(item, "name", "") or "",
                description=_value(item, "comment"),
                admin_status="down" if _value(item, "disabled", "false") == "true" else "up",
                oper_status="up" if _value(item, "running", "false") == "true" else "down",
                mtu=number(_value(item, "mtu")),
                speed=_value(item, "speed"),
                mac_address=_value(item, "mac-address"),
            )
        )
    return [item for item in values if item["name"]]


def routes(payload: Any) -> list[dict[str, Any]]:
    root = _root(payload)
    return [
        route(
            _value(item, "dst-address", "") or "",
            next_hop=_value(item, "gateway"),
            interface_name=_value(item, "interface"),
            protocol=_value(item, "routing-table"),
            metric=number(_value(item, "distance")),
        )
        for item in root.findall(".//route")
        if _value(item, "dst-address")
    ]


def bgp_neighbors(payload: Any) -> list[dict[str, Any]]:
    root = _root(payload)
    return [
        neighbor(
            _value(item, "peer", "") or "",
            remote_as=number(_value(item, "remote-as")),
            state=_value(item, "state"),
            uptime=_value(item, "uptime"),
            prefixes_received=number(_value(item, "prefix-count")),
        )
        for item in root.findall(".//bgp")
        if _value(item, "peer")
    ]


def traffic_counters(payload: Any) -> list[dict[str, Any]]:
    root = _root(payload)
    return [
        traffic(
            _value(item, "interface", "") or "",
            input_bytes=number(_value(item, "rx-byte")),
            output_bytes=number(_value(item, "tx-byte")),
            input_packets=number(_value(item, "rx-packet")),
            output_packets=number(_value(item, "tx-packet")),
        )
        for item in root.findall(".//traffic")
        if _value(item, "interface")
    ]
