<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Yii2;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Result\DisposableCheckResult;
use HarunGecit\EmailValidator\Result\SuggestionResult;
use HarunGecit\EmailValidator\Result\ValidationResult;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Class EmailValidatorComponent
 *
 * Yii2 application component wrapping a lazily created {@see EmailValidator}.
 *
 * ```php
 * // config/web.php (or console.php)
 * 'components' => [
 *     'emailValidator' => [
 *         'class' => \HarunGecit\EmailValidator\Framework\Yii2\EmailValidatorComponent::class,
 *         'config' => [
 *             'checks' => ['mx' => true, 'role_based' => true],
 *             'lists' => ['custom_blocklist' => ['spam.example']],
 *         ],
 *     ],
 * ],
 *
 * Yii::$app->emailValidator->isDisposable('user@mailinator.com'); // true
 * ```
 *
 * The explicitly declared methods are proxied to the wrapped validator; any
 * other public {@see EmailValidator} method is proxied through `__call()`
 * (methods provided by attached behaviors take precedence).
 *
 * @property EmailValidator $validator The wrapped validator (created on first access).
 *
 * @method bool hasValidDNS(string $email)
 * @method string|null getBaseEmail(string $email)
 * @method bool isCatchAll(string $email)
 * @method bool validateSMTP(string $email)
 * @method array validateBatch(array $emails)
 * @method array validateMultiple(array $emails, bool $checkMX = true)
 * @method array validateWithDetails(string $email, bool $checkMX = true)
 * @method array filterValid(array $emails, bool $checkMX = true)
 * @method array filterInvalid(array $emails, bool $checkMX = true)
 * @method array getStatistics(array $emails, bool $checkMX = true)
 * @method string|null extractDomain(string $email)
 * @method string|null extractLocalPart(string $email)
 * @method string normalize(string $email)
 * @method bool isAllowlisted(string $email)
 * @method bool isBlocklisted(string $email)
 * @method EmailValidator addToBlocklist(string $domain)
 * @method EmailValidator addToAllowlist(string $domain)
 * @method EmailValidator removeFromBlocklist(string $domain)
 * @method EmailValidator removeFromAllowlist(string $domain)
 * @method EmailValidator addRoleBasedPrefix(string $prefix)
 *
 * @package HarunGecit\EmailValidator\Framework\Yii2
 */
class EmailValidatorComponent extends Component
{
    /**
     * Validator configuration: an array in {@see Configuration::fromArray()} format,
     * a {@see Configuration} instance, or null for the library defaults.
     *
     * @var array<string, mixed>|Configuration|null
     */
    public $config = [];

    /**
     * @var EmailValidator|null The wrapped validator instance.
     */
    private ?EmailValidator $emailValidator = null;

    /**
     * Returns the wrapped validator, creating it from {@see $config} on first use.
     *
     * @return EmailValidator
     * @throws InvalidConfigException If {@see $config} has an unsupported type.
     */
    public function getValidator(): EmailValidator
    {
        if ($this->emailValidator === null) {
            $this->emailValidator = new EmailValidator($this->createConfiguration());
        }

        return $this->emailValidator;
    }

    /**
     * Replaces the wrapped validator (e.g. with a pre-configured or mocked instance).
     *
     * @param EmailValidator $validator The validator to use.
     * @return void
     */
    public function setValidator(EmailValidator $validator): void
    {
        $this->emailValidator = $validator;
    }

    /**
     * Builds the configuration from {@see $config}.
     *
     * @return Configuration
     * @throws InvalidConfigException If {@see $config} has an unsupported type.
     */
    protected function createConfiguration(): Configuration
    {
        if ($this->config instanceof Configuration) {
            return $this->config;
        }

        if ($this->config === null) {
            return new Configuration();
        }

        if (!is_array($this->config)) {
            throw new InvalidConfigException(
                'EmailValidatorComponent::$config must be an array, a ' . Configuration::class . ' instance or null.'
            );
        }

        return Configuration::fromArray($this->resolvePathAliases($this->config));
    }

