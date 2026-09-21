"""HSGQ GPON OLT provisioning procedures.

HSGQ uses the Huawei-like GPON profile and VLAN command family shown in the
supported G08L configuration export. It remains a separate driver so syntax
differences can be isolated without changing Huawei behavior.
"""

import re


def _send(connection, command, output):
    output.append(connection.send_command_timing(command, strip_prompt=False, strip_command=False))

def _send_input(connection, command, output, capture=True):
    response = connection.send_command_timing(command, strip_prompt=False, strip_command=False)
    if capture:
        output.append(response)

def _quote(value):
    return '"' + str(value).replace('\\', '\\\\').replace('"', '\\"') + '"'


def _run(connection, commands):
    output = []
    for command in commands:
        _send(connection, command, output)
    return '\n'.join(output)


def create_s_vlan(connection, values):
    vlan_id = int(values['vlan_id'])
    port = values.get('port', '0/3 1')
    return _run(connection, ('config', f'vlan {vlan_id} smart', f'vlan attrib {vlan_id} q-in-q', f'vlan forwarding {vlan_id} vlan-connect', f'port vlan {vlan_id} {port}', 'return', 'save'))


def create_c_vlan(connection, values):
    vlan_to = values.get('vlan_to'); vlan_type = values.get('vlan_type', 'smart')
    range_suffix = f" to {int(vlan_to)}" if vlan_to else ''
    type_suffix = '' if vlan_type == 'to' else f' {vlan_type}'
    return _run(connection, ('config', f"vlan {int(values['vlan_id'])}{range_suffix}{type_suffix}", 'return', 'save'))


def ont_line_profile_commands(values):
    profile_id = int(values['profile_id'])
    dba_id = int(values['dba_profile_id'])
    tr069_enabled = bool(values.get('tr069_management_enabled', True)); omcc_encrypt_enabled = bool(values.get('omcc_encrypt_enabled', True)); tr069_ip_index = int(values.get('tr069_ip_index', 1))
    commands = [f"ont-lineprofile gpon profile-id {profile_id} profile-name \"{values['profile_name']}\"", f'omcc encrypt {"on" if omcc_encrypt_enabled else "off"}', f'tr069-management {"enable" if tr069_enabled else "disable"}']
    if tr069_enabled: commands.append(f'tr069-management ip-index {tr069_ip_index}')
    commands.append(f'tcont 1 dba-profile-id {dba_id}')
    if values.get('internet_vlan'):
        commands.append(f"gem mapping 1 1 vlan {int(values['internet_vlan'])}")
    if values.get('tr069_vlan'):
        commands.append(f"gem mapping 1 2 vlan {int(values['tr069_vlan'])}")
    commands += ['commit', 'exit', 'save']
    return commands

def create_ont_line_profile(connection, values):
    return _run(connection, ('config', *ont_line_profile_commands(values)))

def preview_ont_line_profile(values):
    return ['config', *ont_line_profile_commands(values)]


def delete_s_vlan(connection, values):
    vlan_id = int(values['vlan_id'])
    port = values.get('port', '0/3 1')
    return _run(connection, ('config', f'undo service-port {int(values["service_port_id"])}', f'undo port vlan {vlan_id} {port}', f'undo vlan {vlan_id}', 'return', 'save'))


def delete_c_vlan(connection, values):
    vlan_to = values.get('vlan_to'); range_suffix = f" to {int(vlan_to)}" if vlan_to else ''
    return _run(connection, ('config', f"undo vlan {int(values['vlan_id'])}{range_suffix}", 'return', 'save'))

def deactivate_ont(connection, values):
    frame = int(values['frame']); slot = int(values['slot']); pon_port = int(values['pon_port']); ont_id = int(values['ont_id'])
    commands = ['config']
    commands += [f'undo service-port {int(service_port_id)}' for service_port_id in (values.get('service_port_ids') or [])]
    commands += [f'interface gpon {frame}/{slot}', f'ont delete {pon_port} {ont_id}', 'return', 'save']
    return _run(connection, commands)


def delete_ont_line_profile(connection, values):
    return _run(connection, ('config', f"undo ont-lineprofile gpon profile-id {int(values['profile_id'])}", 'return', 'save'))

def create_ont_service_profile(connection, values):
    profile_id = int(values['profile_id']); profile_name = values['profile_name']; port_count = int(values['eth_port_count']); modes = values.get('port_modes') or {}
    commands = [f'ont-srvprofile gpon profile-id {profile_id} profile-name "{profile_name}"', f'ont-port eth {port_count}']
    for port in range(1, port_count + 1):
        mode = modes.get(str(port), 'transparent')
        commands.append(f'port q-in-q eth {port} {"enable" if mode == "qinq" else "disable"}')
        if mode == 'transparent': commands.append(f'port vlan eth {port} transparent')
    return _run(connection, ('config', *commands, 'commit', 'return', 'save'))

