<?php

namespace App\Drivers\Router;

class CiscoRouterDriver extends UnavailableRouterDriver
{
    public function __construct()
    {
        parent::__construct('cisco_router', 'cisco');
    }
}
