"""Shared driver lifecycle, capability checks, and normalized result handling."""

from __future__ import annotations

from abc import ABC, abstractmethod
from collections.abc import Mapping
from typing import Any, ClassVar

from network_automation.contracts.operations import OperationRequest
from network_automation.contracts.results import OperationResult
from network_automation.security.redaction import redact
from network_automation.transports.base import DeviceSession, TransportError, maybe_await

from .configuration import InterfaceConfiguration, parse_configuration

READ_OPERATIONS = frozenset(
    {
        "test_connection",
        "get_system_info",
        "get_device_facts",
        "get_interfaces",
        "get_interface_status",
        "get_routes",
        "get_bgp_neighbors",
        "get_traffic_counters",
    }
)
CONFIGURATION_OPERATIONS = frozenset(
    {
        "validate_configuration",
        "preview_configuration",
        "apply_configuration",
        "commit_configuration",
        "rollback_configuration",
    }
)


class DriverError(RuntimeError):
    """A stable safe error from a vendor driver."""

    def __init__(self, code: str, message: str) -> None:
        self.code = code
        super().__init__(message)


class UnsupportedOperationError(DriverError):
    def __init__(self, driver: str, operation: str) -> None:
        super().__init__("unsupported_operation", f"{operation} is not supported by {driver}.")


class RouterDriver(ABC):
    """Base class that keeps vendor behavior local and transport lifecycle external."""

    identifier: ClassVar[str]
    vendor: ClassVar[str]
    _capabilities: ClassVar[frozenset[str]]

    def capabilities(self) -> set[str]:
        return set(self._capabilities)

    async def execute(self, request: OperationRequest, session: DeviceSession) -> OperationResult:
        operation = request.operation
        if operation not in self._capabilities:
            raise UnsupportedOperationError(self.identifier, operation)

        payload: Mapping[str, Any]
        if operation in CONFIGURATION_OPERATIONS:
            configuration = parse_configuration(request.parameters)
            payload = self.configuration_payload(operation, configuration)
        else:
            payload = self.read_payload(operation, request.device.transport)

        try:
            raw = await maybe_await(session.execute(operation, payload))
            data = self.normalize(operation, raw)
        except DriverError:
            raise
        except TransportError:
            raise
        except Exception as error:
            raise DriverError("parse_failed", "The device response could not be normalized.") from None

        return OperationResult(
            operation=operation,
            status="succeeded",
            driver=self.identifier,
            transport=request.device.transport,
            correlation_id=request.correlation_id,
            message=f"{operation} completed.",
            details={"data": redact(data), "vendor": self.vendor},
        )

    @abstractmethod
    def normalize(self, operation: str, payload: Any) -> Any:
        """Parse one vendor payload into a normalized result value."""

    @abstractmethod
    def read_payload(self, operation: str, transport: str) -> Mapping[str, Any]:
        """Build a driver-owned request for the selected transport."""

    @abstractmethod
    def configuration_payload(self, operation: str, configuration: InterfaceConfiguration) -> Mapping[str, Any]:
        """Translate typed configuration into an internal driver payload."""
