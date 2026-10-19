<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Yii2;

use HarunGecit\EmailValidator\EmailValidator;
use Yii;
use yii\base\InvalidConfigException;
use yii\validators\Validator;

/**
 * Class AbstractEmailValidator
 *
 * Base class of the Yii2 model validators. Resolves the {@see EmailValidator}
 * to use, in this order:
 *
 * 1. the {@see $validator} property (an EmailValidator or EmailValidatorComponent instance);
 * 2. the application component named by {@see $component} (default `emailValidator`),
 *    if it is registered;
 * 3. a shared default `new EmailValidator()` instance (library defaults).
 *
 * Non-string values fail with {@see $typeMessage}.
 *
 * @package HarunGecit\EmailValidator\Framework\Yii2
 */
abstract class AbstractEmailValidator extends Validator
{
    /**
     * @var string|null ID of the application component providing the validator. Null disables the lookup.
     */
    public $component = 'emailValidator';

    /**
     * @var EmailValidator|EmailValidatorComponent|null Explicit validator instance (takes precedence over {@see $component}).
     */
    public $validator;

    /**
     * @var string|null Error message used when the value is not a string. Supports `{attribute}` and `{value}`.
     */
    public $typeMessage;

    /**
     * @var EmailValidator|null Shared fallback instance used when nothing else is configured.
     */
    private static ?EmailValidator $defaultValidator = null;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if ($this->typeMessage === null) {
            $this->typeMessage = Yii::t('app', '{attribute} must be a string.');
        }
    }

    /**
     * Returns the email validator used by this rule.
     *
     * @return EmailValidator
     * @throws InvalidConfigException If {@see $validator} or the application component has an unsupported type.
     */
    public function getEmailValidator(): EmailValidator
    {
        if ($this->validator !== null) {
            return self::unwrap($this->validator, 'validator');
        }

        if ($this->component !== null && $this->component !== '' && Yii::$app !== null && Yii::$app->has($this->component)) {
            return self::unwrap(Yii::$app->get($this->component), 'component "' . $this->component . '"');
        }

        return self::$defaultValidator ??= new EmailValidator();
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $value The value to validate.
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    protected function validateValue($value)
    {
        if (!is_string($value)) {
            return [(string) $this->typeMessage, []];
        }

        return $this->validateEmail($value, $this->getEmailValidator());
    }

    /**
     * Validates a string value.
     *
     * @param string $email The value to validate.
     * @param EmailValidator $validator The resolved email validator.
     * @return array{0: string, 1: array<string, mixed>}|null Null when valid, otherwise [message, params].
     */
    abstract protected function validateEmail(string $email, EmailValidator $validator): ?array;

    /**
     * Extracts an EmailValidator from a configured value.
     *
     * @param mixed $candidate The configured value.
     * @param string $origin Description of the value's origin (for the error message).
     * @return EmailValidator
     * @throws InvalidConfigException If the value has an unsupported type.
     */
    private static function unwrap(mixed $candidate, string $origin): EmailValidator
    {
        if ($candidate instanceof EmailValidator) {
            return $candidate;
        }

        if ($candidate instanceof EmailValidatorComponent) {
            return $candidate->getValidator();
        }

        throw new InvalidConfigException(sprintf(
            'The email validator %s must be an instance of %s or %s, %s given.',
            $origin,
            EmailValidator::class,
            EmailValidatorComponent::class,
            get_debug_type($candidate)
        ));
    }
}
