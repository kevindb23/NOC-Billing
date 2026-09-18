"""Linux BNG driver for Accel-PPP configuration operations."""
import base64
import time

from accel_ppp_config import parse_config, render_config

CONFIG_PATH = "/etc/accel-ppp.conf"
IPTABLES_PATH = "/etc/iptables/rules.v4"
DEVICE_TYPE = "linux"


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
