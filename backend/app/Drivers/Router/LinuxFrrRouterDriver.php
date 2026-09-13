<?php

namespace App\Drivers\Router;

class LinuxFrrRouterDriver extends UnavailableRouterDriver
{
    public function __construct()
    {
        parent::__construct('linux_frr_router', 'linux_frr');
    }
}
