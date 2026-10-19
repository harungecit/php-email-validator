<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\CakePHP;

use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;

/**
 * Class EmailValidatorPlugin
 *
 * CakePHP 4.4+/5.x plugin that registers EmailValidator and its
 * Configuration in the application's DI container, configured from
 * `Configure::read('EmailValidator')` (see EmailValidatorFactory).
 *
 * ```php
 * // src/Application.php
 * public function bootstrap(): void
 * {
 *     parent::bootstrap();
 *     $this->addPlugin(\HarunGecit\EmailValidator\Framework\CakePHP\EmailValidatorPlugin::class);
 * }
 * ```
 *
 * Only the `services` hook is enabled; the plugin has no bootstrap file,
 * routes, middleware, console commands or event listeners. Definitions the
 * application registers itself in `Application::services()` take precedence,
 * because the application registers its services before the plugins' and the
 * container resolves the first definition registered for an id.
 *
 * @package HarunGecit\EmailValidator\Framework\CakePHP
 */
class EmailValidatorPlugin extends BasePlugin
{
    /**
     * EmailValidatorPlugin constructor.
     *
     * The unused hooks are disabled through the options rather than by
     * redeclaring the `*Enabled` properties, whose types differ between
     * CakePHP 4 (untyped) and 5 (bool).
     *
     * @param array<string, mixed> $options Plugin options (hooks, name, paths).
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options + [
            'name' => 'EmailValidator',
            'bootstrap' => false,
            'routes' => false,
            'middleware' => false,
            'console' => false,
            'events' => false,
        ]);
    }

    /**
     * Registers EmailValidator and Configuration as shared services.
     *
     * By default both resolve to the shared EmailValidatorFactory instance
     * (also used by EmailValidationProvider). When the application registers
     * its own Configuration, the container's EmailValidator is built from it.
     *
     * @param ContainerInterface $container The application container.
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(Configuration::class, static function (): Configuration {
            return EmailValidatorFactory::getInstance()->getConfiguration();
        });

        $container->addShared(EmailValidator::class, static function () use ($container): EmailValidator {
            $shared = EmailValidatorFactory::getInstance();
            /** @var Configuration $config */
            $config = $container->get(Configuration::class);

            return $config === $shared->getConfiguration() ? $shared : new EmailValidator($config);
        });
    }
}
