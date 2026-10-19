<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\Validator;

use HarunGecit\EmailValidator\EmailValidator;
use Stringable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Class ValidEmailValidator
 *
 * Validates the ValidEmail constraint with the configured EmailValidator
 * pipeline. Null and empty strings are ignored: combine with NotBlank to
 * require a value.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Validator
 */
class ValidEmailValidator extends ConstraintValidator
{
    private EmailValidator $emailValidator;

    /**
     * @param EmailValidator|null $emailValidator The configured validator service.
     */
    public function __construct(?EmailValidator $emailValidator = null)
    {
        $this->emailValidator = $emailValidator ?? new EmailValidator();
    }

    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidEmail) {
            throw new UnexpectedTypeException($constraint, ValidEmail::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_scalar($value) && !$value instanceof Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;
        $config = $this->emailValidator->getConfiguration();
        $mxEnabled = $config->isMxCheckEnabled();

        if ($constraint->checkMx !== null) {
            $config->enableMxCheck($constraint->checkMx);
        }

        try {
            $result = $this->emailValidator->validate($value);
        } finally {
            $config->enableMxCheck($mxEnabled);
        }

        if ($result->isValid()) {
            return;
        }

        $failedCheck = null;
        foreach ($result->getChecks() as $check => $passed) {
            if (!$passed) {
                $failedCheck = $check;
                break;
            }
        }

        $suggestion = $result->getSuggestion();

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->setParameter('{{ check }}', (string) $failedCheck)
            ->setParameter('{{ reason }}', (string) ($result->getFirstError() ?? 'invalid'))
            ->setParameter('{{ suggestion }}', $suggestion !== null ? $suggestion->getSuggestedEmail() : '')
            ->setCode(ValidEmail::CHECK_CODES[(string) $failedCheck] ?? ValidEmail::INVALID_ERROR)
            ->addViolation();
    }
}
