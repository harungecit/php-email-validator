<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\Validator;

use Attribute;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Class NotRoleBasedEmail
 *
 * Rejects role-based (non-personal) addresses such as admin@, info@ or
 * support@, using the configured `role_based.prefixes`.
 *
 * ```php
 * #[NotRoleBasedEmail]
 * public string $email;
 * ```
 *
 * Message parameters: `{{ value }}`.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Validator
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class NotRoleBasedEmail extends Constraint
{
    public const ROLE_BASED_EMAIL_ERROR = '9a3b7e51-0c2d-4f6a-8e1b-5d4c3b2a1f09';

    protected const ERROR_NAMES = [
        self::ROLE_BASED_EMAIL_ERROR => 'ROLE_BASED_EMAIL_ERROR',
    ];

    public string $message = 'Role-based email addresses (such as admin@ or info@) are not allowed.';

    /**
     * @param string|null $message Custom violation message.
     * @param array<string>|null $groups Validation groups.
     * @param mixed $payload Domain-specific data attached to the constraint.
     */
    #[HasNamedArguments]
    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
