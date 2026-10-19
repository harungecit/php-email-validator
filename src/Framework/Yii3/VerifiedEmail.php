<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Yii3;

use Attribute;
use Closure;
use Yiisoft\Validator\DumpedRuleInterface;
use Yiisoft\Validator\Rule\Trait\SkipOnEmptyTrait;
use Yiisoft\Validator\Rule\Trait\SkipOnErrorTrait;
use Yiisoft\Validator\Rule\Trait\WhenTrait;
use Yiisoft\Validator\SkipOnEmptyInterface;
use Yiisoft\Validator\SkipOnErrorInterface;
use Yiisoft\Validator\WhenInterface;

/**
 * Class VerifiedEmail
 *
 * Yii3 (yiisoft/validator 2.x) rule running the full
 * {@see \HarunGecit\EmailValidator\EmailValidator::validate()} pipeline
 * (format, disposable, MX, role-based, SMTP, ... as enabled in the
 * injected validator's configuration).
 *
 * ```php
 * final class SignupForm
 * {
 *     #[Required]
 *     #[VerifiedEmail(messages: ['disposable' => 'Please use a permanent email address.'])]
 *     public string $email = '';
 * }
 * ```
 *
 * The error message is chosen by the first failed check: `$messages` (keyed
 * by check name: format, spoof, disposable, mx, role_based, smtp, catch_all)
 * falls back to `$message`.
 *
 * @see VerifiedEmailHandler
 *
 * @psalm-import-type SkipOnEmptyValue from SkipOnEmptyInterface
 * @psalm-import-type WhenType from WhenInterface
 *
 * @package HarunGecit\EmailValidator\Framework\Yii3
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class VerifiedEmail implements DumpedRuleInterface, SkipOnErrorInterface, WhenInterface, SkipOnEmptyInterface
{
    use SkipOnEmptyTrait;
    use SkipOnErrorTrait;
    use WhenTrait;

    /**
     * Default messages per failed check.
     *
     * @var array<string, string>
     */
    public const DEFAULT_MESSAGES = [
        'format' => '{Property} is not a valid email address.',
        'spoof' => '{Property} contains suspicious characters.',
        'disposable' => '{Property} must not be a disposable email address.',
        'mx' => 'The domain of {property} cannot receive email.',
        'role_based' => '{Property} must not be a role-based email address.',
        'smtp' => 'The mailbox of {property} could not be verified.',
        'catch_all' => '{Property} must not use a catch-all domain.',
    ];

    /**
     * @var array<string, string> Messages per failed check (defaults merged with the custom ones).
     */
    private array $messages;

    /**
     * @param string $incorrectInputMessage Message used when the value is not a string.
     * Placeholders: `{property}`, `{Property}`, `{type}`.
     * @param string $message Fallback message used when no check-specific message exists.
     * @param array<string, string> $messages Messages keyed by failed check name, merged over {@see DEFAULT_MESSAGES}.
     * Placeholders for both: `{property}`, `{Property}`, `{value}`, `{check}` (failed check name),
     * `{error}` (the library's first error message), `{suggestion}` (typo suggestion or empty string).
     * @param bool|callable|null $skipOnEmpty Whether to skip this rule if the value is empty. See {@see SkipOnEmptyInterface}.
     * @param bool $skipOnError Whether to skip this rule if a previous rule failed. See {@see SkipOnErrorInterface}.
     * @param Closure|null $when Condition for applying the rule. See {@see WhenInterface}.
     *
     * @psalm-param SkipOnEmptyValue $skipOnEmpty
     * @psalm-param WhenType $when
     */
    public function __construct(
        private string $incorrectInputMessage = '{Property} must be a string. {type} given.',
        private string $message = '{Property} is not a valid email address.',
        array $messages = [],
        bool|callable|null $skipOnEmpty = null,
        private bool $skipOnError = false,
        private ?Closure $when = null,
    ) {
        $this->messages = array_merge(self::DEFAULT_MESSAGES, $messages);
        $this->skipOnEmpty = $skipOnEmpty;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::class;
    }

    /**
     * Gets the message used when the value is not a string.
     *
     * @return string
     */
    public function getIncorrectInputMessage(): string
    {
        return $this->incorrectInputMessage;
    }

    /**
     * Gets the fallback message used when no check-specific message exists.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Gets the messages keyed by check name.
     *
     * @return array<string, string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Gets the message for a failed check, falling back to {@see getMessage()}.
     *
     * @param string $check The failed check name.
     * @return string
     */
    public function getMessageFor(string $check): string
    {
        return $this->messages[$check] ?? $this->message;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        $messages = [];
        foreach ($this->messages as $check => $template) {
            $messages[$check] = [
                'template' => $template,
                'parameters' => [],
            ];
        }

        return [
            'incorrectInputMessage' => [
                'template' => $this->incorrectInputMessage,
                'parameters' => [],
            ],
            'message' => [
                'template' => $this->message,
                'parameters' => [],
            ],
            'messages' => $messages,
            'skipOnEmpty' => $this->getSkipOnEmptyOption(),
            'skipOnError' => $this->skipOnError,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getHandler(): string
    {
        return VerifiedEmailHandler::class;
    }
}
