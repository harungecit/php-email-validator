<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\CakePHP;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use Psr\Log\LoggerInterface;

/**
 * Class EmailValidatorFactory
 *
 * Builds the EmailValidator used by the CakePHP integration from the
 * `EmailValidator` Configure key (same structure as Configuration::fromArray()),
 * and holds the shared instance used by both the DI container
 * (EmailValidatorPlugin) and EmailValidationProvider.
 *
 * CakePHP-specific settings on top of Configuration::fromArray():
 *
 * - `cache.driver = 'cakephp'` uses a CakePHP cache config as PSR-16 cache
 *   (`cache.options.config`, default `'default'`); requires cakephp/cache.
 * - `logger` may hold a PSR-3 logger instance.
 *
 * @package HarunGecit\EmailValidator\Framework\CakePHP
 */
final class EmailValidatorFactory
{
    /**
     * Configure key holding the settings.
     */
    public const CONFIGURE_KEY = 'EmailValidator';

    /**
     * @var EmailValidator|null Shared instance.
     */
    private static ?EmailValidator $instance = null;

    /**
     * Builds a Configuration.
     *
     * @param array<string, mixed>|null $settings Settings; null reads `Configure::read('EmailValidator')`.
     * @return Configuration
     */
    public static function configuration(?array $settings = null): Configuration
    {
        if ($settings === null) {
            $settings = class_exists(Configure::class)
                ? (array) Configure::read(self::CONFIGURE_KEY, [])
                : [];
        }

        $cakeCache = null;
        if (isset($settings['cache']['driver']) && $settings['cache']['driver'] === 'cakephp') {
            $cakeCache = (string) ($settings['cache']['options']['config'] ?? 'default');
            unset($settings['cache']['driver'], $settings['cache']['options']);
        }

        $config = Configuration::fromArray($settings);

        if ($cakeCache !== null) {
            $config->setCacheDriver('psr16');
            $config->setCacheOptions(['cache' => Cache::pool($cakeCache)]);
        }

        if (isset($settings['logger']) && $settings['logger'] instanceof LoggerInterface) {
            $config->setLogger($settings['logger']);
        }

        return $config;
    }

    /**
     * Creates a new EmailValidator.
     *
     * @param array<string, mixed>|null $settings Settings; null reads `Configure::read('EmailValidator')`.
     * @return EmailValidator
     */
    public static function create(?array $settings = null): EmailValidator
    {
        return new EmailValidator(self::configuration($settings));
    }

    /**
     * Returns the shared EmailValidator, creating it from Configure on first use.
     *
     * @return EmailValidator
     */
    public static function getInstance(): EmailValidator
    {
        if (self::$instance === null) {
            self::$instance = self::create();
        }

        return self::$instance;
    }

    /**
     * Replaces the shared EmailValidator (null resets it, e.g. in tests or
     * after changing the Configure settings).
     *
     * @param EmailValidator|null $validator
     * @return void
     */
    public static function setInstance(?EmailValidator $validator): void
    {
        self::$instance = $validator;
    }
}
