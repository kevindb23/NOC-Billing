"""Huawei MA-series provisioning procedures executed through Netmiko."""

import re

def _send(connection, command, output):
    output.append(connection.send_command_timing(command, strip_prompt=False, strip_command=False))

def _send_input(connection, command, output, capture=True):
    response = connection.send_command_timing(command, strip_prompt=False, strip_command=False)
    if capture:
        output.append(response)

def _quote(value):
    return '"' + str(value).replace('\\', '\\\\').replace('"', '\\"') + '"'

def create_s_vlan(connection, values):
    vlan_id = int(values['vlan_id']); port = values.get('port', '0/3 1')
    output = []
    for command in ('config', f'vlan {vlan_id} smart', f'vlan attrib {vlan_id} q-in-q', f'vlan forwarding {vlan_id} vlan-connect', f'port vlan {vlan_id} {port}', 'return', 'save'):
        _send(connection, command, output)
    return '\n'.join(output)

def create_c_vlan(connection, values):
    vlan_id = int(values['vlan_id']); vlan_to = values.get('vlan_to'); vlan_type = values.get('vlan_type', 'smart'); output = []
    range_suffix = f' to {int(vlan_to)}' if vlan_to else ''
    type_suffix = '' if vlan_type == 'to' else f' {vlan_type}'
    for command in ('config', f'vlan {vlan_id}{range_suffix}{type_suffix}', 'return', 'save'):
        _send(connection, command, output)
    return '\n'.join(output)

def create_tr069_vlan(connection, values):
    vlan_id = int(values['vlan_id']); vlan_to = values.get('vlan_to'); vlan_type = values.get('vlan_type', 'smart'); port = values.get('port') or f"{values['frame']}/{values['slot']} {values['port_number']}"; output = []
    range_suffix = f' to {int(vlan_to)}' if vlan_to else ''
    type_suffix = '' if vlan_type == 'to' else f' {vlan_type}'
    for command in ('config', f'vlan {vlan_id}{range_suffix}{type_suffix}', f'port vlan {vlan_id} {port}', 'return', 'save'):
        _send(connection, command, output)
    return '\n'.join(output)

def ont_line_profile_commands(values):
    profile_id = int(values['profile_id']); dba_id = int(values['dba_profile_id']); profile_name = values['profile_name']; internet_vlan = values.get('internet_vlan'); tr069_vlan = values.get('tr069_vlan')
    tr069_enabled = bool(values.get('tr069_management_enabled', True)); omcc_encrypt_enabled = bool(values.get('omcc_encrypt_enabled', True)); tr069_ip_index = int(values.get('tr069_ip_index', 1))
    commands = [f'ont-lineprofile gpon profile-id {profile_id} profile-name "{profile_name}"', f'omcc encrypt {"on" if omcc_encrypt_enabled else "off"}', f'tr069-management {"enable" if tr069_enabled else "disable"}']
    if tr069_enabled: commands.append(f'tr069-management ip-index {tr069_ip_index}')
    commands += [f'tcont 1 dba-profile-id {dba_id}', 'gem add 1 eth tcont 1 encrypt on', 'gem add 2 eth tcont 1 encrypt on']
    if internet_vlan and tr069_vlan:
        commands += [f'gem mapping 1 0 vlan {int(internet_vlan)}', f'gem mapping 2 0 vlan {int(tr069_vlan)}']
    commands += ['commit', 'quit', 'return']
    return commands

def create_ont_line_profile(connection, values):
    commands = ont_line_profile_commands(values)
    output = []; _send(connection, 'config', output)
    for command in commands: _send(connection, command, output)
    _send(connection, 'save', output)
    return '\n'.join(output)

def preview_ont_line_profile(values):
    return ['config', *ont_line_profile_commands(values), 'save']

def delete_s_vlan(connection, values):
    vlan_id = int(values['vlan_id']); port = values.get('port') or f"{values['frame']}/{values['slot']} {values['port_number']}"; output = []
    commands = [f'undo service-port {int(values["service_port_id"])}', f'undo port vlan {vlan_id} {port}', f'vlan forwarding {vlan_id} vlan-mac', f'undo vlan attrib {vlan_id} q-in-q', f'undo vlan {vlan_id}', 'return', 'save']
    for command in ('config', *commands): _send(connection, command, output)
    return '\n'.join(output)

def delete_c_vlan(connection, values):
    output = []
    vlan_to = values.get('vlan_to'); range_suffix = f' to {int(vlan_to)}' if vlan_to else ''
    for command in ('config', f'undo vlan {int(values["vlan_id"])}{range_suffix}', 'return', 'save'): _send(connection, command, output)
    return '\n'.join(output)

