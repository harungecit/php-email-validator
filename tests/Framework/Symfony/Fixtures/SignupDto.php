<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Symfony\Fixtures;

use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotDisposableEmail;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotRoleBasedEmail;

/**
 * Fixture for constraint attribute mapping.
 */
class SignupDto
{
    #[NotDisposableEmail]
    public string $email = '';

    #[NotRoleBasedEmail]
    public string $contact = '';
}
