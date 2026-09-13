"""Juniper XML/RPC response parser."""

from __future__ import annotations

import xml.etree.ElementTree as ET
from typing import Any

from .common import interface, neighbor, number, route, traffic


def _root(payload: Any) -> ET.Element:
    if isinstance(payload, ET.Element):
        return payload
    return ET.fromstring(str(payload))


def _text(element: ET.Element, name: str, default: str | None = None) -> str | None:
    child = element.find(name)
    return child.text.strip() if child is not None and child.text else default


def system_info(payload: Any) -> dict[str, Any]:
    root = _root(payload)
    info = root.find("software-information") or root
    return {
        "hostname": _text(info, "host-name"),
        "model": _text(info, "product-model"),
        "version": _text(info, "junos-version"),
        "serial_number": _text(info, "serial-number"),
        "vendor": "Juniper",
    }


def interfaces(payload: Any) -> list[dict[str, Any]]:
    root = _root(payload)
    values = []
    for item in root.findall(".//physical-interface"):
        addresses = [value.text.strip() for value in item.findall(".//ifa-local") if value.text]
        values.append(
            interface(
                _text(item, "name", "") or "",
                description=_text(item, "description"),
                admin_status=_text(item, "admin-status", "unknown") or "unknown",
                oper_status=_text(item, "oper-status", "unknown") or "unknown",
                mtu=number(_text(item, "mtu")),
                speed=_text(item, "speed"),
                mac_address=_text(item, "current-physical-address"),
                ip_addresses=addresses,
            )
        )
    return [item for item in values if item["name"]]


def routes(payload: Any) -> list[dict[str, Any]]:
    root = _root(payload)
    return [
        route(
            _text(item, "destination", "") or "",
            next_hop=_text(item, "next-hop"),
            interface_name=_text(item, "interface-name"),
            protocol=_text(item, "protocol"),
            metric=number(_text(item, "metric")),
        )
        for item in root.findall(".//route")
        if _text(item, "destination")
    ]


def bgp_neighbors(payload: Any) -> list[dict[str, Any]]:
    root = _root(payload)
    return [
        neighbor(
            _text(item, "peer-address", "") or "",
            remote_as=number(_text(item, "peer-as")),
            state=_text(item, "peer-state"),
            uptime=_text(item, "elapsed-time"),
            prefixes_received=number(_text(item, "active-prefix-count")),
        )
        for item in root.findall(".//bgp-peer")
        if _text(item, "peer-address")
    ]


def traffic_counters(payload: Any) -> list[dict[str, Any]]:
    root = _root(payload)
    return [
        traffic(
            _text(item, "interface-name", "") or "",
            input_bytes=number(_text(item, "input-bytes")),
            output_bytes=number(_text(item, "output-bytes")),
            input_packets=number(_text(item, "input-packets")),
            output_packets=number(_text(item, "output-packets")),
        )
        for item in root.findall(".//traffic")
        if _text(item, "interface-name")
    ]
