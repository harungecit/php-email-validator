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
 * Class NotRoleBasedEmailValidator
 *
 * Validates the NotRoleBasedEmail constraint. Null and empty strings are ignored.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Validator
 */
class NotRoleBasedEmailValidator extends ConstraintValidator
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
        if (!$constraint instanceof NotRoleBasedEmail) {
            throw new UnexpectedTypeException($constraint, NotRoleBasedEmail::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_scalar($value) && !$value instanceof Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;
        if (!$this->emailValidator->isRoleBased($value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->setCode(NotRoleBasedEmail::ROLE_BASED_EMAIL_ERROR)
            ->addViolation();
    }
}
