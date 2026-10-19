<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Laravel\Commands\CheckCommand;
use HarunGecit\EmailValidator\Framework\Laravel\Commands\InstallCommand;
use HarunGecit\EmailValidator\Framework\Laravel\Commands\StatsCommand;
use HarunGecit\EmailValidator\Framework\Laravel\Commands\SyncCommand;
use HarunGecit\EmailValidator\Framework\Laravel\Commands\ValidateCommand;
use HarunGecit\EmailValidator\Framework\Laravel\Rules\EmailRule;
use HarunGecit\EmailValidator\Sync\HttpDownloader;
use HarunGecit\EmailValidator\Sync\ListSynchronizer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Class EmailValidatorServiceProvider
 *
 * Laravel service provider for email validator:
 * - binds Configuration, EmailValidator (alias "email-validator") and ListSynchronizer;
 * - registers the string rules valid_email, not_disposable, not_role_based,
 *   has_mx, not_subaddressed and not_spoofed;
 * - registers the @disposableEmail Blade directive;
 * - registers the email-validator:* Artisan commands (console only);
 * - schedules email-validator:sync when `emailvalidator.sync.schedule` is set.
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel
 */
class EmailValidatorServiceProvider extends ServiceProvider
{
    /**
     * String validation rules => message key in lang/en/validation.php.
     */
    public const STRING_RULES = [
        'valid_email',
        'not_disposable',
        'not_role_based',
        'has_mx',
        'not_subaddressed',
        'not_spoofed',
    ];

    /**
     * Supported `sync.schedule` frequencies (besides cron expressions).
     */
    public const SCHEDULE_FREQUENCIES = ['everyMinute', 'hourly', 'daily', 'weekly', 'monthly'];

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
        $this->app->singleton(Configuration::class, function (Container $app) {
            return static::createConfiguration($app);
        });

        // Register EmailValidator as singleton
        $this->app->singleton(EmailValidator::class, function (Container $app) {
            /** @var Configuration $config */
            $config = $app->make(Configuration::class);
            return new EmailValidator($config);
        });

        // Register alias
        $this->app->alias(EmailValidator::class, 'email-validator');

        // Remote list synchronizer (resolve HttpDownloader from the container so it can be swapped)
        $this->app->bind(ListSynchronizer::class, function (Container $app) {
            /** @var Configuration $config */
            $config = $app->make(Configuration::class);
            /** @var HttpDownloader $downloader */
            $downloader = $app->make(HttpDownloader::class);
            return new ListSynchronizer($config, $downloader);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/lang', EmailRule::TRANSLATION_NAMESPACE);

        // Register validation rules once the validator factory is resolved
        $this->callAfterResolving('validator', function ($factory): void {
            $this->registerValidationRules($factory);
        });

        $this->registerBladeDirectives();

        if ($this->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/emailvalidator.php' => $this->configPath('emailvalidator.php'),
            ], 'emailvalidator-config');

            $this->publishes([
                __DIR__ . '/lang' => $this->langPath('vendor/emailvalidator'),
            ], 'emailvalidator-translations');

            $this->commands([
                InstallCommand::class,
                CheckCommand::class,
                ValidateCommand::class,
                SyncCommand::class,
                StatsCommand::class,
            ]);
        }

