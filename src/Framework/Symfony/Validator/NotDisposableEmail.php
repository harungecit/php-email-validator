<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\Validator;

use Attribute;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Class NotDisposableEmail
 *
 * Rejects email addresses whose domain (or one of its parent domains) is a
 * known disposable / temporary email provider. Uses the bundled blocklist plus
 * the configured custom and synced lists, and honours the allowlist.
 *
 * ```php
 * #[NotDisposableEmail]
 * public string $email;
 *
 * #[NotDisposableEmail(message: 'Please use a permanent email address.')]
 * public string $contactEmail;
 * ```
 *
 * Message parameters: `{{ value }}`, `{{ domain }}` (the email's domain) and
 * `{{ matched }}` (the list entry that matched).
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Validator
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class NotDisposableEmail extends Constraint
{
    public const DISPOSABLE_EMAIL_ERROR = '4d1f8c2e-6b0a-4f8e-9c51-2a7d3e9b8f10';

    protected const ERROR_NAMES = [
        self::DISPOSABLE_EMAIL_ERROR => 'DISPOSABLE_EMAIL_ERROR',
    ];

    public string $message = 'Disposable email addresses are not allowed.';

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
