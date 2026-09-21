from hsgq_olt_driver import OPERATIONS


class FakeConnection:
    def __init__(self):
        self.commands = []

    def send_command_timing(self, command, **kwargs):
        self.commands.append(command)
        return command


def test_hsgq_driver_generates_huawei_compatible_vlan_commands():
    connection = FakeConnection()
    OPERATIONS["create_s_vlan"](connection, {"vlan_id": 10, "port": "0/3 1"})

    assert connection.commands == [
        "config",
        "vlan 10 smart",
        "vlan attrib 10 q-in-q",
        "vlan forwarding 10 vlan-connect",
        "port vlan 10 0/3 1",
        "return",
        "save",
    ]


def test_hsgq_driver_generates_a_vlan_range_without_treating_to_as_a_vlan_type():
    connection = FakeConnection()
    OPERATIONS["create_c_vlan"](connection, {"vlan_id": 35, "vlan_to": 40, "vlan_type": "to"})

    assert connection.commands == [
        "config",
        "vlan 35 to 40",
        "return",
        "save",
    ]


def test_hsgq_driver_generates_a_tr069_server_profile_command():
    connection = FakeConnection()
    OPERATIONS["create_tr069_server_profile"](connection, {
        "profile_id": 1,
        "profile_name": "TR069_PROF",
        "url": "http://10.0.10.156:7547/cwmp",
        "username": "acs",
        "password": "secret",
    })

    assert connection.commands == [
        "config",
        'ont tr069-server-profile add profile-id 1 profile-name "TR069_PROF" url "http://10.0.10.156:7547/cwmp" user "acs" "secret"',
        "quit",
        "return",
        "save",
    ]


def test_hsgq_driver_creates_terminal_user_with_interactive_flow():
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


def test_hsgq_driver_applies_terminal_user_password_policy():
    connection = FakeConnection()
    OPERATIONS['update_terminal_user_policy'](connection, {'security_enabled': False, 'security_length': 12})

    assert connection.commands == [
        'config',
        'system modify logon password disable all',
        'system user password security mode normal',
        'system user password security-length 12',
        'return',
        'save',
    ]
