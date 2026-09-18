"""Huawei MA-series provisioning procedures executed through Netmiko."""

def _send(connection, command, output):
    output.append(connection.send_command_timing(command, strip_prompt=False, strip_command=False))

def create_s_vlan(connection, values):
    vlan_id = int(values['vlan_id']); port = values.get('port', '0/3 1')
    output = []
    for command in ('config', f'vlan {vlan_id} smart', f'vlan attrib {vlan_id} q-in-q', f'vlan forwarding {vlan_id} vlan-connect', f'port vlan {vlan_id} {port}', 'return', 'save'):
        _send(connection, command, output)
    return '\n'.join(output)

def create_c_vlan(connection, values):
    vlan_id = int(values['vlan_id']); output = []
    for command in ('config', f'vlan {vlan_id} smart', 'return', 'save'):
        _send(connection, command, output)
    return '\n'.join(output)

def create_tr069_vlan(connection, values):
    vlan_id = int(values['vlan_id']); port = values.get('port') or f"{values['frame']}/{values['slot']} {values['port_number']}"; output = []
    for command in ('config', f'vlan {vlan_id} smart', f'port vlan {vlan_id} {port}', 'return', 'save'):
        _send(connection, command, output)
    return '\n'.join(output)

def create_ont_line_profile(connection, values):
    profile_id = int(values['profile_id']); dba_id = int(values['dba_profile_id']); profile_name = values['profile_name']; internet_vlan = values.get('internet_vlan'); tr069_vlan = values.get('tr069_vlan')
    commands = [f'ont-lineprofile gpon profile-id {profile_id} profile-name "{profile_name}"', 'omcc encrypt on', 'tr069-management enable', 'tr069-management ip-index 1', f'tcont 1 dba-profile-id {dba_id}', 'gem add 1 eth tcont 1 encrypt on', 'gem add 2 eth tcont 1 encrypt on']
    if internet_vlan and tr069_vlan:
        commands += [f'gem mapping 1 0 vlan {int(internet_vlan)}', f'gem mapping 2 0 vlan {int(tr069_vlan)}']
    commands += ['commit', 'quit', 'return']
    output = []; _send(connection, 'config', output)
    for command in commands: _send(connection, command, output)
    _send(connection, 'save', output)
    return '\n'.join(output)

def delete_s_vlan(connection, values):
    vlan_id = int(values['vlan_id']); port = values.get('port') or f"{values['frame']}/{values['slot']} {values['port_number']}"; output = []
    commands = [f'undo service-port {int(values["service_port_id"])}', f'undo port vlan {vlan_id} {port}', f'vlan forwarding {vlan_id} vlan-mac', f'undo vlan attrib {vlan_id} q-in-q', f'undo vlan {vlan_id}', 'return', 'save']
    for command in ('config', *commands): _send(connection, command, output)
    return '\n'.join(output)

def delete_c_vlan(connection, values):
    output = []
    for command in ('config', f'undo vlan {int(values["vlan_id"])}', 'return', 'save'): _send(connection, command, output)
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
    speed = int(values['bandwidth_mbps']) * 1024
    command = f'dba-profile add profile-id {int(values["profile_id"])} profile-name "{values["profile_name"]}" type4 max {speed}'
    for item in ('config', command, 'return', 'save'): _send(connection, item, output)
    return '\n'.join(output)

def delete_dba_profile(connection, values):
    output = []
    for item in ('config', f'undo dba-profile profile-id {int(values["profile_id"])}', 'return', 'save'): _send(connection, item, output)
    return '\n'.join(output)

OPERATIONS = {'create_s_vlan': create_s_vlan, 'create_c_vlan': create_c_vlan, 'create_tr069_vlan': create_tr069_vlan, 'create_ont_line_profile': create_ont_line_profile, 'create_dba_profile': create_dba_profile, 'delete_s_vlan': delete_s_vlan, 'delete_c_vlan': delete_c_vlan, 'delete_tr069_vlan': delete_tr069_vlan, 'delete_ont_line_profile': delete_ont_line_profile, 'delete_dba_profile': delete_dba_profile}
