"""FastAPI application factory for the automation gateway."""

import hmac
from typing import Any

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse

from .config import GatewaySettings, get_settings


def get_driver_registry(request: Request) -> Any:
    """Dependency hook for the future driver registry implementation."""

    return request.app.state.driver_registry


def get_transport_registry(request: Request) -> Any:
    """Dependency hook for the future transport registry implementation."""

    return request.app.state.transport_registry


def create_app(
    *,
    settings: GatewaySettings | None = None,
    driver_registry: Any = None,
    transport_registry: Any = None,
) -> FastAPI:
    """Create an app with explicit registry injection points."""

    resolved_settings = settings or get_settings()
    app = FastAPI(title=resolved_settings.service_name)
    app.state.settings = resolved_settings
    app.state.driver_registry = driver_registry
    app.state.transport_registry = transport_registry

    @app.middleware("http")
    async def require_internal_service_auth(request: Request, call_next: Any) -> Any:
        # Health is intentionally unauthenticated so Docker/orchestrators can
        # probe the process without receiving a service credential.
        if request.url.path == "/health":
            return await call_next(request)

        try:
            expected = resolved_settings.require_service_auth()
        except Exception:
            return JSONResponse(
                {"detail": "Internal service authentication is not configured."},
                status_code=503,
            )

        authorization = request.headers.get("authorization", "")
        scheme, _, presented = authorization.partition(" ")
        if scheme.lower() != "bearer" or not presented or not hmac.compare_digest(presented, expected):
            return JSONResponse({"detail": "Invalid internal service authentication."}, status_code=401)

        return await call_next(request)

    @app.get("/health")
    async def health() -> dict[str, str]:
        return {"status": "ok"}

    return app
