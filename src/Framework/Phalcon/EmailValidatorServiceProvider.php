<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Phalcon;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;

/**
 * Class EmailValidatorServiceProvider
 *
 * Phalcon 5 service provider registering a shared `emailValidator` service.
 *
 * ```php
 * use HarunGecit\EmailValidator\Framework\Phalcon\EmailValidatorServiceProvider;
 *
 * // Configuration from the constructor ...
 * $di->register(new EmailValidatorServiceProvider([
 *     'checks' => ['mx' => true, 'role_based' => true],
 * ]));
 *
 * // ... or from the "emailValidator" section of the "config" service.
 * $di->register(new EmailValidatorServiceProvider());
 *
 * $di->getShared('emailValidator')->isDisposable('user@mailinator.com'); // true
 * ```
 *
 * Configuration resolution (first match wins): the `$config` constructor
 * argument; the `$configKey` section of the DI `config` service (a
 * `Phalcon\Config\Config`, any object with `toArray()`, or an array);
 * the library defaults.
 *
 * @package HarunGecit\EmailValidator\Framework\Phalcon
 */
class EmailValidatorServiceProvider implements ServiceProviderInterface
{
    /**
     * @param array<string, mixed>|Configuration|null $config Validator configuration
     *        ({@see Configuration::fromArray()} format); null to read it from the DI `config` service.
     * @param string $serviceName Name of the registered service.
     * @param string $configKey Section of the DI `config` service holding the configuration.
     */
    public function __construct(
        private array|Configuration|null $config = null,
        private string $serviceName = 'emailValidator',
        private string $configKey = 'emailValidator'
    ) {
    }

    /**
     * Registers the shared email validator service.
     *
     * @param DiInterface $di The DI container.
     * @return void
     */
    public function register(DiInterface $di): void
    {
        $provider = $this;

        // Not static: Phalcon binds service closures to the container.
        $di->setShared($this->serviceName, function () use ($provider, $di): EmailValidator {
            return new EmailValidator($provider->resolveConfiguration($di));
        });
    }

    /**
     * Resolves the validator configuration.
     *
     * @param DiInterface $di The DI container.
     * @return Configuration
     */
    public function resolveConfiguration(DiInterface $di): Configuration
    {
        if ($this->config instanceof Configuration) {
            return $this->config;
        }

        if ($this->config !== null) {
            return Configuration::fromArray($this->config);
        }

        return Configuration::fromArray($this->readConfigSection($di));
    }

    /**
     * Reads the configuration section from the DI `config` service.
     *
     * @param DiInterface $di The DI container.
     * @return array<string, mixed>
     */
    protected function readConfigSection(DiInterface $di): array
    {
        if (!$di->has('config')) {
            return [];
        }

        $config = $di->getShared('config');

        if (is_array($config)) {
            $section = $config[$this->configKey] ?? null;
        } elseif (is_object($config) && method_exists($config, 'get')) {
            $section = $config->get($this->configKey);
        } elseif (is_object($config)) {
            $section = $config->{$this->configKey} ?? null;
        } else {
            $section = null;
        }

        if (is_object($section) && method_exists($section, 'toArray')) {
            $section = $section->toArray();
        }

        return is_array($section) ? $section : [];
    }
}
