<?php

declare(strict_types=1);

/*
 * Foundation helpers used by config/emailvalidator.php. They are provided by
 * laravel/framework in real applications; the tests only install the
 * illuminate/* components.
 */

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $app = \Illuminate\Container\Container::getInstance();

        return method_exists($app, 'storagePath')
            ? $app->storagePath($path)
            : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $path;
    }
}
