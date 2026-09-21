"""Linux BNG driver for Accel-PPP configuration operations."""
import base64
import re
import shlex
import time

from accel_ppp_config import parse_config, render_config

CONFIG_PATH = "/etc/accel-ppp.conf"
IPTABLES_PATH = "/etc/iptables/rules.v4"
DEVICE_TYPE = "linux"
INTERFACE_PATTERN = re.compile(r"^[A-Za-z][A-Za-z0-9_.-]{0,63}$")


def _interface_name(value):
    name = str(value or "").strip()
    if not INTERFACE_PATTERN.fullmatch(name):
        raise RuntimeError("The BNG interface name contains unsupported characters.")
    return name


def _vlan_id(value):
    try:
        vlan_id = int(value)
    except (TypeError, ValueError) as exc:
        raise RuntimeError("The VLAN ID must be an integer from 1 to 4094.") from exc
    if vlan_id < 1 or vlan_id > 4094:
        raise RuntimeError("The VLAN ID must be an integer from 1 to 4094.")
    return vlan_id


def _interface_values(values, require_vlan=True):
    interfaces = values.get("interfaces", [])
    if not isinstance(interfaces, list) or not interfaces:
        raise RuntimeError("At least one BNG VLAN interface is required.")
    result = []
    for item in interfaces:
        if not isinstance(item, dict):
            raise RuntimeError("The BNG VLAN interface payload is invalid.")
        name = _interface_name(item.get("name"))
        parent = _interface_name(item.get("parent")) if item.get("parent") else None
        vlan_id = _vlan_id(item.get("vlan_id")) if require_vlan else item.get("vlan_id")
        result.append({"name": name, "parent": parent, "vlan_id": vlan_id})
    return result


def _netplan_key(name):
    return name.replace(".", r"\.")


def _interface_exists(connection, interface):
    output = connection.send_command(f"ip -o link show {shlex.quote(interface)}", read_timeout=20)
    return bool(re.search(rf"(?m)^\s*\d+:\s*{re.escape(interface)}(?:@|:|\s)", output or "")), output or ""


def _netplan_interface_exists(connection, interface):
    output = connection.send_command("netplan get network.vlans", read_timeout=20)
    return bool(re.search(rf"(?m)^\s*{re.escape(interface)}:\s*$", output or "")), output or ""


def ensure_vlan_interfaces(connection, values):
    interfaces = sorted(_interface_values(values), key=lambda item: item["name"].count("."))
    commands = []
    for item in interfaces:
        parent = item["parent"] or str(values.get("parent_interface", "")).strip()
        parent = _interface_name(parent)
        name = shlex.quote(item["name"])
        parent_arg = shlex.quote(parent)
        parent_netplan = ""
        if "." not in parent:
            parent_key = shlex.quote(f"network.ethernets.{_netplan_key(parent)}.optional=true")
            parent_file = shlex.quote("/etc/netplan/ispbox-parent.yaml")
            parent_netplan = (
                "install -d -m 755 /etc/netplan && "
                f"printf '%s\\n' 'network:' '  version: 2' '  ethernets:' '    {parent}:' '      optional: true' > {parent_file} && "
                f"netplan set --origin-hint ispbox-parent {parent_key} && "
            )
        netplan = shlex.quote(f"network.vlans.{_netplan_key(item['name'])}={{id: {item['vlan_id']}, link: {parent}}}")
        command = (
            f"{parent_netplan}netplan set --origin-hint ispbox-network {netplan} && "
            f"netplan generate && "
            f"(ip link show {name} || ip link add link {parent_arg} name {name} type vlan id {item['vlan_id']}) && "
            f"ip link set {name} up"
        )
        command_output = connection.send_command(command, read_timeout=20)
        exists, verification = _interface_exists(connection, item["name"])
        persisted, netplan_state = _netplan_interface_exists(connection, item["name"])
        if not exists or not persisted:
            command_detail = command_output.strip()[-400:] if command_output else "no command response"
            verification_detail = verification.strip()[-200:] if verification else "no verification response"
            netplan_detail = netplan_state.strip()[-300:] if netplan_state else "no Netplan response"
            message = "persist" if exists and not persisted else "create"
            raise RuntimeError(f"Remote BNG did not {message} VLAN interface {item['name']}. Command response: {command_detail}; verification: {verification_detail}; Netplan: {netplan_detail}")
        commands.append(command)
    return {"interfaces": [item["name"] for item in interfaces], "commands": commands, "verified": True}


