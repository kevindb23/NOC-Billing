from huawei_olt_driver import OPERATIONS, PREVIEW_OPERATIONS


class FakeConnection:
    def __init__(self):
        self.commands = []

    def send_command_timing(self, command, **kwargs):
        self.commands.append(command)
        return command


def test_huawei_driver_creates_terminal_user_with_interactive_ma5800_flow():
    connection = FakeConnection()
    OPERATIONS['create_terminal_user'](connection, {
        'username': 'testuser',
        'password': 'TestUser#2026!',
        'profile_name': 'root',
        'privilege_level': 3,
        'reenter_limit': 1,
        'appended_info': 'Test account',
    })

    assert connection.commands == [
        'config', 'terminal user name', 'testuser', 'TestUser#2026!', 'TestUser#2026!',
        'root', '3', '1', 'Test account', 'n', 'return', 'save',
    ]


def test_huawei_driver_applies_terminal_user_password_policy():
    connection = FakeConnection()
    OPERATIONS['update_terminal_user_policy'](connection, {'security_enabled': True, 'security_length': 12})

    assert connection.commands == [
        'config',
        'system modify logon password enable all',
        'system user password security mode enhance',
        'system user password security-length 12',
        'return',
        'save',
    ]


def test_huawei_driver_previews_qinq_activation_commands_without_connection():
    commands = PREVIEW_OPERATIONS['activate_ont']({
        'frame': 0,
        'slot': 2,
        'pon_port': 0,
        'ont_id': 0,
        'serial_number': '48575443FAB6E248',
        'description': 'SUB_3001_50',
        'line_profile_id': 17,
        'service_profile_id': 15,
        'tr069_profile_id': 15,
        'wan_profile_ids': [15, 16],
        'c_vlan': 3001,
        's_vlan': 50,
        'tr069_vlan': 25,
        'service_port_id': 10001,
        'tr069_service_port_id': 20001,
    })

    assert commands == [
        'config',
        'interface gpon 0/2',
        'ont add 0 0 sn-auth "48575443FAB6E248" omci ont-lineprofile-id 17 ont-srvprofile-id 15 desc "SUB_3001_50"',
        'ont ipconfig 0 0 pppoe vlan 3001 priority 0 user-account ont-input',
        'ont ipconfig 0 0 ip-index 1 dhcp vlan 25 priority 1',
        'ont tr069-server-config 0 0 profile-id 15',
        'ont internet-config 0 0 ip-index 0',
        'ont wan-config 0 0 ip-index 0 profile-id 15',
        'ont wan-config 0 0 ip-index 1 profile-id 16',
        'ont fec 0 0 enable ont-type 2.5g/1.25g use-profile-config',
        'ont port native-vlan 0 0 eth 1 vlan 3001 priority 0',
        'ont port native-vlan 0 0 iphost vlan 25 priority 1',
        'return',
        'config',
        'service-port 10001 vlan 50 gpon 0/2/0 ont 0 gemport 1 multi-service user-vlan 3001 tag-transform translate-and-add inner-vlan 3001 inner-priority 0',
        'service-port 20001 vlan 25 gpon 0/2/0 ont 0 gemport 2 multi-service user-vlan 25 tag-transform translate',
        'return',
        'save',
    ]


def test_huawei_driver_removes_service_ports_before_deleting_the_ont():
    connection = FakeConnection()
    OPERATIONS['deactivate_ont'](connection, {
        'frame': 0,
        'slot': 2,
        'pon_port': 0,
        'ont_id': 0,
        'service_port_ids': [10001, 20001],
    })

    assert connection.commands == [
        'config',
        'undo service-port 10001',
        'undo service-port 20001',
        'interface gpon 0/2',
        'ont delete 0 0',
        'return',
        'save',
    ]


def test_huawei_driver_uses_ont_line_profile_management_settings():
    connection = FakeConnection()
    from huawei_olt_driver import OPERATIONS

    OPERATIONS['create_ont_line_profile'](connection, {
        'profile_id': 17,
        'profile_name': 'LP_CVLAN_3001',
        'dba_profile_id': 15,
        'internet_vlan': 3001,
        'tr069_vlan': 25,
        'tr069_management_enabled': False,
        'tr069_ip_index': 3,
        'omcc_encrypt_enabled': False,
    })

    assert connection.commands == [
        'config',
        'ont-lineprofile gpon profile-id 17 profile-name "LP_CVLAN_3001"',
        'omcc encrypt off',
        'tr069-management disable',
        'tcont 1 dba-profile-id 15',
        'gem add 1 eth tcont 1 encrypt on',
        'gem add 2 eth tcont 1 encrypt on',
        'gem mapping 1 0 vlan 3001',
        'gem mapping 2 0 vlan 25',
        'commit',
        'quit',
        'return',
        'save',
    ]