        $this->registerSchedule();
    }

    /**
     * Builds the Configuration from config('emailvalidator').
     *
     * @param Container $app
     * @return Configuration
     */
    public static function createConfiguration(Container $app): Configuration
    {
        /** @var array<string, mixed> $configArray */
        $configArray = $app->bound('config') ? (array) $app->make('config')->get('emailvalidator', []) : [];

        // "laravel" is not a core driver: it is mapped to PSR-16 below.
        $useLaravelCache = ($configArray['cache']['driver'] ?? null) === 'laravel';
        if ($useLaravelCache) {
            unset($configArray['cache']['driver']);
        }

        // Typo mappings extend the built-in ones instead of replacing them.
        $typoMappings = (array) ($configArray['typo']['mappings'] ?? []);
        unset($configArray['typo']['mappings']);

        // Config files published before the synced list existed: use the default location.
        if (
            !array_key_exists('synced_blocklist_path', (array) ($configArray['lists'] ?? []))
            && method_exists($app, 'storagePath')
        ) {
            $configArray['lists']['synced_blocklist_path'] = $app->storagePath('app/email-validator/synced-blocklist.txt');
        }

        $config = Configuration::fromArray($configArray);

        foreach ($typoMappings as $typo => $correct) {
            $config->addTypoMapping((string) $typo, (string) $correct);
        }

        // Use Laravel's cache if configured
        if ($useLaravelCache && $app->bound('cache')) {
            /** @var \Illuminate\Contracts\Cache\Repository $cacheStore */
            $cacheStore = $app->make('cache')->store();
            $config->setCacheDriver('psr16');
            $config->setCacheOptions(['cache' => $cacheStore] + (array) ($configArray['cache']['options'] ?? []));
        }

        // Set logger if logging is enabled
        if (($configArray['logging']['enabled'] ?? false) && $app->bound('log')) {
            $logger = $app->make('log');
            $channel = $configArray['logging']['channel'] ?? null;
            if (is_string($channel) && $channel !== '' && method_exists($logger, 'channel')) {
                $logger = $logger->channel($channel);
            }
            /** @var \Psr\Log\LoggerInterface $logger */
            $config->setLogger($logger);
        }

        return $config;
    }

    /**
     * Register custom validation rules.
     *
     * @param \Illuminate\Validation\Factory $validator
     * @return void
     */
    protected function registerValidationRules($validator): void
    {
        $emailValidator = function (): EmailValidator {
            /** @var EmailValidator $instance */
            $instance = $this->app->make(EmailValidator::class);
            return $instance;
        };

        $rules = [
            // valid_email - Full validation
            'valid_email' => fn ($value): bool => is_string($value) && $emailValidator()->isValid($value),
            // not_disposable - Not a disposable email
            'not_disposable' => fn ($value): bool => !$emailValidator()->isDisposable((string) $value),
            // not_role_based - Not a role-based email
            'not_role_based' => fn ($value): bool => !$emailValidator()->isRoleBased((string) $value),
            // has_mx - Has valid MX record
            'has_mx' => fn ($value): bool => $emailValidator()->hasValidMX((string) $value),
            // not_subaddressed - Not a plus-addressed email
            'not_subaddressed' => fn ($value): bool => !$emailValidator()->isSubaddressed((string) $value),
            // not_spoofed - No mixed-script / invisible characters (passes when ext-intl is missing)
            'not_spoofed' => fn ($value): bool => $emailValidator()->isSpoofed((string) $value) !== true,
        ];

        foreach ($rules as $rule => $callback) {
            $validator->extend(
                $rule,
                static fn ($attribute, $value) => is_scalar($value) && $callback($value),
                $this->validationMessage($rule)
            );
        }
    }

    /**
     * Returns the (translated) default message of a string rule.
     *
     * @param string $key
     * @return string
     */
    protected function validationMessage(string $key): string
    {
        $translationKey = EmailRule::TRANSLATION_NAMESPACE . '::validation.' . $key;

        if ($this->app->bound('translator')) {
            $translator = $this->app->make('translator');
            if (method_exists($translator, 'has') && $translator->has($translationKey)) {
                return (string) $translator->get($translationKey);
            }
        }

        return EmailRule::defaultMessage($key);
    }

    /**
     * Registers `@disposableEmail($emailOrDomain) ... @else ... @enddisposableEmail`.
     *
     * @return void
     */
    protected function registerBladeDirectives(): void
    {
        if (!class_exists(\Illuminate\View\Compilers\BladeCompiler::class)) {
            return;
        }

        $this->callAfterResolving('blade.compiler', function ($blade): void {
            /** @var \Illuminate\View\Compilers\BladeCompiler $blade */
            $blade->if('disposableEmail', function ($emailOrDomain): bool {
                if (!is_string($emailOrDomain) || $emailOrDomain === '') {
                    return false;
                }

                /** @var EmailValidator $validator */
                $validator = $this->app->make(EmailValidator::class);

                return strpos($emailOrDomain, '@') !== false
                    ? $validator->isDisposable($emailOrDomain)
                    : $validator->isDisposableDomain($emailOrDomain);
            });
        });
    }

    /**
     * Schedules email-validator:sync when `emailvalidator.sync.schedule` is set.
     *
     * @return void
     */
    protected function registerSchedule(): void
    {
        if (!class_exists(Schedule::class) || !$this->app->bound('config')) {
            return;
        }

        $frequency = $this->app->make('config')->get('emailvalidator.sync.schedule');
        if (!is_string($frequency) || trim($frequency) === '') {
            return;
        }

        $frequency = trim($frequency);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($frequency): void {
            $event = $schedule->command(SyncCommand::class)->withoutOverlapping();

            if (in_array($frequency, self::SCHEDULE_FREQUENCIES, true)) {
                $event->{$frequency}();
            } elseif (count(preg_split('/\s+/', $frequency) ?: []) === 5) {
                $event->cron($frequency);
            } else {
                $event->daily();
            }
        });
    }

    /**
     * Whether the application runs in the console.
     *
     * @return bool
     */
    protected function runningInConsole(): bool
    {
        return method_exists($this->app, 'runningInConsole') && $this->app->runningInConsole();
    }

    /**
     * Path inside the application's config directory.
     *
     * @param string $path
     * @return string
     */
    protected function configPath(string $path): string
    {
        return method_exists($this->app, 'configPath')
            ? $this->app->configPath($path)
            : $path;
    }

    /**
     * Path inside the application's lang directory.
     *
     * @param string $path
     * @return string
     */
    protected function langPath(string $path): string
    {
        if (method_exists($this->app, 'langPath')) {
            // langPath() only accepts a sub-path since Laravel 10.
            return rtrim($this->app->langPath(), '\\/') . DIRECTORY_SEPARATOR . $path;
        }

        return method_exists($this->app, 'resourcePath')
            ? $this->app->resourcePath('lang/' . $path)
            : 'lang/' . $path;
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
            ListSynchronizer::class,
            'email-validator',
        ];
    }
}
