#!/usr/bin/env python3
"""Parse and update the approved subset of an Accel-PPP configuration."""
import ipaddress
import re
from typing import Any

SECTION_KEYS = {
    "pppoe": {"bras_name": "ac-name"},
    "ip-pool": {"gateway_address": "gw-ip-address"},
    "radius": {"nas_ip": "nas-ip-address", "nas_identifier": "nas-identifier", "radius_gateway_address": "gw-ip-address"},
    "dns": {"primary_dns": "dns1", "secondary_dns": "dns2"},
}


def _present(value: Any) -> bool:
    return value is not None and str(value).strip() != ""


def _value(lines: list[str], section: str, key: str, default: str = "") -> str:
    current = None
    for line in lines:
        stripped = line.strip()
        if stripped.startswith("[") and stripped.endswith("]"):
            current = stripped[1:-1].lower()
        if current == section and stripped.startswith(f"{key}="):
            return stripped.split("=", 1)[1].strip()
    return default


def parse_config(text: str) -> dict[str, Any]:
    lines = text.splitlines()
    pool_line = _value(lines, "ip-pool", "100.64.0.2-100.64.0.254,pool1")
    pool_name = ""
    pool_start = ""
    pool_end = ""
    current = None
    for line in lines:
        stripped = line.strip()
        if stripped.startswith("[") and stripped.endswith("]"):
            current = stripped[1:-1].lower()
        if current == "ip-pool" and re.match(r"^[^=\s]+-[^=\s]+,[^=\s]+$", stripped):
            pool_line = stripped
            break
    match = re.match(r"^([^,-]+)-([^,]+),(.+)$", pool_line)
    if match:
        pool_start, pool_end, pool_name = match.groups()

    server = _value(lines, "radius", "server")
    server_parts = [part.strip() for part in server.split(",")]
    radius_server = server_parts[0] if server_parts else ""
    radius_secret = server_parts[1] if len(server_parts) > 1 else ""
    auth_port = "1812"
    acct_port = "1813"
    for part in server_parts[2:]:
        if part.startswith("auth-port="):
            auth_port = part.split("=", 1)[1]
        if part.startswith("acct-port="):
            acct_port = part.split("=", 1)[1]

    dae = _value(lines, "radius", "dae-server")
    dae_parts = [part.strip() for part in dae.split(",")]
    dae_server = dae_parts[0] if dae_parts else ""
    dae_secret = dae_parts[1] if len(dae_parts) > 1 else ""
    dae_host, dae_port = (dae_server.rsplit(":", 1) if ":" in dae_server else (dae_server, "3799"))

    return {
        "name": "BNG",
        "bras_name": _value(lines, "pppoe", "ac-name"),
        "gateway_address": _value(lines, "ip-pool", "gw-ip-address"),
        "pool_name": pool_name,
        "pool_start": pool_start,
        "pool_end": pool_end,
        "nas_ip": _value(lines, "radius", "nas-ip-address"),
        "nas_identifier": _value(lines, "radius", "nas-identifier"),
        "radius_gateway_address": _value(lines, "radius", "gw-ip-address"),
        "radius_server": radius_server,
        "radius_secret": radius_secret,
        "auth_port": auth_port,
        "acct_port": acct_port,
        "dae_server": dae_host,
        "dae_port": dae_port,
        "dae_secret": dae_secret,
        "primary_dns": _value(lines, "dns", "dns1"),
        "secondary_dns": _value(lines, "dns", "dns2"),
    }


