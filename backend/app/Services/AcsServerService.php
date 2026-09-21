<?php

namespace App\Services;

use App\Models\AcsServer;
use App\Models\Ont;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AcsServerService
{
    public function __construct(private RouterCommandExecutor $executor) {}
    public function testSsh(array $values): array
    {
        $url = parse_url((string) ($values['api_url'] ?? ''));
        if (! is_array($url) || empty($url['host'])) throw new RuntimeException('Enter a valid ACS API URL so its host can be used for SSH testing.');
        $endpoint = $url['host'].':'.(int) ($values['ssh_port'] ?? 22);
        Log::info('ACS SSH connectivity test requested', ['endpoint' => $endpoint]);
        $result = $this->executor->executeNetmiko(['management_endpoint' => $endpoint, 'username' => $values['ssh_username'] ?? '', 'password' => $values['ssh_password'] ?? ''], 'linux');
        Log::info('ACS SSH connectivity test completed', ['endpoint' => $endpoint, 'success' => (bool) ($result['success'] ?? false)]);
        return $result;
    }
    public function testStoredSsh(AcsServer $server): array { return $this->testSsh(['api_url' => $server->api_url, 'ssh_username' => $server->ssh_username, 'ssh_password' => $server->ssh_password, 'ssh_port' => $server->ssh_port]); }

    public function getPasswordComplexity(AcsServer $server): array
    {
        Log::info('ACS password complexity requested', [
            'acs_server_id' => $server->public_id,
            'action' => 'read_password_complexity',
        ]);
        try {
            $result = $this->executor->executeNetmikoShell(
                $this->sshConfig($server),
                $this->asRoot('awk -F= \'/^GENIEACS_UI_MIN_PASSWORD_LENGTH=/{print $2}\' /opt/genieacs/genieacs.env'),
            );
        } catch (RuntimeException $exception) {
            Log::error('ACS password complexity read failed', [
                'acs_server_id' => $server->public_id,
                'action' => 'read_password_complexity',
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
        $length = (int) trim((string) ($result['output'] ?? ''));

        Log::info('ACS password complexity read', [
            'acs_server_id' => $server->public_id,
            'action' => 'read_password_complexity',
            'minimum_password_length' => $length > 0 ? $length : 6,
        ]);

        return ['minimum_password_length' => $length > 0 ? $length : 6];
    }

    public function updatePasswordComplexity(AcsServer $server, int $length): array
    {
        $line = "GENIEACS_UI_MIN_PASSWORD_LENGTH={$length}";
        $script = "set -eu; file=/opt/genieacs/genieacs.env; if grep -q '^GENIEACS_UI_MIN_PASSWORD_LENGTH=' \"\$file\"; then sed -i 's/^GENIEACS_UI_MIN_PASSWORD_LENGTH=.*/{$line}/' \"\$file\"; else printf '\\n{$line}\\n' >> \"\$file\"; fi; systemctl restart genieacs-ui; systemctl is-active --quiet genieacs-ui";
        Log::info('ACS password complexity update requested', [
            'acs_server_id' => $server->public_id,
            'action' => 'update_password_complexity',
            'minimum_password_length' => $length,
            'service' => 'genieacs-ui',
        ]);
        try {
            $this->executor->executeNetmikoShell($this->sshConfig($server), $this->asRoot($script));
        } catch (RuntimeException $exception) {
            Log::error('ACS password complexity update failed', [
                'acs_server_id' => $server->public_id,
                'action' => 'update_password_complexity',
                'minimum_password_length' => $length,
                'service' => 'genieacs-ui',
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        Log::info('ACS UI password complexity updated', [
            'acs_server_id' => $server->public_id,
            'minimum_password_length' => $length,
            'service' => 'genieacs-ui',
        ]);

        return [
            'minimum_password_length' => $length,
            'service' => 'genieacs-ui',
            'status' => 'applied',
            'message' => 'Password complexity updated and GenieACS UI restarted.',
        ];
    }

    /** @return array{management_endpoint:string,username:string,password:string} */
    private function sshConfig(AcsServer $server): array
    {
        $url = parse_url((string) $server->api_url);
        if (! is_array($url) || empty($url['host'])) {
            throw new RuntimeException('The ACS API URL is invalid, so its SSH host cannot be determined.');
        }

        return [
            'management_endpoint' => $url['host'].':'.(int) ($server->ssh_port ?: 22),
            'username' => (string) $server->ssh_username,
            'password' => (string) $server->ssh_password,
        ];
    }

    private function asRoot(string $command): string
    {
        $quoted = escapeshellarg($command);

        return 'if [ "$(id -u)" -eq 0 ]; then sh -c '.$quoted.'; else sudo -n sh -c '.$quoted.'; fi';
    }
    public function testApi(array $values): array
    {
        $url = $this->apiProbeUrl((string) ($values['api_url'] ?? ''));
        Log::info('ACS API connectivity test requested', ['url' => $url]);
        try {
            $response = Http::timeout(10)->withBasicAuth((string) ($values['api_username'] ?? ''), (string) ($values['api_password'] ?? ''))->acceptJson()->get($url);
        } catch (ConnectionException $exception) {
            Log::error('ACS API connectivity test could not connect', ['url' => $url, 'error' => $exception->getMessage()]);
            throw $exception;
        }
        if ($response->successful() || $response->redirect()) {
            Log::info('ACS API connectivity test completed', ['url' => $url, 'status' => $response->status()]);
            return ['message' => 'ACS API responded successfully.', 'status' => $response->status(), 'url' => $url];
        }
        Log::warning('ACS API connectivity test failed', ['url' => $url, 'status' => $response->status()]);
        if (in_array($response->status(), [401, 403], true)) throw new RuntimeException('ACS API authentication failed. Verify the API username and password.');
        throw new RuntimeException("ACS API responded with HTTP {$response->status()}.");
    }
    public function testStoredApi(AcsServer $server): array { return $this->testApi(['api_url' => $server->api_url, 'api_username' => $server->api_username, 'api_password' => $server->api_password]); }

    public function deviceForOnt(Ont $ont): array
    {
        $server = $ont->acsServer;
        if (! $server) {
            throw new RuntimeException('Assign an ACS server to this ONT before opening provisioning.');
        }

        $devices = $this->findDevices($server, $ont->serial_number);
        $device = $devices[0] ?? null;
        if (! is_array($device) || blank($device['_id'] ?? null)) {
            throw new RuntimeException('This ONT has not contacted the assigned ACS yet. Verify its TR-069 VLAN, IP address, and CWMP configuration.');
        }

        if ($this->refreshUpstreamPort($server, (string) $device['_id'])) {
            $device = $this->findDevices($server, $ont->serial_number)[0] ?? $device;
        }

        return $this->normalizeDevice($ont, $server, $device);
    }

    /** @return array{content: string, device_id: string, file_name: string} */
    public function exportConfiguration(Ont $ont): array
    {
        $server = $ont->acsServer;
        if (! $server) {
            throw new RuntimeException('Assign an ACS server to this ONT before exporting its configuration.');
        }

        $device = $this->findDevices($server, $ont->serial_number)[0] ?? null;
        $deviceId = is_array($device) ? (string) ($device['_id'] ?? '') : '';
        if ($deviceId === '') {
            throw new RuntimeException('This ONT has not contacted the assigned ACS yet. Verify its TR-069 VLAN, IP address, and CWMP configuration.');
        }

        $fileName = $deviceId.'/hw_ctree.xml';
        try {
            $response = Http::timeout(40)
                ->withBasicAuth((string) $server->api_username, (string) $server->api_password)
                ->acceptJson()
                ->post($this->taskUrl($server, $deviceId, 30000), [
                    'name' => 'upload',
                    'fileType' => '3 Vendor Configuration File',
                    'fileName' => $fileName,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException($this->acsConnectionError($server, $exception->getMessage(), 'request the configuration export task'), 0, $exception);
        }

        if ($response->status() === 202) {
            throw new RuntimeException('The configuration export was queued because the ONT is not reachable right now. Retry after its next CWMP inform.');
        }
        if (! $response->successful()) {
            $this->throwApiError($response, 'Unable to request the ONT configuration export.');
        }

        $file = $this->waitForUploadedFile($server, $fileName);

        return ['content' => $file, 'device_id' => $deviceId, 'file_name' => 'hw_ctree.xml'];
    }

    public function enqueueOntTask(Ont $ont, string $action, ?string $firmwareUrl = null, ?array $wlan = null, ?string $upstreamPort = null, ?array $pppoe = null): array
    {
        $server = $ont->acsServer;
        if (! $server) {
            throw new RuntimeException('Assign an ACS server to this ONT before sending provisioning commands.');
        }

        $device = $this->findDevices($server, $ont->serial_number)[0] ?? null;
        $deviceId = is_array($device) ? (string) ($device['_id'] ?? '') : '';
        if ($deviceId === '') {
            throw new RuntimeException('This ONT has not contacted the assigned ACS yet. Verify its TR-069 VLAN, IP address, and CWMP configuration.');
        }

        $task = match ($action) {
            'refresh_device' => ['name' => 'refreshObject', 'objectName' => ''],
            'refresh_wifi' => ['name' => 'refreshObject', 'objectName' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration'],
            'refresh_lan' => ['name' => 'refreshObject', 'objectName' => 'InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig'],
            'reboot' => ['name' => 'reboot'],
            'firmware' => [
                'name' => 'download',
                'fileType' => '1 Firmware Upgrade Image',
                'fileName' => $firmwareUrl,
                'targetFileName' => basename((string) parse_url((string) $firmwareUrl, PHP_URL_PATH)),
            ],
            'update_wifi' => $this->wlanTask($wlan ?? [], $device),
            'update_upstream_port' => $this->upstreamPortTask($upstreamPort),
            'configure_pppoe' => $this->pppoeTask($device, (string) ($pppoe['username'] ?? ''), (string) ($pppoe['password'] ?? '')),
            default => throw new RuntimeException('The selected ONT provisioning operation is not supported.'),
        };

        return $this->postTask(
            $server,
            $deviceId,
            $task,
            $action,
            $action === 'configure_pppoe' ? 30000 : null,
        );
    }

    public function configurePppoe(Ont $ont, string $username, string $password, ?int $vlanId = null): array
    {
        $server = $ont->acsServer;
        if (! $server) {
            throw new RuntimeException('Assign an ACS server to this ONT before activating it with PPPoE configuration.');
        }

        $device = $this->findDevices($server, $ont->serial_number)[0] ?? null;
        $deviceId = is_array($device) ? (string) ($device['_id'] ?? '') : '';
        if ($deviceId === '') {
            throw new RuntimeException('This ONT has not contacted the assigned ACS yet. Verify its TR-069 VLAN, IP address, and CWMP configuration.');
        }

        return $this->postTask($server, $deviceId, $this->pppoeTask($device, $username, $password, $vlanId), 'configure_pppoe', 30000);
    }

    public function disablePppoe(Ont $ont): array
    {
        $server = $ont->acsServer;
        if (! $server) {
            throw new RuntimeException('Assign an ACS server to this ONT before disabling its PPPoE configuration.');
        }

        $device = $this->findDevices($server, $ont->serial_number)[0] ?? null;
        $deviceId = is_array($device) ? (string) ($device['_id'] ?? '') : '';
        if ($deviceId === '') {
            throw new RuntimeException('This ONT has not contacted the assigned ACS, so its PPPoE configuration could not be disabled.');
        }

        return $this->postTask($server, $deviceId, $this->pppoeDisableTask($device), 'disable_pppoe');
    }

    /** @return array<int, array<string, mixed>> */
    private function findDevices(AcsServer $server, string $serialNumber): array
    {
        $query = json_encode(['_deviceId._SerialNumber' => $serialNumber], JSON_THROW_ON_ERROR);
        $url = $this->collectionUrl($server, 'devices').'?query='.rawurlencode($query);
        Log::debug('ACS device lookup requested', ['acs_server_id' => $server->public_id, 'serial_number' => $serialNumber]);
        try {
            $response = Http::timeout(15)
                ->withBasicAuth((string) $server->api_username, (string) $server->api_password)
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException $exception) {
            Log::error('ACS device lookup could not connect', ['acs_server_id' => $server->public_id, 'serial_number' => $serialNumber, 'error' => $exception->getMessage()]);
            throw $exception;
        }

        if (! $response->successful()) {
            Log::warning('ACS device lookup failed', ['acs_server_id' => $server->public_id, 'serial_number' => $serialNumber, 'status' => $response->status()]);
            $this->throwApiError($response, 'Unable to read devices from the assigned ACS.');
        }

        $data = $response->json();
        $devices = is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
        Log::info('ACS device lookup completed', ['acs_server_id' => $server->public_id, 'serial_number' => $serialNumber, 'device_count' => count($devices)]);
        return $devices;
    }

    /** @param array<string, mixed> $device */
    private function normalizeDevice(Ont $ont, AcsServer $server, array $device): array
    {
        $deviceInfo = data_get($device, 'InternetGatewayDevice.DeviceInfo', []);
        $deviceId = data_get($device, '_deviceId', []);

        $wlan = $this->wlanBands($device);
        $wan = $this->wanDetails($device);

        return [
            'name' => $ont->name,
            'serial_number' => $ont->serial_number,
            'status' => $ont->status,
            'device_id' => (string) $device['_id'],
            'manufacturer' => $this->scalarDeviceValue(data_get($deviceId, '_Manufacturer') ?: data_get($deviceInfo, 'Manufacturer')),
            'model' => $this->scalarDeviceValue(data_get($deviceId, '_ProductClass') ?: data_get($deviceInfo, 'ModelName')),
            'last_inform' => $device['_lastInform'] ?? null,
            'acs_server' => ['public_id' => $server->public_id, 'name' => $server->name, 'status' => $server->status],
            'parameters' => [
                'wifi_ssid' => $this->scalarDeviceValue(data_get($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID')),
                'lan_status' => $this->scalarDeviceValue(data_get($device, 'InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.1.Status')),
                'upstream_port' => $this->integerDeviceValue(data_get($device, 'InternetGatewayDevice.DeviceInfo.X_HW_UpPortMode')),
                'wlan' => $wlan,
                'wan' => $wan,
            ],
        ];
    }

    /** @return array{internet: array<int, array<string, mixed>>, tr069: array<int, array<string, mixed>>, connection_request_url: string|null} */
    private function wanDetails(array $device): array
    {
        $internet = [];
        $tr069 = [];
        $wanDevices = data_get($device, 'InternetGatewayDevice.WANDevice', []);

        if (is_array($wanDevices)) {
            foreach ($wanDevices as $wanIndex => $wanDevice) {
                if (! is_array($wanDevice)) {
                    continue;
                }

                $connectionDevices = $wanDevice['WANConnectionDevice'] ?? [];
                if (! is_array($connectionDevices)) {
                    continue;
                }

                foreach ($connectionDevices as $connectionIndex => $connectionDevice) {
                    if (! is_array($connectionDevice)) {
                        continue;
                    }

                    $pppConnections = $connectionDevice['WANPPPConnection'] ?? [];
                    if (is_array($pppConnections)) {
                        foreach ($pppConnections as $connectionIndexValue => $connection) {
                            if (! is_array($connection)) {
                                continue;
                            }

                            $internet[] = $this->normalizeWanConnection(
                                $connection,
                                'pppoe',
                                'InternetGatewayDevice.WANDevice.'.(int) $wanIndex.'.WANConnectionDevice.'.(int) $connectionIndex.'.WANPPPConnection.'.(int) $connectionIndexValue,
                            );
                        }
                    }

                    $ipConnections = $connectionDevice['WANIPConnection'] ?? [];
                    if (is_array($ipConnections)) {
                        foreach ($ipConnections as $connectionIndexValue => $connection) {
                            if (! is_array($connection)) {
                                continue;
                            }

                            $tr069[] = $this->normalizeWanConnection(
                                $connection,
                                'ip',
                                'InternetGatewayDevice.WANDevice.'.(int) $wanIndex.'.WANConnectionDevice.'.(int) $connectionIndex.'.WANIPConnection.'.(int) $connectionIndexValue,
                            );
                        }
                    }
                }
            }
        }

        return [
            'internet' => array_values($internet),
            'tr069' => array_values($tr069),
            'connection_request_url' => $this->scalarDeviceValue(data_get($device, 'InternetGatewayDevice.ManagementServer.ConnectionRequestURL')),
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeWanConnection(array $connection, string $type, string $path): array
    {
        return [
            'type' => $type,
            'name' => $this->scalarDeviceValue(data_get($connection, 'Name') ?: data_get($connection, 'ConnectionType')),
            'pppoe_username' => $type === 'pppoe' ? $this->scalarDeviceValue(data_get($connection, 'Username')) : null,
            'connection_status' => $this->scalarDeviceValue(data_get($connection, 'ConnectionStatus') ?: data_get($connection, 'Status')),
            'ip_address' => $this->scalarDeviceValue(data_get($connection, 'ExternalIPAddress') ?: data_get($connection, 'IPAddress')),
            'subnet_mask' => $this->scalarDeviceValue(data_get($connection, 'SubnetMask')),
            'gateway' => $this->scalarDeviceValue(data_get($connection, 'DefaultGateway')),
            'dns_servers' => $this->listDeviceValues(data_get($connection, 'DNSServers')),
            'enabled' => $this->booleanDeviceValue(data_get($connection, 'Enable'), true),
            'path' => $path,
        ];
    }

    /** @return array<int, string> */
    private function listDeviceValues(mixed $value): array
    {
        if (is_array($value) && (array_key_exists('_value', $value) || array_key_exists('value', $value))) {
            $value = $value['_value'] ?? $value['value'];
        }

        if (is_string($value)) {
            return array_values(array_filter(preg_split('/[\\s,;]+/', trim($value)) ?: [], fn ($item) => $item !== ''));
        }

        if (is_scalar($value)) {
            return [(string) $value];
        }

        return [];
    }

    /** @return array{band_24: array<string, mixed>|null, band_5: array<string, mixed>|null} */
    private function wlanBands(array $device): array
    {
        $bands = ['band_24' => null, 'band_5' => null];
        $configurations = data_get($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration', []);

        if (! is_array($configurations)) {
            return $bands;
        }

        foreach ($configurations as $index => $configuration) {
            if (! is_array($configuration)) {
                continue;
            }

            $radioPath = $this->scalarDeviceValue($configuration['LowerLayers'] ?? null);
            $frequency = null;
            if (is_string($radioPath) && preg_match('/\.Radio\.(\d+)$/', $radioPath, $matches)) {
                $frequency = $this->scalarDeviceValue(data_get($device, 'InternetGatewayDevice.LANDevice.1.WiFi.Radio.'.(int) $matches[1].'.OperatingFrequencyBand'));
            }

            $key = str_contains(strtolower((string) $frequency), '5')
                ? 'band_5'
                : (str_contains(strtolower((string) $frequency), '2.4') || (int) $index === 1 ? 'band_24' : ((int) $index === 2 ? 'band_5' : null));

            if ($key !== null && $bands[$key] === null) {
                $bands[$key] = $this->normalizeWlanBand($configuration);
            }
        }

        return $bands;
    }

    /** @return array<string, bool|int|string|null> */
    private function normalizeWlanBand(mixed $band): array
    {
        return [
            'enabled' => $this->booleanDeviceValue(data_get($band, 'Enable')),
            'ssid' => $this->scalarDeviceValue(data_get($band, 'SSID')),
            'hide_ssid' => $this->booleanDeviceValue(data_get($band, 'SSIDAdvertisementEnabled'), true),
            'auto_channel' => $this->booleanDeviceValue(data_get($band, 'AutoChannelEnable'), true),
            'channel' => $this->integerDeviceValue(data_get($band, 'Channel')),
        ];
    }

    /** @return array{name: string, parameterValues: array<int, array<int, mixed>>} */
    private function wlanTask(array $wlan, array $device): array
    {
        $parameterValues = [];
        $bands = $this->wlanParameterPaths($device);

        foreach ($bands as $key => $path) {
            if (! array_key_exists($key, $wlan) || ! is_array($wlan[$key])) {
                continue;
            }

            $band = is_array($wlan[$key] ?? null) ? $wlan[$key] : [];
            $parameterValues[] = [$path.'.Enable', (bool) ($band['enabled'] ?? false), 'xsd:boolean'];
            $parameterValues[] = [$path.'.SSID', (string) ($band['ssid'] ?? ''), 'xsd:string'];
            $parameterValues[] = [$path.'.SSIDAdvertisementEnabled', ! (bool) ($band['hide_ssid'] ?? false), 'xsd:boolean'];
            $parameterValues[] = [$path.'.AutoChannelEnable', (bool) ($band['auto_channel'] ?? true), 'xsd:boolean'];

            if (array_key_exists('channel', $band) && $band['channel'] !== null && $band['channel'] !== '') {
                $parameterValues[] = [$path.'.Channel', (int) $band['channel'], 'xsd:unsignedInt'];
            }

            if (filled($band['password'] ?? null)) {
                foreach ($this->passwordParameterPaths($device, $path) as $passwordPath) {
                    $parameterValues[] = [$passwordPath, (string) $band['password'], 'xsd:string'];
                }
            }
        }

        return ['name' => 'setParameterValues', 'parameterValues' => $parameterValues];
    }

    /** @return array<string, string> */
    private function wlanParameterPaths(array $device): array
    {
        $paths = [];
        $configurations = data_get($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration', []);

        if (! is_array($configurations)) {
            return $paths;
        }

        foreach ($configurations as $index => $configuration) {
            if (! is_array($configuration)) {
                continue;
            }

            $radioPath = $this->scalarDeviceValue($configuration['LowerLayers'] ?? null);
            $frequency = null;
            if (is_string($radioPath) && preg_match('/\.Radio\.(\d+)$/', $radioPath, $matches)) {
                $frequency = $this->scalarDeviceValue(data_get($device, 'InternetGatewayDevice.LANDevice.1.WiFi.Radio.'.(int) $matches[1].'.OperatingFrequencyBand'));
            }

            $key = str_contains(strtolower((string) $frequency), '5')
                ? 'band_5'
                : (str_contains(strtolower((string) $frequency), '2.4') || (int) $index === 1 ? 'band_24' : ((int) $index === 2 ? 'band_5' : null));

            if ($key !== null && ! array_key_exists($key, $paths)) {
                $paths[$key] = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.'.(int) $index;
            }
        }

        return $paths;
    }

    /** @return array<int, string> */
    private function passwordParameterPaths(array $device, string $configurationPath): array
    {
        $configuration = data_get($device, $configurationPath, []);
        $paths = [];
        foreach (['KeyPassphrase', 'PreSharedKey.1.KeyPassphrase', 'PreSharedKey.1.PreSharedKey'] as $suffix) {
            if ($this->hasDeviceParameter($configuration, $suffix)) {
                $paths[] = $configurationPath.'.'.$suffix;
            }
        }

        return $paths !== [] ? $paths : [$configurationPath.'.PreSharedKey.1.PreSharedKey'];
    }

    private function hasDeviceParameter(mixed $value, string $path): bool
    {
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    private function upstreamPortTask(?string $upstreamPort): array
    {
        $modes = ['optical' => 0, 'lan1' => 1, 'lan2' => 2, 'lan3' => 3, 'lan4' => 4];
        if ($upstreamPort === null || ! array_key_exists($upstreamPort, $modes)) {
            throw new RuntimeException('Select an upstream port before applying the change.');
        }

        return [
            'name' => 'setParameterValues',
            'parameterValues' => [[
                'InternetGatewayDevice.DeviceInfo.X_HW_UpPortMode',
                $modes[$upstreamPort],
                'xsd:unsignedInt',
            ]],
        ];
    }

    /** @param array<string, mixed> $device */
    private function pppoeTask(array $device, string $username, string $password, ?int $vlanId = null): array
    {
        $path = $this->pppConnectionPath($device, $vlanId);

        Log::info('ACS PPPoE WAN path selected', [
            'wan_path' => $path,
            'internet_vlan' => $vlanId,
        ]);

        return [
            'name' => 'setParameterValues',
            'parameterValues' => [
                [$path.'.Username', $username, 'xsd:string'],
                [$path.'.Password', $password, 'xsd:string'],
                [$path.'.Enable', true, 'xsd:boolean'],
                [$path.'.ConnectionTrigger', 'AlwaysOn', 'xsd:string'],
            ],
        ];
    }

    /** @param array<string, mixed> $device */
    private function pppoeDisableTask(array $device): array
    {
        $path = $this->pppConnectionPath($device);

        return [
            'name' => 'setParameterValues',
            'parameterValues' => [
                [$path.'.Enable', false, 'xsd:boolean'],
                [$path.'.ConnectionTrigger', 'OnDemand', 'xsd:string'],
            ],
        ];
    }

    /** @param array<string, mixed> $device */
    private function pppConnectionPath(array $device, ?int $vlanId = null): string
    {
        $candidates = [];
        $wanDevices = data_get($device, 'InternetGatewayDevice.WANDevice', []);
        if (is_array($wanDevices)) {
            foreach ($wanDevices as $wanIndex => $wanDevice) {
                if (! is_array($wanDevice)) continue;
                $connections = $wanDevice['WANConnectionDevice'] ?? [];
                if (! is_array($connections)) continue;
                foreach ($connections as $connectionIndex => $connection) {
                    if (! is_array($connection) || ! array_key_exists('WANPPPConnection', $connection)) continue;
                    $pppConnections = $connection['WANPPPConnection'];
                    if (! is_array($pppConnections)) continue;
                    foreach ($pppConnections as $pppIndex => $pppConnection) {
                        if (! is_array($pppConnection) || ! is_numeric((string) $wanIndex) || ! is_numeric((string) $connectionIndex) || ! is_numeric((string) $pppIndex)) {
                            continue;
                        }

                        $path = 'InternetGatewayDevice.WANDevice.'.(int) $wanIndex.'.WANConnectionDevice.'.(int) $connectionIndex.'.WANPPPConnection.'.(int) $pppIndex;
                        $candidateVlanId = $this->pppConnectionVlanId($pppConnection);
                        if ($vlanId !== null && $candidateVlanId === $vlanId) {
                            return $path;
                        }

                        $candidates[] = [
                            'path' => $path,
                            'score' => $this->pppConnectionScore($pppConnection),
                            'vlan_match' => $vlanId !== null && $this->pppConnectionNameMatchesVlan($pppConnection, $vlanId),
                        ];
                    }
                }
            }
        }

        if ($candidates !== []) {
            if ($vlanId !== null) {
                foreach ($candidates as $candidate) {
                    if ($candidate['vlan_match']) {
                        return $candidate['path'];
                    }
                }

                throw new RuntimeException("Could not identify the Internet PPPoE WAN connection for VLAN {$vlanId} on this ONT.");
            }

            $selected = $candidates[0];
            foreach ($candidates as $candidate) {
                if ($candidate['score'] > $selected['score']) {
                    $selected = $candidate;
                }
            }

            return $selected['path'];
        }

        if ($vlanId !== null) {
            throw new RuntimeException("The ONT did not report an Internet PPPoE WAN connection for VLAN {$vlanId}.");
        }

        return 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1';
    }

    /** @param array<string, mixed> $connection */
    private function pppConnectionNameMatchesVlan(array $connection, int $vlanId): bool
    {
        $values = [];
        foreach (['Name', 'ConnectionName', 'Description', 'Alias'] as $field) {
            $value = $this->scalarDeviceValue(data_get($connection, $field));
            if (filled($value)) {
                $values[] = strtolower($value);
            }
        }

        $name = implode(' ', $values);
        return $name !== '' && (str_contains($name, 'vid_'.$vlanId) || str_contains($name, 'vlan_'.$vlanId) || preg_match('/(?:^|[^0-9])'.$vlanId.'(?:[^0-9]|$)/', $name) === 1);
    }

    /** @param array<string, mixed> $connection */
    private function pppConnectionVlanId(array $connection): ?int
    {
        foreach (['X_HW_VLAN', 'VLANID', 'VLANId', 'VLAN'] as $field) {
            $vlanId = $this->integerDeviceValue(data_get($connection, $field));
            if ($vlanId !== null) {
                return $vlanId;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $connection */
    private function pppConnectionScore(array $connection): int
    {
        $score = $this->pppConnectionVlanId($connection) !== null ? 100 : 0;
        $username = $this->scalarDeviceValue(data_get($connection, 'Username'));
        $status = strtolower((string) $this->scalarDeviceValue(data_get($connection, 'ConnectionStatus')));
        $ipAddress = $this->scalarDeviceValue(data_get($connection, 'ExternalIPAddress'));

        if (filled($username)) $score += 20;
        if ($status === 'connected') $score += 10;
        if (filled($ipAddress) && ! in_array($ipAddress, ['0.0.0.0', '::'], true)) $score += 10;

        return $score;
    }

    private function postTask(AcsServer $server, string $deviceId, array $task, string $action, ?int $timeout = null): array
    {
        Log::info('ACS task requested', [
            'acs_server_id' => $server->public_id,
            'device_id' => $deviceId,
            'action' => $action,
            'task_name' => $task['name'] ?? null,
            'parameter_names' => collect($task['parameterValues'] ?? [])->map(fn ($parameter) => $parameter[0] ?? null)->filter()->values()->all(),
        ]);
        try {
            $httpTimeout = $timeout === null ? 20 : max(20, (int) ceil($timeout / 1000) + 10);
            $response = Http::timeout($httpTimeout)
                ->withBasicAuth((string) $server->api_username, (string) $server->api_password)
                ->acceptJson()
                ->post($this->taskUrl($server, $deviceId, $timeout), $task);
        } catch (ConnectionException $exception) {
            Log::error('ACS task could not connect', ['acs_server_id' => $server->public_id, 'device_id' => $deviceId, 'action' => $action, 'error' => $exception->getMessage()]);
            throw $exception;
        }

        if ($response->successful()) {
            $httpStatus = $response->status();
            $executionStatus = $httpStatus === 200 ? 'applied' : 'queued';
            $taskResponse = $response->json();

            Log::info('ACS task accepted', [
                'acs_server_id' => $server->public_id,
                'device_id' => $deviceId,
                'action' => $action,
                'status' => $httpStatus,
                'execution_status' => $executionStatus,
                'task_id' => is_array($taskResponse) ? ($taskResponse['_id'] ?? null) : null,
            ]);

            return [
                'device_id' => $deviceId,
                'task' => $taskResponse,
                'task_id' => is_array($taskResponse) ? ($taskResponse['_id'] ?? null) : null,
                'action' => $action,
                'accepted' => true,
                'execution_status' => $executionStatus,
                'http_status' => $httpStatus,
            ];
        }

        Log::warning('ACS task rejected', [
            'acs_server_id' => $server->public_id,
            'device_id' => $deviceId,
            'action' => $action,
            'status' => $response->status(),
            'execution_status' => 'rejected',
        ]);
        $this->throwApiError($response, 'ACS rejected the ONT provisioning task.');
    }

    private function scalarDeviceValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['_value'] ?? $value['value'] ?? null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function booleanDeviceValue(mixed $value, bool $fallback = false): bool
    {
        if (is_array($value)) $value = $value['_value'] ?? $value['value'] ?? null;
        if ($value === null || $value === '') return $fallback;
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }

    private function integerDeviceValue(mixed $value): ?int
    {
        if (is_array($value)) $value = $value['_value'] ?? $value['value'] ?? null;
        return is_numeric($value) ? (int) $value : null;
    }

    private function collectionUrl(AcsServer $server, string $collection): string
    {
        $base = rtrim((string) $server->api_url, '/');
        if (str_ends_with($base, '/devices')) $base = substr($base, 0, -strlen('/devices'));
        return $base.'/'.$collection.'/';
    }

    private function taskUrl(AcsServer $server, string $deviceId, ?int $timeout = null): string
    {
        return $this->collectionUrl($server, 'devices').rawurlencode($deviceId).'/tasks?'.($timeout ? 'timeout='.$timeout.'&' : '').'connection_request';
    }

    private function waitForUploadedFile(AcsServer $server, string $fileName): string
    {
        $url = $this->fileServerUrl($server, $fileName);
        $lastStatus = null;

        for ($attempt = 0; $attempt < 12; $attempt++) {
            try {
                $response = Http::timeout(5)->get($url);
            } catch (ConnectionException $exception) {
                throw new RuntimeException($this->acsConnectionError($server, $exception->getMessage(), 'read the uploaded configuration file'), 0, $exception);
            }
            $lastStatus = $response->status();
            if ($response->successful() && $response->body() !== '') {
                return $response->body();
            }

            usleep(500000);
        }

        throw new RuntimeException('The ONT accepted the configuration export task, but GenieACS did not receive hw_ctree.xml from the device yet. File service response: HTTP '.($lastStatus ?? 'no response').'.');
    }

    private function acsConnectionError(AcsServer $server, string $details, string $operation): string
    {
        $url = rtrim((string) $server->api_url, '/');
        $detail = str_contains(strtolower($details), 'empty reply')
            ? 'GenieACS closed the connection without returning an HTTP response.'
            : 'The connection to GenieACS was interrupted.';

        return "Unable to {$operation}. {$detail} Verify that the GenieACS NBI service is running and reachable at {$url}, then retry.";
    }

    private function refreshUpstreamPort(AcsServer $server, string $deviceId): bool
    {
        $response = Http::timeout(10)
            ->withBasicAuth((string) $server->api_username, (string) $server->api_password)
            ->acceptJson()
            ->post($this->taskUrl($server, $deviceId, 3000), [
                'name' => 'getParameterValues',
                'parameterNames' => ['InternetGatewayDevice.DeviceInfo.X_HW_UpPortMode'],
            ]);

        return $response->status() === 200;
    }

    private function fileServerUrl(AcsServer $server, string $fileName): string
    {
        $configuredPrefix = trim((string) env('GENIEACS_FS_URL_PREFIX', ''));
        if ($configuredPrefix !== '') {
            return rtrim($configuredPrefix, '/').'/'.implode('/', array_map('rawurlencode', explode('/', $fileName)));
        }

        $parts = parse_url((string) $server->api_url);
        if (! is_array($parts) || empty($parts['host'])) {
            throw new RuntimeException('The ACS API URL is invalid, so the GenieACS file service URL cannot be determined.');
        }

        $scheme = $parts['scheme'] ?? 'http';
        $port = isset($parts['port']) ? (int) $parts['port'] + 10 : 7567;
        $origin = $scheme.'://'.$parts['host'].':'.$port;

        return $origin.'/'.implode('/', array_map('rawurlencode', explode('/', $fileName)));
    }

    private function throwApiError($response, string $fallback): never
    {
        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException('ACS API authentication failed. Verify the API username and password.');
        }
        if ($response->status() === 404) {
            throw new RuntimeException('The ACS devices endpoint was not found. Set the API URL to the GenieACS NBI endpoint, usually port 7557.');
        }
        throw new RuntimeException($fallback.' HTTP '.$response->status().'.');
    }

    private function apiProbeUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) throw new RuntimeException('Enter a valid ACS API URL.');
        return trim((string) ($parts['path'] ?? ''), '/') === '' ? rtrim($url, '/').'/devices/' : $url;
    }
}