def delete_ont_service_profile(connection, values):
    return _run(connection, ('config', f"undo ont-srvprofile gpon profile-id {int(values['profile_id'])}", 'return', 'save'))

def create_ont_wan_profile(connection, values):
    commands = [f'ont wan-profile profile-id {int(values["profile_id"])} profile-name "{values["profile_name"]}"']
    if values.get('nat_enabled'): commands.append('nat enable')
    return _run(connection, ('config', *commands, 'quit', 'return', 'save'))

def delete_ont_wan_profile(connection, values):
    return _run(connection, ('config', f"undo ont wan-profile profile-id {int(values['profile_id'])}", 'return', 'save'))

def create_tr069_server_profile(connection, values):
    command = f'ont tr069-server-profile add profile-id {int(values["profile_id"])} profile-name {_quote(values["profile_name"])} url {_quote(values["url"])} user {_quote(values["username"])} {_quote(values["password"])}'
    return _run(connection, ('config', command, 'quit', 'return', 'save'))

def delete_tr069_server_profile(connection, values):
    return _run(connection, ('config', f"undo ont tr069-server-profile profile-id {int(values['profile_id'])}", 'return', 'save'))

def create_terminal_user(connection, values):
    output = []
    commands = [
        'config', 'terminal user name', str(values['username']), str(values['password']),
        str(values['password']), str(values.get('profile_name') or 'root'),
        str(int(values.get('privilege_level', 3))), str(int(values.get('reenter_limit', 1))),
        str(values.get('appended_info') or ''), 'n', 'return', 'save',
    ]
    for index, command in enumerate(commands):
        _send_input(connection, command, output, capture=index not in (3, 4))
    return '\n'.join(output)

def replace_terminal_user(connection, values):
    output = []
    _send(connection, f'undo terminal user name {values["username"]}', output)
    created = create_terminal_user(connection, values)
    if created:
        output.append(created)
    return '\n'.join(output)

def delete_terminal_user(connection, values):
    return _run(connection, ('config', f"undo terminal user name {values['username']}", 'return', 'save'))

def update_terminal_user_policy(connection, values):
    enabled = bool(values.get('security_enabled'))
    length = int(values.get('security_length', 12))
    return _run(connection, (
        'config',
        'system modify logon password enable all' if enabled else 'system modify logon password disable all',
        'system user password security mode enhance' if enabled else 'system user password security mode normal',
        f'system user password security-length {length}', 'return', 'save',
    ))

def _parse_autofind(output):
    records = []
    for block in re.split(r'(?=Number\s*:)', output, flags=re.IGNORECASE):
        location = re.search(r'F/S/P\s*:\s*(\d+)\s*/\s*(\d+)\s*/\s*(\d+)', block, flags=re.IGNORECASE)
        serial = re.search(r'Ont\s+SN\s*:\s*([^\s\r\n]+)', block, flags=re.IGNORECASE)
        if not location or not serial:
            continue
        frame, slot, pon_port = (int(value) for value in location.groups())
        serial_number = serial.group(1).strip()
        records.append({'frame': frame, 'slot': slot, 'pon_port': pon_port, 'serial_number': serial_number, 'name': f'ONT {serial_number}'})
    return records

def discover_onts(connection, values=None):
    output = connection.send_command_timing('display ont autofind all', strip_prompt=False, strip_command=False)
    return _parse_autofind(output)


OPERATIONS = {
    'create_s_vlan': create_s_vlan,
    'create_c_vlan': create_c_vlan,
    'create_ont_line_profile': create_ont_line_profile,
    'create_ont_service_profile': create_ont_service_profile,
    'create_ont_wan_profile': create_ont_wan_profile,
    'create_tr069_server_profile': create_tr069_server_profile,
    'discover_onts': discover_onts,
    'create_terminal_user': create_terminal_user,
    'replace_terminal_user': replace_terminal_user,
    'delete_terminal_user': delete_terminal_user,
    'update_terminal_user_policy': update_terminal_user_policy,
    'delete_s_vlan': delete_s_vlan,
    'delete_c_vlan': delete_c_vlan,
    'deactivate_ont': deactivate_ont,
    'delete_ont_line_profile': delete_ont_line_profile,
    'delete_ont_service_profile': delete_ont_service_profile,
    'delete_ont_wan_profile': delete_ont_wan_profile,
    'delete_tr069_server_profile': delete_tr069_server_profile,
}

PREVIEW_OPERATIONS = {'create_ont_line_profile': preview_ont_line_profile}
