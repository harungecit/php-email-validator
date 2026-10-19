<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\CodeIgniter;

use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Exceptions\RateLimitExceededException;
use Throwable;

/**
 * Class EmailValidatorRules
 *
 * CodeIgniter 4 validation rule set. It is registered automatically through
 * the `HarunGecit\EmailValidator\Config\Registrar` discovery hook, or can be
 * added by hand to `Config\Validation::$ruleSets`.
 *
 * ```php
 * $rules = [
 *     'email' => 'required|valid_email|not_disposable_email|not_role_based_email',
 * ];
 * ```
 *
 * Available rules:
 *
 * - `not_disposable_email`   The domain (or a parent domain) is not on the disposable blocklist.
 * - `email_has_mx`           The domain has MX records (performs a DNS lookup).
 * - `not_role_based_email`   The local part is not a role account (admin@, info@, ...).
 * - `not_subaddressed_email` The address has no "+tag" sub-address.
 * - `not_spoofed_email`      No mixed-script / invisible characters (passes when ext-intl is missing).
 * - `verified_email`         The full pipeline configured for the `emailValidator` service.
 *
 * Error messages: CodeIgniter uses an error message set by a rule verbatim
 * and ahead of any custom message, so placeholders such as `{field}` are not
 * available there. When the application defines a `Validation.<rule>` language
 * line (e.g. in `app/Language/en/Validation.php`), the rule leaves the message
 * to CodeIgniter instead, so the language line, `{field}`/`{value}`
 * placeholders and per-field custom messages from `setRules()` all apply.
 * Otherwise the English default messages below are used.
 *
 * Note: CodeIgniter instantiates rule sets with `new $class()` and treats every
 * method name as a rule name, so this class keeps its public surface to the
 * rules themselves.
 *
 * @package HarunGecit\EmailValidator\Framework\CodeIgniter
 */
class EmailValidatorRules
{
    /**
     * Default (English) error messages, keyed by rule name.
     *
     * @var array<string, string>
     */
    public const MESSAGES = [
        'not_disposable_email' => 'Disposable (temporary) email addresses are not allowed.',
        'email_has_mx' => 'The email domain cannot receive mail (no MX record found).',
        'not_role_based_email' => 'Role-based email addresses (such as info@ or admin@) are not allowed.',
        'not_subaddressed_email' => 'Email addresses with a "+" tag are not allowed.',
        'not_spoofed_email' => 'The email address contains mixed-script or invisible characters.',
        'verified_email' => 'The email address is not valid.',
    ];

    /**
     * @var EmailValidator|null Validator injected through the constructor.
     */
    private ?EmailValidator $validator;

    /**
     * @var EmailValidator|null Lazily created fallback when no CodeIgniter service is available.
     */
    private static ?EmailValidator $fallbackValidator = null;

    /**
     * EmailValidatorRules constructor.
     *
     * @param EmailValidator|null $validator Validator to use; defaults to `service('emailValidator')`
     *                                       or a default instance when CodeIgniter is not available.
     */
    public function __construct(?EmailValidator $validator = null)
    {
        $this->validator = $validator;
    }

    /**
     * Fails when the email address belongs to a disposable email provider.
     *
     * @param mixed       $str   The field value.
     * @param string|null $error Error message set on failure.
     * @return bool
     */
    public function not_disposable_email($str = null, ?string &$error = null): bool
    {
        if (!is_string($str) || $this->resolveValidator()->isDisposable($str)) {
            return $this->reject('not_disposable_email', $error);
        }

        return true;
    }

    /**
     * Fails when the email domain has no MX records (performs a DNS lookup).
     *
     * @param mixed       $str   The field value.
     * @param string|null $error Error message set on failure.
     * @return bool
     */
    public function email_has_mx($str = null, ?string &$error = null): bool
    {
        if (!is_string($str) || !$this->resolveValidator()->hasValidMX($str)) {
            return $this->reject('email_has_mx', $error);
        }

        return true;
    }

