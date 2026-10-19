<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Yii3;

use HarunGecit\EmailValidator\EmailValidator;
use Yiisoft\Validator\Exception\UnexpectedRuleException;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\RuleHandlerInterface;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\ValidationContext;

/**
 * Class NotDisposableEmailHandler
 *
 * Handler of the {@see NotDisposableEmail} rule. The {@see EmailValidator} is
 * injected by the DI container (autowiring) when one is configured; otherwise
 * a default instance is created on first use.
 *
 * @package HarunGecit\EmailValidator\Framework\Yii3
 */
final class NotDisposableEmailHandler implements RuleHandlerInterface
{
    /**
     * @param EmailValidator|null $emailValidator The validator to use; null for a lazily created default instance.
     */
    public function __construct(private ?EmailValidator $emailValidator = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, RuleInterface $rule, ValidationContext $context): Result
    {
        if (!$rule instanceof NotDisposableEmail) {
            throw new UnexpectedRuleException(NotDisposableEmail::class, $rule);
        }

        $result = new Result();

        if (!is_string($value)) {
            return $result->addError($rule->getIncorrectInputMessage(), [
                'property' => $context->getTranslatedProperty(),
                'Property' => $context->getCapitalizedTranslatedProperty(),
                'type' => get_debug_type($value),
            ]);
        }

        if (strpos($value, '@') === false) {
            return $result;
        }

        $check = $this->getEmailValidator()->checkDisposable($value);

        if ($check->isDisposable()) {
            $result->addError($rule->getMessage(), [
                'property' => $context->getTranslatedProperty(),
                'Property' => $context->getCapitalizedTranslatedProperty(),
                'value' => $value,
                'domain' => (string) $check->getDomain(),
                'matched' => (string) $check->getMatchedDomain(),
            ]);
        }

        return $result;
    }

    /**
     * Returns the email validator, creating a default instance if none was injected.
     *
     * @return EmailValidator
     */
    private function getEmailValidator(): EmailValidator
    {
        return $this->emailValidator ??= new EmailValidator();
    }
}
