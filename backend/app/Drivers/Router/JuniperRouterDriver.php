<?php

namespace App\Drivers\Router;

class JuniperRouterDriver extends UnavailableRouterDriver
{
    public function __construct()
    {
        parent::__construct('juniper_router', 'juniper');
    }
}
