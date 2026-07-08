<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Cache\Psr16CacheAdapter;

/**
 * Class EmailValidatorServiceProvider
 *
 * Laravel service provider for email validator.
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel
 */
class EmailValidatorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/emailvalidator.php',
            'emailvalidator'
        );

        // Register Configuration as singleton
        $this->app->singleton(Configuration::class, function (Application $app) {
            /** @var array<string, mixed> $configArray */
            $configArray = $app['config']['emailvalidator'] ?? [];
            $config = Configuration::fromArray($configArray);

            // Use Laravel's cache if configured
            if (($configArray['cache']['driver'] ?? null) === 'laravel') {
                $config->setCacheDriver('psr16');
                /** @var \Illuminate\Contracts\Cache\Repository $cacheStore */
                $cacheStore = $app['cache']->store();
                $config->setCacheOptions(['cache' => $cacheStore]);
            }

            // Set logger if logging is enabled
            if ($configArray['logging']['enabled'] ?? false) {
                /** @var \Psr\Log\LoggerInterface $logger */
                $logger = $app['log'];
                $config->setLogger($logger);
            }

            return $config;
        });

        // Register EmailValidator as singleton
        $this->app->singleton(EmailValidator::class, function (Application $app) {
            /** @var Configuration $config */
            $config = $app->make(Configuration::class);
            return new EmailValidator($config);
        });

        // Register alias
        $this->app->alias(EmailValidator::class, 'email-validator');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/config/emailvalidator.php' => $this->app->configPath('emailvalidator.php'),
        ], 'emailvalidator-config');

        // Register validation rules
        $this->registerValidationRules();
    }

    /**
     * Register custom validation rules.
     *
     * @return void
     */
    protected function registerValidationRules(): void
    {
        /** @var \Illuminate\Validation\Factory $validator */
        $validator = $this->app['validator'];

        // valid_email - Full validation
        $validator->extend('valid_email', function ($attribute, $value, $parameters, $validator) {
            /** @var EmailValidator $emailValidator */
            $emailValidator = $this->app->make(EmailValidator::class);
            return $emailValidator->isValid((string) $value);
        }, 'The :attribute must be a valid email address.');

        // not_disposable - Not a disposable email
        $validator->extend('not_disposable', function ($attribute, $value, $parameters, $validator) {
            /** @var EmailValidator $emailValidator */
            $emailValidator = $this->app->make(EmailValidator::class);
            return !$emailValidator->isDisposable((string) $value);
        }, 'The :attribute must not be a disposable email address.');

        // not_role_based - Not a role-based email
        $validator->extend('not_role_based', function ($attribute, $value, $parameters, $validator) {
            /** @var EmailValidator $emailValidator */
            $emailValidator = $this->app->make(EmailValidator::class);
            return !$emailValidator->isRoleBased((string) $value);
        }, 'The :attribute must not be a role-based email address.');

        // has_mx - Has valid MX record
        $validator->extend('has_mx', function ($attribute, $value, $parameters, $validator) {
            /** @var EmailValidator $emailValidator */
            $emailValidator = $this->app->make(EmailValidator::class);
            return $emailValidator->hasValidMX((string) $value);
        }, 'The :attribute domain must have valid MX records.');

        // not_subaddressed - Not a plus-addressed email
        $validator->extend('not_subaddressed', function ($attribute, $value, $parameters, $validator) {
            /** @var EmailValidator $emailValidator */
            $emailValidator = $this->app->make(EmailValidator::class);
            return !$emailValidator->isSubaddressed((string) $value);
        }, 'The :attribute must not contain a plus sign (+).');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            EmailValidator::class,
            Configuration::class,
            'email-validator',
        ];
    }
}