def delete_tr069_vlan(connection, values):
    vlan_id = int(values['vlan_id']); port = values.get('port') or f"{values['frame']}/{values['slot']} {values['port_number']}"; output = []
    for command in ('config', f'undo port vlan {vlan_id} {port}', f'undo vlan {vlan_id}', 'return', 'save'):
        _send(connection, command, output)
    return '\n'.join(output)

def delete_ont_line_profile(connection, values):
    output = []
    for command in ('config', f'undo ont-lineprofile gpon profile-id {int(values["profile_id"])}', 'return', 'save'): _send(connection, command, output)
    return '\n'.join(output)

def create_dba_profile(connection, values):
    output = []
    speed = int(values.get('bandwidth_kbps') or (int(values['bandwidth_mbps']) * 1024))
    command = f'dba-profile add profile-id {int(values["profile_id"])} profile-name "{values["profile_name"]}" type4 max {speed}'
    for item in ('config', command, 'return', 'save'): _send(connection, item, output)
    return '\n'.join(output)

def delete_dba_profile(connection, values):
    output = []
    for item in ('config', f'undo dba-profile profile-id {int(values["profile_id"])}', 'return', 'save'): _send(connection, item, output)
    return '\n'.join(output)

def activation_commands(values):
    frame = int(values['frame']); slot = int(values['slot']); pon_port = int(values['pon_port']); ont_id = int(values['ont_id'])
    serial = _quote(values['serial_number']); description = _quote(values['description'])
    location = f'{frame}/{slot}/{pon_port}'
    commands = ['config', f'interface gpon {frame}/{slot}', f'ont add {pon_port} {ont_id} sn-auth {serial} omci']
    if values.get('line_profile_id') is not None: commands[-1] += f' ont-lineprofile-id {int(values["line_profile_id"])}'
    if values.get('service_profile_id') is not None: commands[-1] += f' ont-srvprofile-id {int(values["service_profile_id"])}'
    commands[-1] += f' desc {description}'
    c_vlan = int(values['c_vlan']); commands += [
        f'ont ipconfig {pon_port} {ont_id} pppoe vlan {c_vlan} priority 0 user-account ont-input',
    ]
    if values.get('tr069_vlan') is not None:
        commands.append(f'ont ipconfig {pon_port} {ont_id} ip-index 1 dhcp vlan {int(values["tr069_vlan"])} priority 1')
    if values.get('tr069_profile_id') is not None: commands.append(f'ont tr069-server-config {pon_port} {ont_id} profile-id {int(values["tr069_profile_id"])}')
    commands.append(f'ont internet-config {pon_port} {ont_id} ip-index 0')
    wan_profile_ids = values.get('wan_profile_ids')
    if wan_profile_ids is None and values.get('wan_profile_id') is not None:
        wan_profile_ids = [values['wan_profile_id']]
    for ip_index, wan_profile_id in enumerate(wan_profile_ids or []):
        commands.append(f'ont wan-config {pon_port} {ont_id} ip-index {ip_index} profile-id {int(wan_profile_id)}')
    commands += [
        f'ont fec {pon_port} {ont_id} enable ont-type 2.5g/1.25g use-profile-config',
        f'ont port native-vlan {pon_port} {ont_id} eth 1 vlan {c_vlan} priority 0',
    ]
    if values.get('tr069_vlan') is not None:
        commands.append(f'ont port native-vlan {pon_port} {ont_id} iphost vlan {int(values["tr069_vlan"])} priority 1')
    commands += ['return', 'config']
    if values.get('service_port_id') is not None:
        commands.append(f'service-port {int(values["service_port_id"])} vlan {int(values["s_vlan"])} gpon {location} ont {ont_id} gemport 1 multi-service user-vlan {c_vlan} tag-transform translate-and-add inner-vlan {c_vlan} inner-priority 0')
    if values.get('tr069_vlan') is not None and values.get('tr069_service_port_id') is not None:
        tr069_vlan = int(values['tr069_vlan']); commands.append(f'service-port {int(values["tr069_service_port_id"])} vlan {tr069_vlan} gpon {location} ont {ont_id} gemport 2 multi-service user-vlan {tr069_vlan} tag-transform translate')
    commands += ['return', 'save']
    return commands

def activate_ont(connection, values):
    commands = activation_commands(values)
    output = []
    for command in commands: _send(connection, command, output)
    return '\n'.join(output)

def deactivate_ont(connection, values):
    frame = int(values['frame']); slot = int(values['slot']); pon_port = int(values['pon_port']); ont_id = int(values['ont_id'])
    service_port_ids = values.get('service_port_ids') or []
    commands = ['config']
    commands += [f'undo service-port {int(service_port_id)}' for service_port_id in service_port_ids]
    commands += [f'interface gpon {frame}/{slot}', f'ont delete {pon_port} {ont_id}', 'return', 'save']
    output = []
    for command in commands: _send(connection, command, output)
    return '\n'.join(output)

