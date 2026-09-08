<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;

if (! defined('LARAVEL_VERSION')) {
    if (interface_exists(Application::class)) {
        define('LARAVEL_VERSION', Application::VERSION);
    } else {
        define('LARAVEL_VERSION', '10.0');
    }
}
