import unittest

from bng_driver_linux import ensure_vlan_interfaces, remove_vlan_interfaces


class BngVlanDriverTest(unittest.TestCase):
    def test_ensure_builds_nested_qinq_commands_with_netplan_origin_hint(self):
        connection = RecordingConnection()

        result = ensure_vlan_interfaces(connection, {
            "parent_interface": "ens17",
            "interfaces": [
                {"name": "ens17.50", "vlan_id": 50, "parent": "ens17"},
                {"name": "ens17.50.3001", "vlan_id": 3001, "parent": "ens17.50"},
            ],
        })

        self.assertIn("netplan set --origin-hint ispbox-network", connection.commands[0])
        self.assertIn("netplan set --origin-hint ispbox-parent", connection.commands[0])
        self.assertIn("network.ethernets.ens17.optional=true", connection.commands[0])
        self.assertIn("printf '%s\\n' 'network:'", connection.commands[0])
        self.assertTrue(any("ip link show ens17.50 || ip link add link ens17 name ens17.50 type vlan id 50" in command for command in connection.commands))
        self.assertTrue(any("ip link show ens17.50.3001 || ip link add link ens17.50 name ens17.50.3001 type vlan id 3001" in command for command in connection.commands))
        self.assertEqual(result["interfaces"], ["ens17.50", "ens17.50.3001"])
        self.assertTrue(any(command.startswith("ip -o link show") for command in connection.commands))

    def test_ensure_fails_when_remote_interface_is_not_present(self):
        connection = RecordingConnection(verify_interfaces=False)

        with self.assertRaisesRegex(RuntimeError, "did not create VLAN interface ens17.50"):
            ensure_vlan_interfaces(connection, {
                "parent_interface": "ens17",
                "interfaces": [{"name": "ens17.50", "vlan_id": 50, "parent": "ens17"}],
            })

    def test_ensure_fails_when_runtime_interface_exists_but_netplan_is_missing(self):
        connection = RecordingConnection(netplan_interfaces=set())

        with self.assertRaisesRegex(RuntimeError, "did not persist VLAN interface ens17.50"):
            ensure_vlan_interfaces(connection, {
                "parent_interface": "ens17",
                "interfaces": [{"name": "ens17.50", "vlan_id": 50, "parent": "ens17"}],
            })

    def test_remove_deletes_nested_interfaces_before_parent_and_clears_netplan(self):
        connection = RecordingConnection()

        remove_vlan_interfaces(connection, {
            "interfaces": [
                {"name": "ens17.50", "vlan_id": 50, "parent": "ens17"},
                {"name": "ens17.50.3001", "vlan_id": 3001, "parent": "ens17.50"},
            ],
        })

        delete_index = next(index for index, command in enumerate(connection.commands) if "ip link delete ens17.50.3001" in command)
        parent_index = next(index for index, command in enumerate(connection.commands) if "ip link delete ens17.50" in command and "3001" not in command)
        self.assertLess(delete_index, parent_index)
        self.assertTrue(any("netplan set --origin-hint ispbox-network" in command and "=null" in command for command in connection.commands))
        self.assertTrue(any(command.startswith("ip -o link show") for command in connection.commands))


class RecordingConnection:
    def __init__(self, verify_interfaces=True, netplan_interfaces=None):
        self.commands = []
        self.verify_interfaces = verify_interfaces
        self.netplan_interfaces = set(netplan_interfaces if netplan_interfaces is not None else {"ens17.50", "ens17.50.3001"})

    def send_command(self, command, read_timeout=15):
        self.commands.append(command)
        if command.startswith("ip -o link show"):
            interface = command.rsplit(" ", 1)[-1]
            if any(f"ip link delete {interface}" in previous for previous in self.commands):
                return "Device does not exist."
            if not self.verify_interfaces:
                return "Device does not exist."
            return f"42: {interface}@ens17: <BROADCAST,UP>"
        if command == "netplan get network.vlans":
            return "\n".join(f"{interface}:\n  id: 50" for interface in sorted(self.netplan_interfaces))
        return "ok"


if __name__ == "__main__":
    unittest.main()