    /**
     * Resolves Yii path aliases (e.g. "@runtime/email-validator/synced.txt")
     * in the file path settings of the `lists` section.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    protected function resolvePathAliases(array $config): array
    {
        if (!isset($config['lists']) || !is_array($config['lists'])) {
            return $config;
        }

        $resolve = static function ($path) {
            return is_string($path) && strncmp($path, '@', 1) === 0 ? (string) \Yii::getAlias($path) : $path;
        };

        foreach (['blocklist_path', 'allowlist_path', 'role_based_path', 'synced_blocklist_path'] as $key) {
            if (isset($config['lists'][$key])) {
                $config['lists'][$key] = $resolve($config['lists'][$key]);
            }
        }

        foreach (['custom_blocklist_paths', 'custom_allowlist_paths'] as $key) {
            if (isset($config['lists'][$key])) {
                $config['lists'][$key] = array_map($resolve, (array) $config['lists'][$key]);
            }
        }

        return $config;
    }

    // ==================== Proxied methods ====================

    /**
     * Runs the full validation pipeline.
     *
     * @param string $email The email address to validate.
     * @return ValidationResult
     */
    public function validate(string $email): ValidationResult
    {
        return $this->getValidator()->validate($email);
    }

    /**
     * Checks whether the email passes all enabled checks.
     *
     * @param string $email The email address to validate.
     * @param bool|null $checkMX Override the MX check setting for this call.
     * @return bool
     */
    public function isValid(string $email, ?bool $checkMX = null): bool
    {
        return $this->getValidator()->isValid($email, $checkMX);
    }

    /**
     * Checks the email format only.
     *
     * @param string $email The email address to check.
     * @return bool
     */
    public function isValidFormat(string $email): bool
    {
        return $this->getValidator()->isValidFormat($email);
    }

    /**
     * Checks whether the email belongs to a disposable provider.
     *
     * @param string $email The email address to check.
     * @return bool
     */
    public function isDisposable(string $email): bool
    {
        return $this->getValidator()->isDisposable($email);
    }

    /**
     * Checks whether a bare domain is disposable.
     *
     * @param string $domain The domain to check.
     * @return bool
     */
    public function isDisposableDomain(string $domain): bool
    {
        return $this->getValidator()->isDisposableDomain($domain);
    }

    /**
     * Explains a disposable-domain lookup (matched entry, list source, allowlist hit).
     *
     * @param string $emailOrDomain An email address or a bare domain.
     * @return DisposableCheckResult
     */
    public function checkDisposable(string $emailOrDomain): DisposableCheckResult
    {
        return $this->getValidator()->checkDisposable($emailOrDomain);
    }

    /**
     * Checks whether the email domain has valid MX records.
     *
     * @param string $email The email address to check.
     * @return bool
     */
    public function hasValidMX(string $email): bool
    {
        return $this->getValidator()->hasValidMX($email);
    }

    /**
     * Checks whether the email is role-based (admin@, info@, ...).
     *
     * @param string $email The email address to check.
     * @return bool
     */
    public function isRoleBased(string $email): bool
    {
        return $this->getValidator()->isRoleBased($email);
    }

    /**
     * Checks whether the email uses plus addressing (user+tag@...).
     *
     * @param string $email The email address to check.
     * @return bool
     */
    public function isSubaddressed(string $email): bool
    {
        return $this->getValidator()->isSubaddressed($email);
    }

    /**
     * Detects mixed-script / invisible-character spoofing.
     *
     * @param string $email The email address to check.
     * @return bool|null Null when ext-intl is unavailable.
     */
    public function isSpoofed(string $email): ?bool
    {
        return $this->getValidator()->isSpoofed($email);
    }

    /**
     * Returns a typo suggestion for the email domain, if any.
     *
     * @param string $email The email address to check.
     * @return SuggestionResult|null
     */
    public function getSuggestion(string $email): ?SuggestionResult
    {
        return $this->getValidator()->getSuggestion($email);
    }

    /**
     * Returns statistics about the loaded block/allow lists.
     *
     * @return array<string, mixed>
     */
    public function getListStatistics(): array
    {
        return $this->getValidator()->getListStatistics();
    }

    /**
     * Proxies unknown method calls to the wrapped {@see EmailValidator}.
     *
     * Methods provided by attached behaviors keep precedence, and unknown
     * methods still raise {@see \yii\base\UnknownMethodException}.
     *
     * @param string $name The method name.
     * @param array<int|string, mixed> $params The method parameters.
     * @return mixed
     */
    public function __call($name, $params)
    {
        if (!parent::hasMethod($name)) {
            $validator = $this->getValidator();
            if (is_callable([$validator, $name])) {
                return $validator->$name(...$params);
            }
        }

        return parent::__call($name, $params);
    }

    /**
     * {@inheritdoc}
     *
     * Also reports the public methods of the wrapped {@see EmailValidator}.
     */
    public function hasMethod($name, $checkBehaviors = true)
    {
        if (parent::hasMethod($name, $checkBehaviors)) {
            return true;
        }

        if (!method_exists(EmailValidator::class, $name)) {
            return false;
        }

        $method = new \ReflectionMethod(EmailValidator::class, $name);

        return $method->isPublic() && !$method->isStatic();
    }
}