def remove_vlan_interfaces(connection, values):
    interfaces = sorted(_interface_values(values, require_vlan=False), key=lambda item: item["name"].count("."), reverse=True)
    commands = []
    for item in interfaces:
        name = shlex.quote(item["name"])
        netplan = shlex.quote(f"network.vlans.{_netplan_key(item['name'])}=null")
        command = (
            f"netplan set --origin-hint ispbox-network {netplan} && "
            f"netplan generate && "
            f"(ip link delete {name} 2>/dev/null || true)"
        )
        connection.send_command(command, read_timeout=20)
        exists, verification = _interface_exists(connection, item["name"])
        if exists:
            detail = verification.strip()[-400:] or "no response"
            raise RuntimeError(f"Remote BNG did not remove VLAN interface {item['name']}. Remote response: {detail}")
        commands.append(command)
    return {"interfaces": [item["name"] for item in interfaces], "commands": commands, "verified": True}


def _pppoe_interface_names(text):
    names = []
    section = None
    for line in text.splitlines():
        stripped = line.strip()
        if stripped.startswith("[") and stripped.endswith("]"):
            section = stripped[1:-1].lower()
        elif section == "pppoe" and stripped.startswith("interface="):
            names.append(stripped.split("=", 1)[1].split(",", 1)[0].strip())
    return names


def _render_pppoe_interfaces(text, interfaces):
    lines = text.splitlines(True)
    existing = _pppoe_interface_names(text)
    added = [interface for interface in interfaces if interface not in existing]
    if not added:
        return text, []

    section_start = None
    section_end = len(lines)
    for index, line in enumerate(lines):
        stripped = line.strip().lower()
        if stripped == "[pppoe]":
            section_start = index
            continue
        if section_start is not None and stripped.startswith("[") and stripped.endswith("]"):
            section_end = index
            break
    if section_start is None:
        raise RuntimeError("The Accel-PPP configuration has no [pppoe] section.")

    newline = "\n"
    insertion = [f"interface={interface}{newline}" for interface in added]
    lines[section_end:section_end] = insertion
    rendered = "".join(lines)
    if not rendered.endswith("\n"):
        rendered += "\n"
    return rendered, added


def _write_accel_config(connection, rendered):
    encoded = base64.b64encode(rendered.encode()).decode()
    stamp = str(int(time.time()))
    backup = f"{CONFIG_PATH}.codex.{stamp}.bak"
    temp = f"{CONFIG_PATH}.codex.{stamp}.tmp"
    command = (
        f"sudo cp -- {CONFIG_PATH} {backup} && "
        f"printf '%s' '{encoded}' | base64 -d | sudo tee {temp} >/dev/null && "
        f"sudo test -s {temp} && sudo mv -- {temp} {CONFIG_PATH} && "
        "sudo systemctl restart accel-ppp.service && "
        "sudo systemctl is-active --quiet accel-ppp.service"
    )
    output = connection.send_command(command, read_timeout=30)
    return backup, output


def ensure_pppoe_interfaces(connection, values):
    interfaces = [_interface_name(interface) for interface in values.get("interfaces", [])]
    if not interfaces:
        return {"interfaces": [], "added": [], "verified": True}
    original = _read(connection)
    rendered, added = _render_pppoe_interfaces(original, interfaces)
    if not added:
        return {"interfaces": interfaces, "added": [], "verified": True, "path": CONFIG_PATH}
    backup, output = _write_accel_config(connection, rendered)
    return {"interfaces": interfaces, "added": added, "verified": True, "path": CONFIG_PATH, "backup": backup, "output": output}


def remove_pppoe_interfaces(connection, values):
    interfaces = [_interface_name(interface) for interface in values.get("interfaces", [])]
    if not interfaces:
        return {"interfaces": [], "removed": [], "verified": True}
    original = _read(connection)
    targets = set(interfaces)
    lines = original.splitlines(True)
    rendered_lines = []
    section = None
    removed = []
    for line in lines:
        stripped = line.strip()
        if stripped.startswith("[") and stripped.endswith("]"):
            section = stripped[1:-1].lower()
        if section == "pppoe" and stripped.startswith("interface="):
            name = stripped.split("=", 1)[1].split(",", 1)[0].strip()
            if name in targets:
                removed.append(name)
                continue
        rendered_lines.append(line)
    if not removed:
        return {"interfaces": interfaces, "removed": [], "verified": True, "path": CONFIG_PATH}
    rendered = "".join(rendered_lines)
    if not rendered.endswith("\n"):
        rendered += "\n"
    backup, output = _write_accel_config(connection, rendered)
    return {"interfaces": interfaces, "removed": removed, "verified": True, "path": CONFIG_PATH, "backup": backup, "output": output}


