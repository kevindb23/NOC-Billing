import pytest

from network_automation.config import ConfigurationError, GatewaySettings, get_settings


def test_missing_service_auth_fails_closed() -> None:
    settings = get_settings({})

    with pytest.raises(ConfigurationError):
        settings.require_service_auth()


def test_service_auth_is_typed_and_not_defaulted() -> None:
    settings = get_settings(
        {"NETWORK_AUTOMATION_SERVICE_TOKEN": "x" * 32}
    )

    assert settings.require_service_auth() == "x" * 32
    assert settings.service_auth_token is not None
    assert "x" * 32 not in repr(settings)


def test_secure_transport_defaults_and_bounded_timeouts() -> None:
    settings = GatewaySettings()

    assert settings.host_key_verification is True
    assert settings.tls_verification is True
    assert settings.write_retries == 0
    assert 0 < settings.session_idle_timeout <= 3600
    assert 0 < settings.session_connect_timeout <= 120
    assert 0 < settings.session_command_timeout <= 300
    assert 0 < settings.session_overall_timeout <= 600


@pytest.mark.parametrize(
    "field",
    [
        "session_idle_timeout",
        "session_connect_timeout",
        "session_command_timeout",
        "session_overall_timeout",
        "api_request_timeout",
        "snmp_request_timeout",
    ],
)
def test_timeouts_must_be_positive(field: str) -> None:
    with pytest.raises(ValueError):
        GatewaySettings(**{field: 0})


def test_write_retries_are_bounded_and_cannot_default_to_a_retry() -> None:
    with pytest.raises(ValueError):
        GatewaySettings(write_retries=4)

    assert GatewaySettings().write_retries == 0


def test_environment_values_are_typed_without_device_credentials() -> None:
    settings = get_settings(
        {
            "NETWORK_AUTOMATION_SERVICE_TOKEN": "t" * 40,
            "NETWORK_AUTOMATION_PORT": "8100",
            "NETWORK_AUTOMATION_SESSION_COMMAND_TIMEOUT": "45",
            "NETWORK_AUTOMATION_HOST_KEY_VERIFICATION": "false",
            "NETWORK_AUTOMATION_TLS_VERIFICATION": "true",
        }
    )

    assert settings.port == 8100
    assert settings.session_command_timeout == 45
    assert settings.host_key_verification is False
    assert settings.tls_verification is True
    assert not hasattr(settings, "password")
