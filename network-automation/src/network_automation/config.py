"""Typed, fail-closed configuration for the automation gateway."""

from __future__ import annotations

import os
from collections.abc import Mapping

from pydantic import BaseModel, ConfigDict, Field, SecretStr, field_validator


class ConfigurationError(RuntimeError):
    """Raised when the gateway cannot safely serve internal requests."""


class GatewaySettings(BaseModel):
    """Runtime settings with bounded network and session budgets.

    Device credentials are intentionally absent from this model. Laravel sends
    decrypted credentials only in the in-memory request that executes an
    operation; they are never configured as Python service environment values.
    """

    model_config = ConfigDict(extra="forbid")

    service_name: str = Field(default="network-automation", min_length=1)
    environment: str = Field(default="production", min_length=1)
    bind_host: str = Field(default="127.0.0.1", min_length=1, max_length=255)
    port: int = Field(default=8000, ge=1, le=65535)

    # The token is optional at construction so health checks and local config
    # validation remain inspectable. Protected routes reject every request when
    # it is missing; production startup must provide it through the runtime.
    service_auth_token: SecretStr | None = None

    session_idle_timeout: float = Field(default=300.0, gt=0, le=3600)
    session_connect_timeout: float = Field(default=15.0, gt=0, le=120)
    session_command_timeout: float = Field(default=30.0, gt=0, le=300)
    session_overall_timeout: float = Field(default=60.0, gt=0, le=600)
    api_request_timeout: float = Field(default=30.0, gt=0, le=300)
    snmp_request_timeout: float = Field(default=5.0, gt=0, le=120)
    write_retries: int = Field(default=0, ge=0, le=3)

    host_key_verification: bool = True
    tls_verification: bool = True
    log_redaction: bool = True

    @field_validator("service_auth_token")
    @classmethod
    def validate_service_token(cls, value: SecretStr | None) -> SecretStr | None:
        if value is not None and len(value.get_secret_value()) < 32:
            raise ValueError("service_auth_token must contain at least 32 characters")
        return value

    def require_service_auth(self) -> str:
        """Return the service token or fail closed when it is not configured."""

        if self.service_auth_token is None:
            raise ConfigurationError("Internal service authentication is not configured")
        token = self.service_auth_token.get_secret_value()
        if not token:
            raise ConfigurationError("Internal service authentication is not configured")
        return token


def _env(environ: Mapping[str, str], name: str, default: str | None = None) -> str | None:
    value = environ.get(name, default)
    if value is None:
        return None
    value = value.strip()
    return value if value else None


def _bool_env(environ: Mapping[str, str], name: str, default: bool) -> bool:
    value = _env(environ, name)
    if value is None:
        return default
    normalized = value.lower()
    if normalized in {"1", "true", "yes", "on"}:
        return True
    if normalized in {"0", "false", "no", "off"}:
        return False
    raise ConfigurationError(f"{name} must be a boolean")


def get_settings(environ: Mapping[str, str] | None = None) -> GatewaySettings:
    """Load typed settings from environment without inventing secrets."""

    values = os.environ if environ is None else environ
    return GatewaySettings(
        service_name=_env(values, "NETWORK_AUTOMATION_SERVICE_NAME", "network-automation"),
        environment=_env(values, "NETWORK_AUTOMATION_ENVIRONMENT", "production"),
        bind_host=_env(values, "NETWORK_AUTOMATION_BIND_HOST", "127.0.0.1"),
        port=_env(values, "NETWORK_AUTOMATION_PORT", "8000"),
        service_auth_token=_env(values, "NETWORK_AUTOMATION_SERVICE_TOKEN"),
        session_idle_timeout=_env(values, "NETWORK_AUTOMATION_SESSION_IDLE_TIMEOUT", "300"),
        session_connect_timeout=_env(values, "NETWORK_AUTOMATION_SESSION_CONNECT_TIMEOUT", "15"),
        session_command_timeout=_env(values, "NETWORK_AUTOMATION_SESSION_COMMAND_TIMEOUT", "30"),
        session_overall_timeout=_env(values, "NETWORK_AUTOMATION_SESSION_OVERALL_TIMEOUT", "60"),
        api_request_timeout=_env(values, "NETWORK_AUTOMATION_API_REQUEST_TIMEOUT", "30"),
        snmp_request_timeout=_env(values, "NETWORK_AUTOMATION_SNMP_REQUEST_TIMEOUT", "5"),
        write_retries=_env(values, "NETWORK_AUTOMATION_WRITE_RETRIES", "0"),
        host_key_verification=_bool_env(values, "NETWORK_AUTOMATION_HOST_KEY_VERIFICATION", True),
        tls_verification=_bool_env(values, "NETWORK_AUTOMATION_TLS_VERIFICATION", True),
        log_redaction=_bool_env(values, "NETWORK_AUTOMATION_LOG_REDACTION", True),
    )
