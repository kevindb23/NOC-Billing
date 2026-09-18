#!/usr/bin/env python3
"""Small JSON bridge for generic router operations through Netmiko."""

import json
import sys
from typing import Any

from netmiko import ConnectHandler
from netmiko.exceptions import NetmikoAuthenticationException, NetmikoTimeoutException
from paramiko.ssh_exception import SSHException
from huawei_olt_driver import OPERATIONS as HUAWEI_OPERATIONS


def main() -> int:
    try:
        payload: dict[str, Any] = json.load(sys.stdin)
        device = payload["device"]
        operation = payload.get("operation", "test_connection")

        connection = ConnectHandler(**device)
        try:
            result: dict[str, Any] = {
                "ok": True,
                "message": "SSH connection and authentication succeeded through Netmiko.",
            }
            if operation == "command":
                command = payload.get("command")
                if not command:
                    raise ValueError("No command was supplied for this router operation.")
                result["output"] = connection.send_command(command, read_timeout=device.get("timeout", 8))
            if operation == "config":
                commands = payload.get("commands")
                if not commands:
                    raise ValueError("No configuration commands were supplied.")
                result["output"] = connection.send_config_set(commands, read_timeout=device.get("timeout", 15))
            if operation == "provision":
                provisioner = HUAWEI_OPERATIONS.get(payload.get("provisioning")) if device.get("device_type") == "huawei_olt_ssh" else None
                if not provisioner:
                    raise ValueError("The selected OLT provisioning operation is not supported by this driver.")
                result["output"] = provisioner(connection, payload.get("values", {}))
            return write_result(result)
        finally:
            connection.disconnect()
    except NetmikoAuthenticationException:
        return write_result({"ok": False, "message": "SSH authentication failed. Verify the username and password."}, 1)
    except NetmikoTimeoutException:
        return write_result({"ok": False, "message": "SSH connection timed out. Verify the endpoint and that SSH is reachable."}, 1)
    except (SSHException, OSError):
        return write_result({"ok": False, "message": "SSH connection failed. Verify the endpoint and that SSH is reachable."}, 1)
    except (KeyError, TypeError, ValueError) as exception:
        return write_result({"ok": False, "message": str(exception)}, 1)
    except Exception:
        return write_result({"ok": False, "message": "The Netmiko router operation failed."}, 1)


def write_result(result: dict[str, Any], exit_code: int = 0) -> int:
    sys.stdout.write(json.dumps(result))
    sys.stdout.flush()
    return exit_code


if __name__ == "__main__":
    raise SystemExit(main())
