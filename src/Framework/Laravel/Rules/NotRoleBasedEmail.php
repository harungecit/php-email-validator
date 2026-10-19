<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel\Rules;

use Closure;

/**
 * Class NotRoleBasedEmail
 *
 * Fails for role-based (non-personal) addresses such as admin@, info@ or
 * support@, using the configured `emailvalidator.role_based.prefixes`.
 *
 * ```php
 * 'email' => ['required', 'email', new NotRoleBasedEmail()],
 * ```
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel\Rules
 */
class NotRoleBasedEmail extends EmailRule
{
    /**
     * {@inheritdoc}
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || $value === '') {
            return;
        }

        if ($this->emailValidator()->isRoleBased($value)) {
            $this->fail($fail, 'not_role_based');
        }
    }
}
