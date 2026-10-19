<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Config;

use HarunGecit\EmailValidator\Framework\CodeIgniter\EmailValidatorRules;

/**
 * Class Registrar
 *
 * CodeIgniter 4 auto-discovery hook (not used outside CodeIgniter).
 *
 * CodeIgniter looks for `Config/Registrar.php` in every PSR-4 namespace root,
 * including Composer packages, and calls the static method named after a
 * config class's short name whenever that config class is instantiated.
 * Array values are merged into the config property with array_merge(), so
 * `Validation()` appends our rule set to `Config\Validation::$ruleSets`
 * without the application touching its own config.
 *
 * Discovery requires `registrars` in `Config\Modules::$aliases` and
 * `Config\Modules::$discoverInComposer = true` (both are CodeIgniter defaults).
 *
 * This class references no CodeIgniter classes, so it is safe to autoload anywhere.
 *
 * @package HarunGecit\EmailValidator\Config
 */
class Registrar
{
    /**
     * Additions to `Config\Validation` (method name must match the config class name).
     *
     * @return array<string, array<class-string>>
     */
    public static function Validation(): array // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        return [
            'ruleSets' => [EmailValidatorRules::class],
        ];
    }
}
