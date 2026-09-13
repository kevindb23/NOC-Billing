"""Vendor driver registry with explicit, extensible identifiers."""

from __future__ import annotations

from collections.abc import Iterable

from .base import RouterDriver
from .cisco import CiscoDriver
from .juniper import JuniperDriver
from .linux_frr import LinuxFrrDriver
from .mikrotik import MikroTikDriver


class UnsupportedDriverError(LookupError):
    def __init__(self, driver_id: str) -> None:
        self.code = "unsupported_driver"
        super().__init__(f"Unsupported router driver: {driver_id}.")


class DriverRegistry:
    """Resolve drivers without vendor conditionals in the gateway boundary."""

    def __init__(self, drivers: Iterable[RouterDriver] | None = None) -> None:
        self._drivers: dict[str, RouterDriver] = {}
        selected = drivers or (
            JuniperDriver(),
            MikroTikDriver(),
            CiscoDriver(),
            LinuxFrrDriver(),
        )
        for driver in selected:
            self.register(driver)

    def register(self, driver: RouterDriver) -> None:
        identifier = str(driver.identifier).strip().lower()
        if not identifier:
            raise ValueError("A driver identifier is required")
        self._drivers[identifier] = driver

    def resolve(self, driver_id: str) -> RouterDriver:
        identifier = str(driver_id).strip().lower()
        try:
            return self._drivers[identifier]
        except KeyError:
            raise UnsupportedDriverError(identifier) from None

    def identifiers(self) -> tuple[str, ...]:
        return tuple(sorted(self._drivers))
