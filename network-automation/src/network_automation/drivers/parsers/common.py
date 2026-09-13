"""Small helpers shared by deterministic vendor parsers."""

from __future__ import annotations

from typing import Any


INTERFACE_KEYS = (
    "name",
    "description",
    "admin_status",
    "oper_status",
    "mtu",
    "speed",
    "mac_address",
    "ip_addresses",
)


def interface(
    name: str,
    *,
    description: str | None = None,
    admin_status: str = "unknown",
    oper_status: str = "unknown",
    mtu: int | None = None,
    speed: str | None = None,
    mac_address: str | None = None,
    ip_addresses: list[str] | None = None,
) -> dict[str, Any]:
    return {
        "name": name,
        "description": description,
        "admin_status": admin_status.lower(),
        "oper_status": oper_status.lower(),
        "mtu": mtu,
        "speed": speed,
        "mac_address": mac_address,
        "ip_addresses": ip_addresses or [],
    }


def number(value: object) -> int | None:
    try:
        return int(str(value).replace(",", ""))
    except (TypeError, ValueError):
        return None


def route(
    destination: str,
    *,
    next_hop: str | None = None,
    interface_name: str | None = None,
    protocol: str | None = None,
    metric: int | None = None,
) -> dict[str, Any]:
    prefix = None
    if "/" in destination:
        destination, prefix = destination.split("/", 1)
    return {
        "destination": destination,
        "prefix_length": number(prefix),
        "next_hop": next_hop,
        "interface": interface_name,
        "protocol": protocol,
        "metric": metric,
    }


def neighbor(
    address: str,
    *,
    remote_as: int | None = None,
    state: str | None = None,
    uptime: str | None = None,
    prefixes_received: int | None = None,
) -> dict[str, Any]:
    return {
        "neighbor": address,
        "remote_as": remote_as,
        "state": state.lower() if isinstance(state, str) else state,
        "uptime": uptime,
        "prefixes_received": prefixes_received,
    }


def traffic(
    interface_name: str,
    *,
    input_bytes: int | None = None,
    output_bytes: int | None = None,
    input_packets: int | None = None,
    output_packets: int | None = None,
) -> dict[str, Any]:
    return {
        "interface": interface_name,
        "input_bytes": input_bytes,
        "output_bytes": output_bytes,
        "input_packets": input_packets,
        "output_packets": output_packets,
    }
