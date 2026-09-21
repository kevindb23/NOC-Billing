from huawei_olt_driver import OPERATIONS as HUAWEI_OPERATIONS
from hsgq_olt_driver import OPERATIONS as HSGQ_OPERATIONS


class FakeConnection:
    def __init__(self, output):
        self.output = output
        self.commands = []

    def send_command_timing(self, command, **kwargs):
        self.commands.append(command)
        return self.output


AUTOFIND_OUTPUT = """
Number : 1
F/S/P : 0/1/1
Ont SN : HWTC12345678
VenderID : HWTC

Number : 2
F/S/P : 0/1/2
Ont SN : HWTC87654321
VenderID : HWTC
"""


def test_huawei_discovery_parses_autofind_records():
    connection = FakeConnection(AUTOFIND_OUTPUT)
    records = HUAWEI_OPERATIONS['discover_onts'](connection)

    assert connection.commands == ['display ont autofind all']
    assert records == [
        {'frame': 0, 'slot': 1, 'pon_port': 1, 'serial_number': 'HWTC12345678', 'name': 'ONT HWTC12345678'},
        {'frame': 0, 'slot': 1, 'pon_port': 2, 'serial_number': 'HWTC87654321', 'name': 'ONT HWTC87654321'},
    ]


def test_hsgq_discovery_ignores_malformed_blocks():
    connection = FakeConnection(AUTOFIND_OUTPUT + "\nNumber : 3\nF/S/P : invalid\n")
    records = HSGQ_OPERATIONS['discover_onts'](connection)

    assert len(records) == 2
