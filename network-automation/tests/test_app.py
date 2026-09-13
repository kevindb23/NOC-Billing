from datetime import datetime, timezone

import pytest
from fastapi.testclient import TestClient
from pydantic import SecretStr, ValidationError

from network_automation.app import create_app
from network_automation.contracts.devices import DeviceCredentials, DeviceTarget
from network_automation.contracts.operations import OperationRequest
from network_automation.contracts.results import OperationResult


def test_run_command_request_is_rejected_by_the_safe_operation_contract() -> None:
    with pytest.raises(ValidationError):
        OperationRequest(
            operation="run_command",
            parameters={"command": "show secret"},
            device=DeviceTarget(
                driver="mikrotik_router",
                transport="api",
                hostname="edge-router.example.test",
            ),
        )


def test_approved_generic_operation_names_are_accepted() -> None:
    target = DeviceTarget(
        driver="mikrotik_router",
        transport="api",
        hostname="edge-router.example.test",
    )

    assert OperationRequest(operation="test_connection", device=target).operation == "test_connection"
    assert OperationRequest(operation="get_system_info", device=target).operation == "get_system_info"


def test_mock_is_rejected_as_a_transport_for_real_operations() -> None:
    with pytest.raises(ValidationError):
        OperationRequest(
            operation="test_connection",
            device=DeviceTarget(
                driver="mikrotik_router",
                transport="mock",
                hostname="edge-router.example.test",
            ),
        )


@pytest.mark.parametrize("sensitive_key", ["command", "password", "token"])
def test_operation_parameters_reject_sensitive_keys(sensitive_key: str) -> None:
    with pytest.raises(ValidationError):
        OperationRequest(
            operation="test_connection",
            parameters={sensitive_key: "test-only-value"},
            device=DeviceTarget(
                driver="mikrotik_router",
                transport="api",
                hostname="edge-router.example.test",
            ),
        )


@pytest.mark.parametrize("sensitive_key", ["command", "password", "token"])
def test_device_metadata_rejects_nested_sensitive_keys(sensitive_key: str) -> None:
    with pytest.raises(ValidationError):
        DeviceTarget(
            driver="mikrotik_router",
            transport="api",
            hostname="edge-router.example.test",
            metadata={"site": "lab", "connection": {sensitive_key: "test-only-value"}},
        )


@pytest.mark.parametrize("boundary", ["parameters", "metadata"])
@pytest.mark.parametrize(
    "credential_like_value",
    [
        "password=password-value",
        "Authorization: Bearer bearer-value",
        "-----BEGIN PRIVATE KEY-----\nprivate-key-value\n-----END PRIVATE KEY-----",
        SecretStr("secret-value"),
    ],
)
def test_generic_boundaries_reject_nested_credential_like_values(
    boundary: str,
    credential_like_value: object,
) -> None:
    nested_value = {"connection": [{"value": credential_like_value}]}

    with pytest.raises(ValidationError):
        if boundary == "parameters":
            OperationRequest(
                operation="test_connection",
                parameters=nested_value,
                device=DeviceTarget(
                    driver="mikrotik_router",
                    transport="api",
                    hostname="edge-router.example.test",
                ),
            )
        else:
            DeviceTarget(
                driver="mikrotik_router",
                transport="api",
                hostname="edge-router.example.test",
                metadata=nested_value,
            )


def test_typed_generic_operation_parameters_remain_available_to_future_drivers() -> None:
    request = OperationRequest(
        operation="get_system_info",
        parameters={
            "timeout_seconds": 5,
            "interfaces": ["ether1", "ether2"],
            "filters": {"site": "lab", "enabled": True},
        },
        device=DeviceTarget(
            driver="mikrotik_router",
            transport="api",
            hostname="edge-router.example.test",
        ),
    )

    assert request.parameters == {
        "timeout_seconds": 5,
        "interfaces": ["ether1", "ether2"],
        "filters": {"site": "lab", "enabled": True},
    }


def test_health_route_returns_a_stable_ok_payload() -> None:
    response = TestClient(create_app()).get("/health")

    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_app_keeps_driver_and_transport_registries_in_dependency_state() -> None:
    driver_registry = object()
    transport_registry = object()

    app = create_app(
        driver_registry=driver_registry,
        transport_registry=transport_registry,
    )

    assert app.state.driver_registry is driver_registry
    assert app.state.transport_registry is transport_registry


def test_device_target_has_typed_network_and_transport_fields() -> None:
    target = DeviceTarget(
        driver="juniper_router",
        transport="netconf",
        hostname="core-router.example.test",
        management_ip="192.0.2.10",
        port=830,
        vendor="juniper",
        model="mx204",
    )

    assert str(target.management_ip) == "192.0.2.10"
    assert target.port == 830
    assert target.transport == "netconf"


def test_credentials_are_available_to_transports_but_not_raw_serialization() -> None:
    credentials = DeviceCredentials(
        username="automation",
        password="do-not-expose",
        private_key="PRIVATE KEY MATERIAL",
        token="token-value",
        community="community-value",
    )

    serialized = credentials.model_dump()
    serialized_json = credentials.model_dump_json()

    assert credentials.password.get_secret_value() == "do-not-expose"
    assert "password" not in serialized
    assert "private_key" not in serialized
    assert "do-not-expose" not in serialized_json
    assert "PRIVATE KEY MATERIAL" not in serialized_json
    assert "token-value" not in serialized_json


def test_operation_result_has_a_normalized_status_and_safe_details() -> None:
    result = OperationResult(
        operation="test_connection",
        status="not_configured",
        driver="mikrotik_router",
        transport="api",
        correlation_id="request-123",
        message="No router transport is configured.",
        checked_at=datetime(2026, 9, 13, tzinfo=timezone.utc),
        details={"region": "lab", "password": "hidden"},
    )

    assert result.status == "not_configured"
    assert result.correlation_id == "request-123"
    assert result.details == {"region": "lab", "password": "[REDACTED]"}


def test_operation_result_redacts_sensitive_message_values() -> None:
    result = OperationResult(
        operation="test_connection",
        status="failed",
        driver="mikrotik_router",
        transport="api",
        correlation_id="request-456",
        message="transport failed password=test-password token=test-token",
    )

    assert "test-password" not in result.message
    assert "test-token" not in result.message
    assert result.message == "transport failed password=[REDACTED] token=[REDACTED]"


def test_operation_result_applies_the_same_redaction_policy_to_command_values() -> None:
    result = OperationResult(
        operation="test_connection",
        status="failed",
        driver="mikrotik_router",
        transport="api",
        correlation_id="request-789",
        message='driver rejected command="show configuration secrets"',
    )

    assert "show configuration secrets" not in result.message
    assert result.message == "driver rejected command=[REDACTED]"
