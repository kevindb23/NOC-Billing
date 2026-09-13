"""FastAPI application factory for the automation gateway."""

from typing import Any

from fastapi import FastAPI, Request
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

    @app.get("/health")
    async def health() -> dict[str, str]:
        return {"status": "ok"}

    return app
