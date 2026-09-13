from network_automation.security.redaction import (
    REDACTED,
    redact,
    redact_exception_message,
)


def test_redaction_recurses_through_nested_mappings_and_lists() -> None:
    payload = {
        "router_id": "router-42",
        "correlation_id": "request-123",
        "metadata": {"site": "lab", "token": "token-value"},
        "credentials": {
            "username": "safe-to-use-internally",
            "password": "password-value",
            "private_key": "private-key-value",
            "community": "community-value",
        },
        "headers": [{"Authorization": "Bearer token-value"}, {"cookie": "sid=secret"}],
        "items": [{"name": "uplink", "secret": "secret-value"}],
    }

    redacted = redact(payload)

    assert redacted["router_id"] == "router-42"
    assert redacted["correlation_id"] == "request-123"
    assert redacted["metadata"]["site"] == "lab"
    assert redacted["metadata"]["token"] == REDACTED
    assert redacted["credentials"] == REDACTED
    assert redacted["headers"][0]["Authorization"] == REDACTED
    assert redacted["headers"][1]["cookie"] == REDACTED
    assert redacted["items"][0]["name"] == "uplink"
    assert redacted["items"][0]["secret"] == REDACTED


def test_exception_message_redaction_masks_credential_values_and_preserves_correlation_id() -> None:
    message = (
        "transport failed password=password-value token=token-value "
        "Authorization: Bearer bearer-value correlation_id=request-123"
    )

    redacted = redact_exception_message(message)

    assert "password-value" not in redacted
    assert "token-value" not in redacted
    assert "bearer-value" not in redacted
    assert "correlation_id=request-123" in redacted
