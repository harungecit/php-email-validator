<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\CakePHP;

use Cake\Validation\Validator;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Exceptions\RateLimitExceededException;
use InvalidArgumentException;

/**
 * Class EmailValidationProvider
 *
 * CakePHP 4.4+/5.x validation provider.
 *
 * ```php
 * use HarunGecit\EmailValidator\Framework\CakePHP\EmailValidationProvider;
 *
 * // One call: registers the provider and adds the rules with default messages
 * EmailValidationProvider::attach($validator, 'email', ['notDisposable', 'notRoleBased']);
 *
 * // Or by hand
 * $validator
 *     ->setProvider('emailValidator', new EmailValidationProvider())
 *     ->add('email', 'notDisposable', [
 *         'rule' => 'notDisposable',
 *         'provider' => 'emailValidator',
 *         'message' => 'Please use a permanent email address.',
 *     ]);
 * ```
 *
 * Rules: notDisposable, notRoleBased, notSubaddressed, notSpoofed, hasMx and
 * verifiedEmail (the full pipeline configured for the validator). Non-string
 * values fail every rule.
 *
 * Rule methods take `(mixed $value, array $context = [])`, which works with
 * both the CakePHP 4 (positional context) and 5 (named `context`) calling
 * conventions.
 *
 * @package HarunGecit\EmailValidator\Framework\CakePHP
 */
class EmailValidationProvider
{
    /**
     * Default provider name used by attach().
     */
    public const NAME = 'emailValidator';

    /**
     * Available rules and their default (English) error messages.
     *
     * @var array<string, string>
     */
    public const MESSAGES = [
        'notDisposable' => 'Disposable (temporary) email addresses are not allowed.',
        'notRoleBased' => 'Role-based email addresses (such as info@ or admin@) are not allowed.',
        'notSubaddressed' => 'Email addresses with a "+" tag are not allowed.',
        'notSpoofed' => 'The email address contains mixed-script or invisible characters.',
        'hasMx' => 'The email domain cannot receive mail (no MX record found).',
        'verifiedEmail' => 'The email address is not valid.',
    ];

    /**
     * @var EmailValidator|null The validator, resolved lazily when not injected.
     */
    private ?EmailValidator $validator;

    /**
     * EmailValidationProvider constructor.
     *
     * @param EmailValidator|null $validator Validator to use; defaults to the shared
     *                                       EmailValidatorFactory instance (Configure-based).
     */
    public function __construct(?EmailValidator $validator = null)
    {
        $this->validator = $validator;
    }

    /**
     * Registers the provider on a validator (unless one is already registered
     * under the same name) and adds the given rules to a field.
     *
     * ```php
     * EmailValidationProvider::attach($validator, 'email', [
     *     'notDisposable',                                  // default message
     *     'notRoleBased' => 'Please use a personal address.', // custom message
     * ], ['on' => 'create']);
     * ```
     *
     * @param Validator                      $validator The CakePHP validator.
     * @param string                         $field     The field to validate.
     * @param array<int|string, string|null> $rules     Rule names, or rule name => custom message.
     * @param array<string, mixed>           $options   Extra rule options (e.g. `on`, `last`).
     * @param EmailValidationProvider|null   $provider  Provider instance to register (replaces an existing one).
     * @return Validator
     * @throws InvalidArgumentException When a rule name is unknown.
     */
    public static function attach(
        Validator $validator,
        string $field = 'email',
        array $rules = ['notDisposable'],
        array $options = [],
        ?EmailValidationProvider $provider = null
    ): Validator {
        if ($provider !== null || $validator->getProvider(self::NAME) === null) {
            $validator->setProvider(self::NAME, $provider ?? new self());
        }

        foreach ($rules as $key => $value) {
            $rule = is_string($key) ? $key : (string) $value;
            $message = is_string($key) && is_string($value) ? $value : null;

            if (!isset(self::MESSAGES[$rule])) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown email validation rule "%s". Available rules: %s.',
                    $rule,
                    implode(', ', array_keys(self::MESSAGES))
                ));
            }

            $validator->add($field, $rule, array_merge($options, [
                'rule' => $rule,
                'provider' => self::NAME,
                'message' => $message ?? self::MESSAGES[$rule],
            ]));
        }

        return $validator;
    }

    /**
     * Fails when the email address belongs to a disposable email provider.
     *
     * @param mixed                $value   The field value.
     * @param array<string, mixed> $context The validation context.
     * @return bool
     */
    public function notDisposable(mixed $value, array $context = []): bool
    {
        return is_string($value) && !$this->getValidator()->isDisposable($value);
    }

    /**
     * Fails when the email address is role-based (admin@, info@, support@, ...).
     *
     * @param mixed                $value   The field value.
     * @param array<string, mixed> $context The validation context.
     * @return bool
     */
    public function notRoleBased(mixed $value, array $context = []): bool
    {
        return is_string($value) && !$this->getValidator()->isRoleBased($value);
    }

    /**
     * Fails when the email address uses sub-addressing (user+tag@example.com).
     *
     * @param mixed                $value   The field value.
     * @param array<string, mixed> $context The validation context.
     * @return bool
     */
    public function notSubaddressed(mixed $value, array $context = []): bool
    {
        return is_string($value) && !$this->getValidator()->isSubaddressed($value);
    }

    /**
     * Fails when the email address mixes scripts or contains invisible
     * characters. Passes when ext-intl is not installed (the check cannot run).
     *
     * @param mixed                $value   The field value.
     * @param array<string, mixed> $context The validation context.
     * @return bool
     */
    public function notSpoofed(mixed $value, array $context = []): bool
    {
        return is_string($value) && $this->getValidator()->isSpoofed($value) !== true;
    }

    /**
     * Fails when the email domain has no MX records (performs a DNS lookup).
     *
     * @param mixed                $value   The field value.
     * @param array<string, mixed> $context The validation context.
     * @return bool
     */
    public function hasMx(mixed $value, array $context = []): bool
    {
        return is_string($value) && $this->getValidator()->hasValidMX($value);
    }

    /**
     * Runs the full validation pipeline configured for the validator. Fails
     * (instead of throwing) when the rate limit is exceeded.
     *
     * @param mixed                $value   The field value.
     * @param array<string, mixed> $context The validation context.
     * @return bool
     */
    public function verifiedEmail(mixed $value, array $context = []): bool
    {
        if (!is_string($value)) {
            return false;
        }

        try {
            return $this->getValidator()->validate($value)->isValid();
        } catch (RateLimitExceededException $e) {
            return false;
        }
    }

    /**
     * Returns the EmailValidator used by the rules.
     *
     * @return EmailValidator
     */
    public function getValidator(): EmailValidator
    {
        if ($this->validator === null) {
            $this->validator = EmailValidatorFactory::getInstance();
        }

        return $this->validator;
    }
}
