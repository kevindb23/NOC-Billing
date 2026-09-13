"""Lazy registry for the four supported transport adapters."""

from __future__ import annotations

from collections.abc import Iterable

from .api import ApiTransport
from .base import Transport
from .netconf import NetconfTransport
from .snmp import SnmpTransport
from .ssh import SshTransport


class TransportRegistry:
    """Resolve transport identifiers without importing device libraries."""

    def __init__(self, transports: Iterable[Transport] | None = None) -> None:
        self._transports: dict[str, Transport] = {}
        for transport in transports or ():
            self.register(transport)

    def register(self, transport: Transport) -> None:
        identifier = str(transport.identifier).strip().lower()
        if identifier not in {"api", "ssh", "netconf", "snmp"}:
            raise ValueError(f"Unsupported transport: {identifier}")
        self._transports[identifier] = transport

    def resolve(self, identifier: str) -> Transport:
        normalized = identifier.strip().lower()
        if normalized not in {"api", "ssh", "netconf", "snmp"}:
            raise KeyError(f"Unsupported transport: {identifier}")
        if normalized not in self._transports:
            self._transports[normalized] = {
                "api": ApiTransport,
                "ssh": SshTransport,
                "netconf": NetconfTransport,
                "snmp": SnmpTransport,
            }[normalized]()
        return self._transports[normalized]

    def identifiers(self) -> tuple[str, ...]:
        return tuple(sorted(self._transports))