def validate_values(values: dict[str, Any], partial: bool = False) -> dict[str, str]:
    required = ["bras_name", "gateway_address", "pool_name", "pool_start", "pool_end", "nas_ip", "nas_identifier", "radius_gateway_address", "radius_server", "radius_secret", "dae_server", "dae_secret", "primary_dns", "secondary_dns"]
    errors: dict[str, str] = {}
    for key in required:
        if not partial and not _present(values.get(key)):
            errors[key] = "This field is required."
    for key in ["gateway_address", "nas_ip", "radius_gateway_address", "primary_dns", "secondary_dns", "pool_start", "pool_end"]:
        if _present(values.get(key)):
            try:
                ipaddress.ip_address(str(values[key]).strip())
            except ValueError:
                errors[key] = "Enter a valid IP address."
    try:
        if ipaddress.ip_address(str(values.get("pool_start"))) > ipaddress.ip_address(str(values.get("pool_end"))):
            errors["pool_end"] = "The pool end address must be after the start address."
    except ValueError:
        pass
    for key in ["auth_port", "acct_port", "dae_port"]:
        if partial and not _present(values.get(key)):
            continue
        try:
            value = int(values.get(key, ""))
            if not 1 <= value <= 65535:
                raise ValueError
        except (TypeError, ValueError):
            errors[key] = "Enter a port between 1 and 65535."
    if errors:
        raise ValueError("; ".join(f"{key}: {message}" for key, message in errors.items()))
    return {key: (str(value).strip() if value is not None else None) for key, value in values.items()}


def _replace(lines: list[str], section: str, key: str, value: str) -> None:
    current = None
    for index, line in enumerate(lines):
        stripped = line.strip()
        if stripped.startswith("[") and stripped.endswith("]"):
            current = stripped[1:-1].lower()
        if current == section and stripped.startswith(f"{key}="):
            newline = "\n" if line.endswith("\n") else ""
            lines[index] = f"{key}={value}{newline}"
            return
    for index, line in enumerate(lines):
        if line.strip().lower() == f"[{section}]":
            lines.insert(index + 1, f"{key}={value}\n")
            return
    if lines and lines[-1].strip():
        lines.append("\n")
    lines.extend([f"[{section}]\n", f"{key}={value}\n"])


def _replace_pool(lines: list[str], pool_name: str, value: str) -> None:
    current = None
    for index, line in enumerate(lines):
        stripped = line.strip()
        if stripped.startswith("[") and stripped.endswith("]"):
            current = stripped[1:-1].lower()
        if current == "ip-pool" and re.match(r"^[^=\s]+-[^=\s]+,[^=\s]+$", stripped):
            newline = "\n" if line.endswith("\n") else ""
            lines[index] = f"{value},{pool_name}{newline}"
            return
    for index, line in enumerate(lines):
        if line.strip().lower() == "[ip-pool]":
            lines.insert(index + 1, f"{value},{pool_name}\n")
            return
    raise ValueError("Missing [ip-pool] section.")


def render_config(original_text: str, values: dict[str, Any], partial: bool = False) -> str:
    clean = validate_values(values, partial=partial)
    newline = "\n" if original_text.endswith("\n") else ""
    lines = original_text.splitlines(True)
    replacements = [("pppoe", "ac-name", "bras_name"), ("ip-pool", "gw-ip-address", "gateway_address"), ("radius", "nas-ip-address", "nas_ip"), ("radius", "nas-identifier", "nas_identifier"), ("radius", "gw-ip-address", "radius_gateway_address"), ("dns", "dns1", "primary_dns"), ("dns", "dns2", "secondary_dns")]
    for section, key, value_key in replacements:
        if clean.get(value_key):
            _replace(lines, section, key, clean[value_key])
    if all(clean.get(key) for key in ["pool_name", "pool_start", "pool_end"]):
        _replace_pool(lines, clean["pool_name"], f"{clean['pool_start']}-{clean['pool_end']}")
    if all(clean.get(key) for key in ["radius_server", "radius_secret", "auth_port", "acct_port"]):
        _replace(lines, "radius", "server", f"{clean['radius_server']},{clean['radius_secret']},auth-port={clean['auth_port']},acct-port={clean['acct_port']}")
    if all(clean.get(key) for key in ["dae_server", "dae_port", "dae_secret"]):
        _replace(lines, "radius", "dae-server", f"{clean['dae_server']}:{clean['dae_port']},{clean['dae_secret']}")
    result = "".join(lines)
    return result if result.endswith("\n") or not newline else result + "\n"
