"""Requests accepted by the gateway operation boundary."""

from typing import Any, Literal
from uuid import uuid4

from pydantic import BaseModel, ConfigDict, Field, StringConstraints
from typing_extensions import Annotated

from .devices import DeviceCredentials, DeviceTarget


OperationName = Literal["connection_test", "system_info"]
CorrelationId = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=128)]


class OperationRequest(BaseModel):
    """A safe, normalized request for a supported device operation."""

    model_config = ConfigDict(extra="forbid", str_strip_whitespace=True)

    operation: OperationName
    device: DeviceTarget
    credentials: DeviceCredentials | None = None
    parameters: dict[str, Any] = Field(default_factory=dict)
    correlation_id: CorrelationId = Field(default_factory=lambda: str(uuid4()))