    /**
     * Fails when the email address is role-based (admin@, info@, support@, ...).
     *
     * @param mixed       $str   The field value.
     * @param string|null $error Error message set on failure.
     * @return bool
     */
    public function not_role_based_email($str = null, ?string &$error = null): bool
    {
        if (!is_string($str) || $this->resolveValidator()->isRoleBased($str)) {
            return $this->reject('not_role_based_email', $error);
        }

        return true;
    }

    /**
     * Fails when the email address uses sub-addressing (user+tag@example.com).
     *
     * @param mixed       $str   The field value.
     * @param string|null $error Error message set on failure.
     * @return bool
     */
    public function not_subaddressed_email($str = null, ?string &$error = null): bool
    {
        if (!is_string($str) || $this->resolveValidator()->isSubaddressed($str)) {
            return $this->reject('not_subaddressed_email', $error);
        }

        return true;
    }

    /**
     * Fails when the email address mixes scripts or contains invisible
     * characters. Passes when ext-intl is not installed (the check cannot run).
     *
     * @param mixed       $str   The field value.
     * @param string|null $error Error message set on failure.
     * @return bool
     */
    public function not_spoofed_email($str = null, ?string &$error = null): bool
    {
        if (!is_string($str) || $this->resolveValidator()->isSpoofed($str) === true) {
            return $this->reject('not_spoofed_email', $error);
        }

        return true;
    }

    /**
     * Runs the full validation pipeline configured for the validator (format,
     * disposable, MX, ... as enabled in the configuration). On failure the
     * default message includes the first failed check.
     *
     * @param mixed       $str   The field value.
     * @param string|null $error Error message set on failure.
     * @return bool
     */
    public function verified_email($str = null, ?string &$error = null): bool
    {
        if (!is_string($str)) {
            return $this->reject('verified_email', $error);
        }

        try {
            $result = $this->resolveValidator()->validate($str);
        } catch (RateLimitExceededException $e) {
            return $this->reject('verified_email', $error, 'Too many email validation attempts. Please try again later.');
        }

        if ($result->isValid()) {
            return true;
        }

        $reason = $result->getFirstError();

        return $this->reject(
            'verified_email',
            $error,
            $reason === null ? null : sprintf('The email address is not valid: %s.', $reason)
        );
    }

    /**
     * Sets the error message for a failed rule and returns false.
     *
     * The message is left to CodeIgniter when the application defines a
     * `Validation.<rule>` language line (see the class description).
     *
     * @param string      $rule    The rule name.
     * @param string|null $error   Error message reference.
     * @param string|null $message Message to use instead of the default one.
     * @return bool Always false.
     */
    private function reject(string $rule, ?string &$error, ?string $message = null): bool
    {
        if (!$this->hasLanguageLine($rule)) {
            $error = $message ?? self::MESSAGES[$rule];
        }

        return false;
    }

    /**
     * Whether the application defines a `Validation.<rule>` language line.
     *
     * @param string $rule The rule name.
     * @return bool
     */
    private function hasLanguageLine(string $rule): bool
    {
        if (!function_exists('lang')) {
            return false;
        }

        $key = 'Validation.' . $rule;

        try {
            return lang($key) !== $key;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Resolves the EmailValidator: the injected instance, the shared
     * `emailValidator` CodeIgniter service, or a lazily created default.
     *
     * @return EmailValidator
     */
    private function resolveValidator(): EmailValidator
    {
        if ($this->validator !== null) {
            return $this->validator;
        }

        if (function_exists('service')) {
            try {
                $service = service('emailValidator');
            } catch (Throwable $e) {
                $service = null;
            }

            if ($service instanceof EmailValidator) {
                return $this->validator = $service;
            }
        }

        if (self::$fallbackValidator === null) {
            self::$fallbackValidator = new EmailValidator();
        }

        return $this->validator = self::$fallbackValidator;
    }
}
