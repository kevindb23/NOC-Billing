<?php
namespace App\Services;

use App\Models\Router;
use RuntimeException;
class RouterSessionService
{
    public function start(Router $router): array { return $this->send($this->payload($router, 'start')); }
    public function stop(Router $router): array { return $this->send(['action' => 'stop', 'entity' => 'router', 'entity_id' => $router->public_id]); }
    public function status(Router $router): array { return $this->send(['action' => 'status', 'entity' => 'router', 'entity_id' => $router->public_id]); }
    private function payload(Router $router, string $action): array
    {
        if ($router->preferred_transport !== 'ssh') throw new RuntimeException('Persistent sessions currently support SSH only.');
        if (blank($router->ssh_username) || blank($router->ssh_password)) throw new RuntimeException('Save SSH credentials on the router before connecting.');
        $parts = parse_url(str_contains((string) $router->management_endpoint, '://') ? $router->management_endpoint : 'tcp://'.$router->management_endpoint);
        if (!is_array($parts) || empty($parts['host'])) throw new RuntimeException('Enter a valid router management endpoint.');
        return ['action'=>$action,'entity'=>'router','entity_id'=>$router->public_id,'device_type'=>$this->deviceType($router),'host'=>$parts['host'],'port'=>(int)($parts['port'] ?? 22),'username'=>$router->ssh_username,'password'=>$router->ssh_password];
    }
    private function deviceType(Router $router): string { return strtolower($router->vendor)==='juniper' && strtolower((string)$router->model)==='mx204' ? 'juniper_junos' : throw new RuntimeException('No persistent Netmiko driver is registered for this router model.'); }
    private function send(array $payload): array
    {
        $socket=(string)config('router.router_session_socket'); $connection=@stream_socket_client("unix://{$socket}",$errno,$error,2); if(!$connection) throw new RuntimeException('The router session service is not running.'); fwrite($connection,json_encode($payload,JSON_THROW_ON_ERROR)); stream_set_timeout($connection,10); $response=json_decode((string)fgets($connection),true); fclose($connection); if(!is_array($response)||!($response['ok']??false)) throw new RuntimeException((string)($response['message']??'The router session service failed.')); return $response;
    }
}
