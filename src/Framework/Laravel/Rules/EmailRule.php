<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel\Rules;

use Closure;
use HarunGecit\EmailValidator\EmailValidator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

/**
 * Class EmailRule
 *
 * Base class of the email validator rule objects (Laravel 10+). Resolves the
 * configured EmailValidator from the container and produces translatable
 * messages from the `emailvalidator::validation.*` translation group, falling
 * back to the bundled English messages.
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel\Rules
 */
abstract class EmailRule implements ValidationRule, ValidatorAwareRule
{
    /**
     * Translation namespace registered by the service provider.
     */
    public const TRANSLATION_NAMESPACE = 'emailvalidator';

    /** @var array<string, string>|null */
    private static ?array $defaultMessages = null;

    /**
     * The validator instance running the rule.
     */
    protected ?Validator $validator = null;

    /**
     * @param string|null $message Custom message or translation key (supports :attribute).
     */
    public function __construct(protected ?string $message = null)
    {
    }

    /**
     * Sets the current validator.
     *
     * @param Validator $validator
     * @return $this
     */
    public function setValidator(Validator $validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Uses a custom message (or translation key) for failures.
     *
     * @param string $message
     * @return static
     */
    public function withMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Resolves the configured EmailValidator from the container.
     *
     * @return EmailValidator
     */
    protected function emailValidator(): EmailValidator
    {
        /** @var EmailValidator $validator */
        $validator = Container::getInstance()->make(EmailValidator::class);

        return $validator;
    }

    /**
     * Reports a failure with the custom message, the translated message or the
     * bundled English default, in that order.
     *
     * @param Closure $fail The failure callback passed to validate().
     * @param string $key Message key in the emailvalidator::validation group.
     * @param array<string, string> $replace Extra replacements (without the leading colon).
     * @return void
     */
    protected function fail(Closure $fail, string $key, array $replace = []): void
    {
        if ($this->message !== null) {
            $fail($this->message)->translate($replace);
            return;
        }

        $translationKey = self::TRANSLATION_NAMESPACE . '::validation.' . $key;
        $translator = $this->validator?->getTranslator();

        if ($translator !== null && method_exists($translator, 'has') && $translator->has($translationKey)) {
            $fail($translationKey)->translate($replace);
            return;
        }

        $message = self::defaultMessage($key);
        foreach ($replace as $search => $value) {
            $message = str_replace(':' . $search, $value, $message);
        }

        $fail($message);
    }

    /**
     * Returns the bundled English message for a key.
     *
     * @param string $key
     * @return string
     */
    public static function defaultMessage(string $key): string
    {
        if (self::$defaultMessages === null) {
            /** @var array<string, string> $messages */
            $messages = require dirname(__DIR__) . '/lang/en/validation.php';
            self::$defaultMessages = $messages;
        }

        return self::$defaultMessages[$key] ?? self::$defaultMessages['valid_email'];
    }
}