def create_ont_service_profile(connection, values):
    profile_id = int(values['profile_id']); profile_name = values['profile_name']; port_count = int(values['eth_port_count']); modes = values.get('port_modes') or {}
    commands = [f'ont-srvprofile gpon profile-id {profile_id} profile-name "{profile_name}"', f'ont-port eth {port_count}']
    for port in range(1, port_count + 1):
        mode = modes.get(str(port), 'transparent')
        if mode == 'qinq': commands.append(f'port q-in-q eth {port} enable')
        else: commands.extend([f'port q-in-q eth {port} disable', f'port vlan eth {port} transparent'])
    commands += ['commit', 'return', 'save']
    output = []; _send(connection, 'config', output)
    for command in commands: _send(connection, command, output)
    return '\n'.join(output)

def delete_ont_service_profile(connection, values):
    output = []
    for item in ('config', f'undo ont-srvprofile gpon profile-id {int(values["profile_id"])}', 'return', 'save'): _send(connection, item, output)
    return '\n'.join(output)

def create_ont_wan_profile(connection, values):
    commands = [f'ont wan-profile profile-id {int(values["profile_id"])} profile-name "{values["profile_name"]}"']
    if values.get('nat_enabled'): commands.append('nat enable')
    output = []; _send(connection, 'config', output)
    for command in commands + ['quit', 'return', 'save']: _send(connection, command, output)
    return '\n'.join(output)

def delete_ont_wan_profile(connection, values):
    output = []
    for item in ('config', f'undo ont wan-profile profile-id {int(values["profile_id"])}', 'return', 'save'): _send(connection, item, output)
    return '\n'.join(output)

def create_tr069_server_profile(connection, values):
    command = f'ont tr069-server-profile add profile-id {int(values["profile_id"])} profile-name {_quote(values["profile_name"])} url {_quote(values["url"])} user {_quote(values["username"])} {_quote(values["password"])}'
    output = []; _send(connection, 'config', output)
    for item in (command, 'quit', 'return', 'save'): _send(connection, item, output)
    return '\n'.join(output)

def delete_tr069_server_profile(connection, values):
    output = []
    for item in ('config', f'undo ont tr069-server-profile profile-id {int(values["profile_id"])}', 'return', 'save'): _send(connection, item, output)
    return '\n'.join(output)

def create_terminal_user(connection, values):
    output = []
    commands = [
        'config',
        'terminal user name',
        str(values['username']),
        str(values['password']),
        str(values['password']),
        str(values.get('profile_name') or 'root'),
        str(int(values.get('privilege_level', 3))),
        str(int(values.get('reenter_limit', 1))),
        str(values.get('appended_info') or ''),
        'n',
        'return',
        'save',
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
    output = []
    for command in ('config', f'undo terminal user name {values["username"]}', 'return', 'save'):
        _send(connection, command, output)
    return '\n'.join(output)

def update_terminal_user_policy(connection, values):
    enabled = bool(values.get('security_enabled'))
    length = int(values.get('security_length', 12))
    commands = ('config',
        'system modify logon password enable all' if enabled else 'system modify logon password disable all',
        'system user password security mode enhance' if enabled else 'system user password security mode normal',
        f'system user password security-length {length}', 'return', 'save')
    output = []
    for command in commands:
        _send(connection, command, output)
    return '\n'.join(output)

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

OPERATIONS = {'create_s_vlan': create_s_vlan, 'create_c_vlan': create_c_vlan, 'create_tr069_vlan': create_tr069_vlan, 'create_ont_line_profile': create_ont_line_profile, 'create_ont_service_profile': create_ont_service_profile, 'create_ont_wan_profile': create_ont_wan_profile, 'create_tr069_server_profile': create_tr069_server_profile, 'create_dba_profile': create_dba_profile, 'activate_ont': activate_ont, 'deactivate_ont': deactivate_ont, 'create_terminal_user': create_terminal_user, 'replace_terminal_user': replace_terminal_user, 'update_terminal_user_policy': update_terminal_user_policy, 'discover_onts': discover_onts, 'delete_s_vlan': delete_s_vlan, 'delete_c_vlan': delete_c_vlan, 'delete_tr069_vlan': delete_tr069_vlan, 'delete_ont_line_profile': delete_ont_line_profile, 'delete_ont_service_profile': delete_ont_service_profile, 'delete_ont_wan_profile': delete_ont_wan_profile, 'delete_tr069_server_profile': delete_tr069_server_profile, 'delete_dba_profile': delete_dba_profile, 'delete_terminal_user': delete_terminal_user}

PREVIEW_OPERATIONS = {'activate_ont': activation_commands, 'create_ont_line_profile': preview_ont_line_profile}
