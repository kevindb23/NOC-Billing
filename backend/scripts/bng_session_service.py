#!/usr/bin/env python3
"""Dedicated persistent Netmiko session service for BNG devices."""
import os
import olt_session_service as shared

shared.SOCKET = os.environ.get("BNG_SESSION_SOCKET", "/run/bng-session/bng-session.sock")
shared.logger.name = "bng-session"
shared.main()
