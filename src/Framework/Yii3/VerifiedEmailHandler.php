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
 * Class VerifiedEmailHandler
 *
 * Handler of the {@see VerifiedEmail} rule. The {@see EmailValidator} is
 * injected by the DI container (autowiring) when one is configured; otherwise
 * a default instance (library defaults: format, MX and disposable checks) is
 * created on first use.
 *
 * @package HarunGecit\EmailValidator\Framework\Yii3
 */
final class VerifiedEmailHandler implements RuleHandlerInterface
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
        if (!$rule instanceof VerifiedEmail) {
            throw new UnexpectedRuleException(VerifiedEmail::class, $rule);
        }

        $result = new Result();

        if (!is_string($value)) {
            return $result->addError($rule->getIncorrectInputMessage(), [
                'property' => $context->getTranslatedProperty(),
                'Property' => $context->getCapitalizedTranslatedProperty(),
                'type' => get_debug_type($value),
            ]);
        }

        $validation = $this->getEmailValidator()->validate($value);

        if ($validation->isValid()) {
            return $result;
        }

        $failedCheck = (string) array_search(false, $validation->getChecks(), true);
        $suggestion = $validation->getSuggestion();

        return $result->addError($rule->getMessageFor($failedCheck), [
            'property' => $context->getTranslatedProperty(),
            'Property' => $context->getCapitalizedTranslatedProperty(),
            'value' => $value,
            'check' => $failedCheck,
            'error' => (string) $validation->getFirstError(),
            'suggestion' => $suggestion !== null ? $suggestion->getSuggestedEmail() : '',
        ]);
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
