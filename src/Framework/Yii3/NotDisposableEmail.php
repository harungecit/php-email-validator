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
 * Class NotDisposableEmail
 *
 * Yii3 (yiisoft/validator 2.x) rule: the value must not be an email address
 * of a disposable provider (subdomains of listed providers included, the
 * allowlist honoured).
 *
 * ```php
 * final class SignupForm
 * {
 *     #[Required]
 *     #[Email]
 *     #[NotDisposableEmail]
 *     public string $email = '';
 * }
 * ```
 *
 * Values without an "@" are not checked; combine with the built-in `Email`
 * rule for format validation.
 *
 * @see NotDisposableEmailHandler
 *
 * @psalm-import-type SkipOnEmptyValue from SkipOnEmptyInterface
 * @psalm-import-type WhenType from WhenInterface
 *
 * @package HarunGecit\EmailValidator\Framework\Yii3
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class NotDisposableEmail implements DumpedRuleInterface, SkipOnErrorInterface, WhenInterface, SkipOnEmptyInterface
{
    use SkipOnEmptyTrait;
    use SkipOnErrorTrait;
    use WhenTrait;

    /**
     * @param string $incorrectInputMessage Message used when the value is not a string.
     * Placeholders: `{property}`, `{Property}`, `{type}`.
     * @param string $message Message used when the email is disposable.
     * Placeholders: `{property}`, `{Property}`, `{value}`, `{domain}` (domain of the value),
     * `{matched}` (the blocklist entry that matched).
     * @param bool|callable|null $skipOnEmpty Whether to skip this rule if the value is empty. See {@see SkipOnEmptyInterface}.
     * @param bool $skipOnError Whether to skip this rule if a previous rule failed. See {@see SkipOnErrorInterface}.
     * @param Closure|null $when Condition for applying the rule. See {@see WhenInterface}.
     *
     * @psalm-param SkipOnEmptyValue $skipOnEmpty
     * @psalm-param WhenType $when
     */
    public function __construct(
        private string $incorrectInputMessage = '{Property} must be a string. {type} given.',
        private string $message = '{Property} must not be a disposable email address.',
        bool|callable|null $skipOnEmpty = null,
        private bool $skipOnError = false,
        private ?Closure $when = null,
    ) {
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
     * Gets the message used when the email is disposable.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return [
            'incorrectInputMessage' => [
                'template' => $this->incorrectInputMessage,
                'parameters' => [],
            ],
            'message' => [
                'template' => $this->message,
                'parameters' => [],
            ],
            'skipOnEmpty' => $this->getSkipOnEmptyOption(),
            'skipOnError' => $this->skipOnError,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getHandler(): string
    {
        return NotDisposableEmailHandler::class;
    }
}
