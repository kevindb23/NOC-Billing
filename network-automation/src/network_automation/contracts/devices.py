"""Typed device and credential boundaries.

Credential values are deliberately usable by an in-process transport while
being excluded from every Pydantic serialization path.
"""

from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field, IPvAnyAddress, SecretStr


TransportName = Literal["api", "ssh", "netconf", "snmp"]


class DeviceTarget(BaseModel):
    """The non-secret information needed to select a device transport."""

    model_config = ConfigDict(extra="forbid", str_strip_whitespace=True)

    driver: str = Field(min_length=1, max_length=100)
    transport: TransportName
    hostname: str | None = Field(default=None, min_length=1, max_length=255)
    management_ip: IPvAnyAddress | None = None
    port: int | None = Field(default=None, ge=1, le=65535)
    vendor: str | None = Field(default=None, min_length=1, max_length=100)
    model: str | None = Field(default=None, min_length=1, max_length=150)
    metadata: dict[str, Any] = Field(default_factory=dict)


class DeviceCredentials(BaseModel):
    """Internal credential material, excluded from model serialization."""

    model_config = ConfigDict(extra="forbid", str_strip_whitespace=True)

    username: str | None = Field(default=None, min_length=1, max_length=255, exclude=True)
    password: SecretStr | None = Field(default=None, exclude=True)
    private_key: SecretStr | None = Field(default=None, exclude=True)
    passphrase: SecretStr | None = Field(default=None, exclude=True)
    token: SecretStr | None = Field(default=None, exclude=True)
    community: SecretStr | None = Field(default=None, exclude=True)
    tls_ca_certificate: SecretStr | None = Field(default=None, exclude=True)
    tls_certificate: SecretStr | None = Field(default=None, exclude=True)
    tls_private_key: SecretStr | None = Field(default=None, exclude=True)
