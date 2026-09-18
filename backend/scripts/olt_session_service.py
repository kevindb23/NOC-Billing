#!/usr/bin/env python3
"""Persistent Netmiko session manager for OLT devices."""
import json, logging, os, socket, threading, time
from typing import Any
from netmiko import ConnectHandler
from huawei_olt_driver import OPERATIONS as HUAWEI_OPERATIONS
from bng_driver_linux import read_accel_ppp_config, preview_accel_ppp_config, save_accel_ppp_config, preview_iptables, save_iptables
from bng_driver_mikrotik import unsupported as mikrotik_accel_ppp_unsupported

SOCKET = os.environ.get("OLT_SESSION_SOCKET", "/run/olt-session/olt-session.sock")
sessions: dict[str, dict[str, Any]] = {}
lock = threading.Lock()
logging.basicConfig(level=logging.INFO, format='%(asctime)s %(levelname)s olt-session %(message)s')
logger = logging.getLogger('olt-session')

BNG_OPERATIONS = {
    "linux": {
        "read_accel_ppp_config": read_accel_ppp_config,
        "preview_accel_ppp_config": preview_accel_ppp_config,
        "save_accel_ppp_config": save_accel_ppp_config,
        "preview_iptables": preview_iptables,
        "save_iptables": save_iptables,
    },
    "mikrotik_routeros": {
        "read_accel_ppp_config": mikrotik_accel_ppp_unsupported,
        "preview_accel_ppp_config": mikrotik_accel_ppp_unsupported,
        "save_accel_ppp_config": mikrotik_accel_ppp_unsupported,
    },
}

def reply(conn, payload):
    try: conn.sendall((json.dumps(payload) + "\n").encode())
    except BrokenPipeError: pass

def session_loop(key: str, config: dict[str, Any]):
    while True:
        with lock:
            session = sessions.get(key)
            if not session or not session.get("requested"):
                return
            session["status"] = "connecting"
        try:
            connection = ConnectHandler(**config)
            if config.get("device_type") == "huawei_olt_ssh":
                logger.info("entering Huawei privileged mode olt_id=%s", key)
                connection.enable()
                logger.info("Huawei privileged mode ready olt_id=%s", key)
            with lock:
                if key not in sessions or not sessions[key].get("requested"):
                    connection.disconnect(); return
                sessions[key]["connection"] = connection
                sessions[key]["status"] = "connected"
                sessions[key]["last_connected_at"] = time.time()
            while True:
                with lock:
                    session = sessions.get(key)
                    if not session or not session.get("requested"):
                        connection.disconnect(); return
                if not connection.is_alive():
                    raise OSError("SSH session disconnected")
                try: connection.write_channel("\n")
                except Exception: pass
                time.sleep(10)
        except Exception as exc:
            with lock:
                if key not in sessions or not sessions[key].get("requested"): return
                sessions[key]["status"] = "disconnected"
                sessions[key]["error"] = str(exc)[:500]
                sessions[key].pop("connection", None)
            time.sleep(5)

def handle(request: dict[str, Any]) -> dict[str, Any]:
    action = request.get("action")
    entity = request.get("entity", "olt")
    key = str(request.get("entity_id", request.get("olt_id", "")))
    if not key: return {"ok": False, "message": "Device identifier is required."}
    if action == "start":
        with lock:
            existing = sessions.get(key)
            if existing and existing.get("requested"): return {"ok": True, "status": existing.get("status", "connecting")}
        config = {"device_type": request["device_type"], "host": request["host"], "port": request["port"], "username": request["username"], "password": request["password"], "secret": request["password"], "timeout": 15}
        with lock:
            sessions[key] = {"requested": True, "status": "connecting", "error": None, "config": config}
        threading.Thread(target=session_loop, args=(key, config), daemon=True).start()
        return {"ok": True, "status": "connecting"}
    if action == "stop":
        with lock:
            session = sessions.get(key)
            if not session: return {"ok": True, "status": "stopped"}
            session["requested"] = False
            connection = session.get("connection")
        if connection:
            try: connection.disconnect()
            except Exception: pass
        with lock: sessions.pop(key, None)
        return {"ok": True, "status": "stopped"}
    if action == "status":
        with lock:
            session = sessions.get(key, {})
            return {"ok": True, "status": session.get("status", "stopped"), "error": session.get("error")}
    if action == "provision":
        with lock:
            session = sessions.get(key, {})
            connection = session.get("connection")
            status = session.get("status", "stopped")
        if status != "connected" or connection is None:
            logger.error("provision rejected entity=%s id=%s status=%s", entity, key, status)
            return {"ok": False, "message": "The OLT is not connected. Start the persistent SSH session first."}
        if entity == "bng":
            provisioner = BNG_OPERATIONS.get(request.get("device_type"), {}).get(request.get("operation"))
        else:
            provisioner = HUAWEI_OPERATIONS.get(request.get("operation")) if request.get("device_type") == "huawei_olt_ssh" else None
        if not provisioner:
            return {"ok": False, "message": "The selected device provisioning operation is not supported."}
        try:
            values = request.get("values", {})
            if not isinstance(values, dict):
                values = {}
            safe_values = {name: ("***" if "secret" in name or name == "password" else value) for name, value in values.items()}
            logger.info("provision started entity=%s id=%s operation=%s values=%s", entity, key, request.get("operation"), safe_values)
            output = provisioner(connection, request.get("values", {}))
            if entity == "bng":
                logger.info("provision completed entity=%s id=%s operation=%s path=%s backup=%s", entity, key, request.get("operation"), output.get("path"), output.get("backup"))
            else:
                logger.info("provision completed entity=%s id=%s operation=%s output=%s", entity, key, request.get("operation"), output)
            return {"ok": True, "output": output}
        except Exception as exc:
            logger.exception("provision failed olt_id=%s operation=%s", key, request.get("operation"))
            return {"ok": False, "message": str(exc)[:500]}
    return {"ok": False, "message": "Unsupported session action."}

def main():
    os.makedirs(os.path.dirname(SOCKET), exist_ok=True)
    try: os.unlink(SOCKET)
    except FileNotFoundError: pass
    server = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    server.bind(SOCKET); os.chmod(SOCKET, 0o660); server.listen(32)
    while True:
        conn, _ = server.accept()
        try:
            request = json.loads(conn.recv(65536).decode())
            reply(conn, handle(request))
        except Exception as exc: reply(conn, {"ok": False, "message": str(exc)})
        finally: conn.close()

if __name__ == "__main__": main()
