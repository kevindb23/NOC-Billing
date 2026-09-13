import asyncio

from network_automation.contracts.devices import DeviceCredentials, DeviceTarget
from network_automation.transports.api import ApiTransport


class FakeResponse:
    status_code = 200
    text = "ok"

    def json(self):
        return {"status": "ok"}

    def raise_for_status(self):
        return None


class FakeClient:
    def __init__(self, **kwargs):
        self.kwargs = kwargs
        self.requests = []
        self.closed = False

    async def request(self, method, path, **kwargs):
        self.requests.append((method, path, kwargs))
        return FakeResponse()

    async def aclose(self):
        self.closed = True


def test_api_transport_pools_client_and_defaults_to_tls_verification():
    client_holder = []

    def factory(**kwargs):
        client = FakeClient(**kwargs)
        client_holder.append(client)
        return client

    target = DeviceTarget(
        driver="mikrotik_router",
        transport="api",
        hostname="router.example.test",
        metadata={"api_url": "https://router.example.test/api"},
    )
    credentials = DeviceCredentials(token="secret-token")
    transport = ApiTransport(client_factory=factory)

    async def scenario():
        session = await transport.connect(target, credentials)
        result = await session.execute("test_connection")
        await session.close()
        return result

    assert asyncio.run(scenario()) == {"status": "ok"}
    client = client_holder[0]
    assert client.kwargs["verify"] is True
    assert client.kwargs["headers"] == {"Authorization": "Bearer secret-token"}
    assert client.requests[0][0:2] == ("GET", "/health")
    assert client.closed is True
