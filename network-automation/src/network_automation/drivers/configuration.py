"""Typed, vendor-neutral configuration input for Router UI operations."""

from __future__ import annotations

from collections.abc import Mapping
from typing import Any

from pydantic import BaseModel, ConfigDict, Field, model_validator


class InterfaceConfiguration(BaseModel):
    """The deliberately small first configuration shape exposed to the UI."""

    model_config = ConfigDict(extra="forbid", str_strip_whitespace=True)

    interface: str = Field(min_length=1, max_length=128)
    enabled: bool | None = None
    description: str | None = Field(default=None, max_length=255)
    vlan_id: int | None = Field(default=None, ge=1, le=4094)
    subinterface: int | None = Field(default=None, ge=0, le=65535)

    @model_validator(mode="after")
    def require_a_change(self) -> "InterfaceConfiguration":
        if all(value is None for value in (self.enabled, self.description, self.vlan_id, self.subinterface)):
            raise ValueError("At least one interface change is required")
        return self


class ConfigurationParameters(BaseModel):
    """Envelope accepted by all configuration operations."""

    model_config = ConfigDict(extra="forbid")

    configuration: InterfaceConfiguration


def parse_configuration(parameters: Mapping[str, Any]) -> InterfaceConfiguration:
    """Validate a UI configuration payload and reject arbitrary commands."""

    return ConfigurationParameters.model_validate(parameters).configuration
