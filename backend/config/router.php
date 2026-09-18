<?php

return [
    'netmiko_python' => env('NETMIKO_PYTHON', base_path('.venv/bin/python')),
    'netmiko_script' => env('NETMIKO_SCRIPT', base_path('scripts/netmiko_bridge.py')),
    'olt_session_socket' => env('OLT_SESSION_SOCKET', '/run/olt-session/olt-session.sock'),
    'router_session_socket' => env('ROUTER_SESSION_SOCKET', '/run/router-session/router-session.sock'),
    'bng_session_socket' => env('BNG_SESSION_SOCKET', '/run/bng-session/bng-session.sock'),
];
