"""Linux/FRR command-output parser."""

from __future__ import annotations

import re
from typing import Any

from .common import interface, neighbor, number, route, traffic


def system_info(payload: Any) -> dict[str, Any]:
    text = str(payload)
    return {
        "hostname": _match(text, r"^hostname\s+(\S+)", flags=re.MULTILINE),
        "model": "Linux/FRR",
        "version": _match(text, r"FRRouting\s+(\S+)", flags=re.IGNORECASE),
        "serial_number": None,
        "vendor": "Linux/FRR",
    }


def interfaces(payload: Any) -> list[dict[str, Any]]:
    pattern = re.compile(
        r"^Interface\s+(?P<name>\S+)\s+(?P<admin>up|down)/(?P<oper>up|down)\s+"
        r"description\s+(?P<description>.*?)\s+mtu\s+(?P<mtu>\d+)\s+speed\s+(?P<speed>\S+)\s+"
        r"mac\s+(?P<mac>\S+)\s+ip\s+(?P<ip>\S+)$",
        re.IGNORECASE | re.MULTILINE,
    )
    return [
        interface(
            match["name"],
            description=match["description"].strip() or None,
            admin_status=match["admin"],
            oper_status=match["oper"],
            mtu=number(match["mtu"]),
            speed=match["speed"],
            mac_address=match["mac"],
            ip_addresses=[match["ip"]],
        )
        for match in pattern.finditer(str(payload))
    ]


def routes(payload: Any) -> list[dict[str, Any]]:
    pattern = re.compile(
        r"^(?P<destination>\S+)\s+via\s+(?P<next_hop>\S+)\s+dev\s+(?P<interface>\S+)\s+"
        r"proto\s+(?P<protocol>\S+)\s+metric\s+(?P<metric>\d+)$",
        re.IGNORECASE | re.MULTILINE,
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
        r"BGP neighbor\s+(?P<address>\S+)\s+remote-as\s+(?P<remote_as>\d+)\s+"
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
