<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\Validator;

use Attribute;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Class ValidEmail
 *
 * Runs the full validation pipeline configured for the bundle (format,
 * disposable, MX, role-based, spoof, SMTP, catch-all — whichever checks are
 * enabled in `email_validator.checks`) and reports the first failing check.
 *
 * ```php
 * #[ValidEmail]
 * public string $email;
 *
 * #[ValidEmail(checkMx: false, message: 'Invalid email: {{ reason }}')]
 * public string $newsletterEmail;
 * ```
 *
 * Message parameters: `{{ value }}`, `{{ check }}` (name of the failing check,
 * e.g. "disposable"), `{{ reason }}` (the validator's error message) and
 * `{{ suggestion }}` (typo suggestion such as "user@gmail.com", or empty).
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Validator
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ValidEmail extends Constraint
{
    public const INVALID_FORMAT_ERROR = 'b2f0c6a4-3e1d-4c8b-9a7f-6d5e4c3b2a10';
    public const DISPOSABLE_ERROR = 'c3a1d7b5-4f2e-4d9c-8b6a-7e6f5d4c3b21';
    public const MX_ERROR = 'd4b2e8c6-5a3f-4ead-9c7b-8f7a6e5d4c32';
    public const ROLE_BASED_ERROR = 'e5c3f9d7-6b4a-4fbe-8d8c-9a8b7f6e5d43';
    public const SPOOF_ERROR = 'f6d4a0e8-7c5b-40cf-9e9d-0b9c8a7f6e54';
    public const SMTP_ERROR = '07e5b1f9-8d6c-41d0-8f0e-1c0d9b8a7f65';
    public const CATCH_ALL_ERROR = '18f6c2a0-9e7d-42e1-9a1f-2d1e0c9b8a76';
    public const INVALID_ERROR = '29a7d3b1-0f8e-43f2-8b2a-3e2f1d0c9b87';

    protected const ERROR_NAMES = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
        self::DISPOSABLE_ERROR => 'DISPOSABLE_ERROR',
        self::MX_ERROR => 'MX_ERROR',
        self::ROLE_BASED_ERROR => 'ROLE_BASED_ERROR',
        self::SPOOF_ERROR => 'SPOOF_ERROR',
        self::SMTP_ERROR => 'SMTP_ERROR',
        self::CATCH_ALL_ERROR => 'CATCH_ALL_ERROR',
        self::INVALID_ERROR => 'INVALID_ERROR',
    ];

    /**
     * Violation codes by check name (as reported by ValidationResult::getChecks()).
     */
    public const CHECK_CODES = [
        'format' => self::INVALID_FORMAT_ERROR,
        'disposable' => self::DISPOSABLE_ERROR,
        'mx' => self::MX_ERROR,
        'role_based' => self::ROLE_BASED_ERROR,
        'spoof' => self::SPOOF_ERROR,
        'smtp' => self::SMTP_ERROR,
        'catch_all' => self::CATCH_ALL_ERROR,
    ];

    public string $message = 'This value is not a valid email address ({{ reason }}).';

    /**
     * Overrides `checks.mx` for this constraint; null keeps the configured value.
     */
    public ?bool $checkMx = null;

    /**
     * @param string|null $message Custom violation message.
     * @param bool|null $checkMx Override the configured MX check (null = use configuration).
     * @param array<string>|null $groups Validation groups.
     * @param mixed $payload Domain-specific data attached to the constraint.
     */
    #[HasNamedArguments]
    public function __construct(
        ?string $message = null,
        ?bool $checkMx = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->checkMx = $checkMx;
    }
}