def _read(connection) -> str:
    output = connection.send_command(f"cat {CONFIG_PATH}", read_timeout=15)
    if not output.strip():
        raise RuntimeError("The remote Accel-PPP configuration file is empty or unreadable.")
    return output


def read_accel_ppp_config(connection, values=None):
    text = _read(connection)
    return {"content": text, "values": parse_config(text), "path": CONFIG_PATH}


def preview_accel_ppp_config(connection, values):
    original = _read(connection)
    rendered = render_config(original, values, partial=True)
    return {"content": rendered, "values": parse_config(rendered), "path": CONFIG_PATH}


def save_accel_ppp_config(connection, values):
    original = _read(connection)
    existing = parse_config(original)
    merged = {**existing, **{key: value for key, value in values.items() if value is not None and str(value).strip()}}
    rendered = render_config(original, merged)
    encoded = base64.b64encode(rendered.encode()).decode()
    stamp = str(int(time.time()))
    backup = f"{CONFIG_PATH}.codex.{stamp}.bak"
    temp = f"{CONFIG_PATH}.codex.{stamp}.tmp"
    command = (
        f"cp -- {CONFIG_PATH} {backup} && "
        f"printf '%s' '{encoded}' | base64 -d > {temp} && "
        f"test -s {temp} && "
        f"mv -- {temp} {CONFIG_PATH}"
    )
    output = connection.send_command(command, read_timeout=15)
    return {"content": rendered, "values": parse_config(rendered), "path": CONFIG_PATH, "backup": backup, "output": output}


def _read_iptables(connection) -> str:
    output = connection.send_command(f"sudo cat {IPTABLES_PATH}", read_timeout=15)
    if not output.strip():
        raise RuntimeError("The remote iptables rules file is empty or unreadable.")
    return output


def _iptables_rule(values):
    kind = values.get("kind")
    if kind == "forwarding":
        customer = str(values.get("customer_interface", "")).strip()
        internet = str(values.get("internet_interface", "")).strip()
        if not customer or not internet:
            return []
        return [
            ("filter", f"-A FORWARD -i {customer} -o {internet} -j ACCEPT"),
            ("filter", f"-A FORWARD -i {internet} -o {customer} -j ACCEPT"),
        ]
    network = str(values.get("subscriber_network", "")).strip()
    mode = values.get("public_ip_mode")
    start = str(values.get("public_ip_start", "")).strip()
    end = str(values.get("public_ip_end", "")).strip()
    bypass = str(values.get("local_bypass_network", "")).strip()
    rules = []
    if network and bypass:
        rules.append(("nat", f"-A POSTROUTING -s {network} -d {bypass} -j ACCEPT"))
    if network and mode == "single" and start:
        rules.append(("nat", f"-A POSTROUTING -s {network} -j SNAT --to-source {start}"))
    if network and mode == "range" and start and end:
        rules.append(("nat", f"-A POSTROUTING -s {network} -o {values.get('egress_interface', '')} -j SNAT --to-source {start}-{end}"))
    if network and mode == "masquerade" and values.get("egress_interface"):
        rules.append(("nat", f"-A POSTROUTING -s {network} -o {values['egress_interface']} -j MASQUERADE"))
    return rules


def _render_iptables(original, values):
    lines = original.splitlines()
    for table, rule in _iptables_rule(values):
        if any(line.strip() == rule for line in lines):
            continue
        for index, line in enumerate(lines):
            if line.strip() == "COMMIT" and index and any(lines[j].strip() == f"*{table}" for j in range(index)):
                lines.insert(index, rule)
                break
    return "\n".join(lines) + "\n"


def preview_iptables(connection, values):
    original = _read_iptables(connection)
    return {"content": _render_iptables(original, values), "path": IPTABLES_PATH}


def save_iptables(connection, values):
    original = _read_iptables(connection)
    rendered = _render_iptables(original, values)
    encoded = base64.b64encode(rendered.encode()).decode()
    stamp = str(int(time.time()))
    backup = f"{IPTABLES_PATH}.codex.{stamp}.bak"
    temp = f"{IPTABLES_PATH}.codex.{stamp}.tmp"
    command = f"sudo cp -- {IPTABLES_PATH} {backup} && printf '%s' '{encoded}' | base64 -d | sudo tee {temp} >/dev/null && sudo test -s {temp} && sudo mv -- {temp} {IPTABLES_PATH} && sudo iptables-restore < {IPTABLES_PATH}"
    output = connection.send_command(command, read_timeout=20)
    return {"content": rendered, "path": IPTABLES_PATH, "backup": backup, "output": output}
