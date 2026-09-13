<?php

namespace App\Drivers\Router;

class MikroTikRouterDriver extends UnavailableRouterDriver
{
    public function __construct()
    {
        parent::__construct('mikrotik_router', 'mikrotik');
    }
}
