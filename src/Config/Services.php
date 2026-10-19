<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Config;

use CodeIgniter\Config\BaseService;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\CodeIgniter\Config\EmailValidator as EmailValidatorConfig;

/**
 * Class Services
 *
 * CodeIgniter 4 auto-discovery hook: CodeIgniter looks for
 * `Config/Services.php` in every PSR-4 namespace root (including Composer
 * packages) and exposes its static methods as services, so
 * `service('emailValidator')` works without any application code.
 *
 * It extends a CodeIgniter class and is only loaded by CodeIgniter; nothing in
 * the library references it.
 *
 * @package HarunGecit\EmailValidator\Config
 */
class Services extends BaseService
{
    /**
     * The EmailValidator service, configured from `app/Config/EmailValidator.php`
     * when the application defines it (extending
     * HarunGecit\EmailValidator\Framework\CodeIgniter\Config\EmailValidator),
     * otherwise from the defaults.
     *
     * @param Configuration|null $config    Explicit configuration (overrides the config file).
     * @param bool               $getShared Return the shared instance.
     * @return EmailValidator
     */
    public static function emailValidator(?Configuration $config = null, bool $getShared = true): EmailValidator
    {
        if ($getShared) {
            return static::getSharedInstance('emailValidator', $config);
        }

        return new EmailValidator($config ?? EmailValidatorConfig::resolve()->toConfiguration());
    }
}
