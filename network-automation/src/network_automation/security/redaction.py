"""Centralized recursive redaction for logs, results, and exception messages."""

from __future__ import annotations

import re
from collections.abc import Mapping
from typing import Any, Iterable


REDACTED = "[REDACTED]"

_SENSITIVE_KEY_PARTS = frozenset(
    {
        "accesskey",
        "apikey",
        "authorization",
        "command",
        "certificate",
        "clientsecret",
        "community",
        "cookie",
        "credential",
        "password",
        "passphrase",
        "privatekey",
        "secret",
        "token",
    }
)

_KEY_VALUE_PATTERN = re.compile(
    r"(?P<key>access[ _-]?key|api[ _-]?key|authorization|client[ _-]?secret|"
    r"command(?:s)?|community|cookie|credential(?:s)?|password|passphrase|"
    r"private[ _-]?key|secret|token)"
    r"(?P<separator>\s*(?:=|:)\s*)"
    r"(?P<value>(?:Bearer\s+)?\"[^\"]*\"|(?:Bearer\s+)?'[^']*'|(?:Bearer\s+)?[^\s,;]+)",
    re.IGNORECASE,
)
_BEARER_PATTERN = re.compile(r"\bBearer\s+[^\s,;]+", re.IGNORECASE)
_PEM_PATTERN = re.compile(
    r"-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----.+?-----END [A-Z0-9 ]*PRIVATE KEY-----",
    re.IGNORECASE | re.DOTALL,
)


def _normalized_key(key: object) -> str:
    return re.sub(r"[^a-z0-9]", "", str(key).lower())


def _is_sensitive_key(key: object) -> bool:
    normalized = _normalized_key(key)
    return any(part in normalized for part in _SENSITIVE_KEY_PARTS)


def _is_secret_value(value: Any) -> bool:
    return callable(getattr(value, "get_secret_value", None))


def _find_sensitive_content(value: Any) -> str | None:
    if isinstance(value, Mapping):
        for key, item in value.items():
            if _is_sensitive_key(key):
                return "key"
            found = _find_sensitive_content(item)
            if found is not None:
                return found
    elif isinstance(value, (list, tuple, set, frozenset)):
        for item in value:
            found = _find_sensitive_content(item)
            if found is not None:
                return found
    elif _is_secret_value(value):
        return "value"
    elif isinstance(value, bytes):
        return _find_sensitive_content(value.decode(errors="replace"))
    elif isinstance(value, str) and (
        _PEM_PATTERN.search(value)
        or _KEY_VALUE_PATTERN.search(value)
        or _BEARER_PATTERN.search(value)
    ):
        return "value"
    return None


def reject_sensitive_keys(value: Any) -> Any:
    """Reject credential or command-shaped keys and values at request boundaries."""

    sensitive_content = _find_sensitive_content(value)
    if sensitive_content == "key":
        raise ValueError("Sensitive key is not allowed")
    if sensitive_content == "value":
        raise ValueError("Credential-like value is not allowed")
    return value


def redact(value: Any) -> Any:
    """Return a recursively redacted copy while preserving safe metadata."""

    if isinstance(value, Mapping):
        return {
            key: REDACTED if _is_sensitive_key(key) else redact(item)
            for key, item in value.items()
        }
    if isinstance(value, list):
        return [redact(item) for item in value]
    if isinstance(value, tuple):
        return tuple(redact(item) for item in value)
    if isinstance(value, set):
        return {redact(item) for item in value}
    return value


def redact_exception_message(
    message: object,
    secrets: Iterable[object] | None = None,
) -> str:
    """Redact credential-shaped key/value pairs and explicitly supplied secrets."""

    redacted = str(message)
    explicit_secrets = sorted(
        {
            str(secret)
            for secret in (secrets or ())
            if secret is not None and str(secret)
        },
        key=len,
        reverse=True,
    )
    for secret in explicit_secrets:
        redacted = redacted.replace(secret, REDACTED)
    redacted = _PEM_PATTERN.sub(REDACTED, redacted)
    redacted = _KEY_VALUE_PATTERN.sub(
        lambda match: f"{match.group('key')}{match.group('separator')}{REDACTED}",
        redacted,
    )
    return _BEARER_PATTERN.sub(f"Bearer {REDACTED}", redacted)


def redact_exception(
    exception: BaseException,
    secrets: Iterable[object] | None = None,
) -> str:
    """Return a safe exception message without exposing credential values."""

    return redact_exception_message(exception, secrets=secrets)
