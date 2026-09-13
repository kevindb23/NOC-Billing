"""Configuration models for the automation gateway."""

from pydantic import BaseModel, ConfigDict, Field


class GatewaySettings(BaseModel):
    """Small, dependency-free settings boundary for application construction."""

    model_config = ConfigDict(extra="forbid")

    service_name: str = Field(default="network-automation", min_length=1)
    environment: str = Field(default="production", min_length=1)


def get_settings() -> GatewaySettings:
    """Return the default gateway settings.

    Environment loading is intentionally left to the hosting process so this
    foundation never invents or logs credential configuration.
    """

    return GatewaySettings()
