"""Normalized operation result contracts."""

from datetime import datetime, timezone
from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator
from typing_extensions import Annotated

from .devices import TransportName
from .operations import CorrelationId, OperationName
from network_automation.security.redaction import redact, redact_exception_message


NormalizedStatus = Literal["connected", "not_configured", "unsupported", "failed"]
DriverName = Annotated[str, Field(min_length=1, max_length=100)]


class OperationResult(BaseModel):
    """Stable result shape independent of vendor protocol or CLI output."""

    model_config = ConfigDict(extra="forbid")

    operation: OperationName
    status: NormalizedStatus
    driver: DriverName
    transport: TransportName
    correlation_id: CorrelationId
    message: str = Field(min_length=1, max_length=2000)
    checked_at: datetime = Field(default_factory=lambda: datetime.now(timezone.utc))
    details: dict[str, Any] | None = None

    @field_validator("message", mode="before")
    @classmethod
    def redact_message(cls, value: Any) -> Any:
        if isinstance(value, str):
            return redact_exception_message(value)
        return value

    @field_validator("details", mode="before")
    @classmethod
    def redact_details(cls, value: dict[str, Any] | None) -> dict[str, Any] | None:
        if value is None:
            return None
        return redact(value)
