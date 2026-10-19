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
 * Class NotDisposableEmailValidator
 *
 * Validates the NotDisposableEmail constraint. Registered as a service by the
 * bundle (with the configured EmailValidator injected); without a container
 * it falls back to an EmailValidator with the default configuration.
 *
 * Null and empty strings are ignored: combine with NotBlank to require a value.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Validator
 */
class NotDisposableEmailValidator extends ConstraintValidator
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
        if (!$constraint instanceof NotDisposableEmail) {
            throw new UnexpectedTypeException($constraint, NotDisposableEmail::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_scalar($value) && !$value instanceof Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;
        if (strpos($value, '@') === false) {
            return;
        }

        $check = $this->emailValidator->checkDisposable($value);
        if (!$check->isDisposable()) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->setParameter('{{ domain }}', (string) $check->getDomain())
            ->setParameter('{{ matched }}', (string) $check->getMatchedDomain())
            ->setCode(NotDisposableEmail::DISPOSABLE_EMAIL_ERROR)
            ->addViolation();
    }
}
