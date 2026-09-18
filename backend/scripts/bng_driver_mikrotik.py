"""MikroTik BNG driver boundary.

MikroTik has its own command syntax and is intentionally not allowed to edit
the Linux Accel-PPP configuration file.
"""
DEVICE_TYPE = "mikrotik_routeros"


def unsupported(*args, **kwargs):
    raise RuntimeError("Accel-PPP configuration is supported only by the Linux BNG driver.")


read_accel_ppp_config = unsupported
preview_accel_ppp_config = unsupported
save_accel_ppp_config = unsupported
