"""Cisco IOS/IOS-XE text parser for the normalized Router contract."""

from __future__ import annotations

import re
from typing import Any

from .common import interface, neighbor, number, route, traffic


def system_info(payload: Any) -> dict[str, Any]:
    text = str(payload)
    version = _match(text, r"Version\s+([^\s\r\n]+)")
    model = _match(text, r"cisco\s+([^\s(]+)")
    return {
        "hostname": _match(text, r"^([A-Za-z0-9_.-]+) uptime is", flags=re.MULTILINE),
        "model": model,
        "version": version,
        "serial_number": None,
        "vendor": "Cisco",
    }


def interfaces(payload: Any) -> list[dict[str, Any]]:
    values = []
    pattern = re.compile(
        r"^(?P<name>\S+)\s+(?P<admin>up|down)\s+(?P<oper>up|down)\s+"
        r"(?P<description>.*?)\s+(?P<mtu>\d+)\s+(?P<speed>\S+)\s+(?P<mac>[0-9a-f.:-]+)$",
        re.IGNORECASE | re.MULTILINE,
    )
    for match in pattern.finditer(str(payload)):
        values.append(
            interface(
                match["name"],
                description=match["description"].strip() or None,
                admin_status=match["admin"],
                oper_status=match["oper"],
                mtu=number(match["mtu"]),
                speed=match["speed"],
                mac_address=match["mac"],
                ip_addresses=_ips_for(str(payload), match["name"]),
            )
        )
    return values


def routes(payload: Any) -> list[dict[str, Any]]:
    pattern = re.compile(
        r"Route\s+(?P<destination>\S+)\s+via\s+(?P<next_hop>\S+),\s+"
        r"(?P<interface>\S+),\s+(?P<protocol>\S+),\s+metric\s+(?P<metric>\d+)",
        re.IGNORECASE,
    )
    return [
        route(
            match["destination"],
            next_hop=match["next_hop"],
            interface_name=match["interface"],
            protocol=match["protocol"],
            metric=number(match["metric"]),
        )
        for match in pattern.finditer(str(payload))
    ]


def bgp_neighbors(payload: Any) -> list[dict[str, Any]]:
    pattern = re.compile(
        r"BGP neighbor\s+(?P<address>\S+)\s+remote AS\s+(?P<remote_as>\d+)\s+"
        r"state\s+(?P<state>\S+)\s+uptime\s+(?P<uptime>\S+)\s+prefixes\s+(?P<prefixes>\d+)",
        re.IGNORECASE,
    )
    return [
        neighbor(
            match["address"],
            remote_as=number(match["remote_as"]),
            state=match["state"],
            uptime=match["uptime"],
            prefixes_received=number(match["prefixes"]),
        )
        for match in pattern.finditer(str(payload))
    ]


def traffic_counters(payload: Any) -> list[dict[str, Any]]:
    pattern = re.compile(
        r"Traffic\s+(?P<interface>\S+)\s+input-bytes\s+(?P<input_bytes>\d+)\s+"
        r"output-bytes\s+(?P<output_bytes>\d+)\s+input-packets\s+(?P<input_packets>\d+)\s+"
        r"output-packets\s+(?P<output_packets>\d+)",
        re.IGNORECASE,
    )
    return [
        traffic(
            match["interface"],
            input_bytes=number(match["input_bytes"]),
            output_bytes=number(match["output_bytes"]),
            input_packets=number(match["input_packets"]),
            output_packets=number(match["output_packets"]),
        )
        for match in pattern.finditer(str(payload))
    ]


def _match(text: str, pattern: str, *, flags: int = 0) -> str | None:
    match = re.search(pattern, text, flags)
    return match.group(1) if match else None


def _ips_for(text: str, interface_name: str) -> list[str]:
    match = re.search(rf"Interface\s+{re.escape(interface_name)}.*?IP address:\s+(\S+)", text, re.DOTALL)
    return [match.group(1)] if match else []
