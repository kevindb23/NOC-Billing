#!/usr/bin/env python3
"""Dedicated persistent Netmiko session service for routers."""
import os
import olt_session_service as shared

shared.SOCKET = os.environ.get("ROUTER_SESSION_SOCKET", "/run/router-session/router-session.sock")
shared.logger.name = "router-session"
shared.main()
