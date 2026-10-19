<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Yii2;

use HarunGecit\EmailValidator\EmailValidator;
use Yii;

/**
 * Class NotRoleBasedEmailValidator
 *
 * Yii2 validator rejecting role-based addresses (admin@, info@, support@, ...).
 * The prefix list comes from the resolved validator's configuration
 * (`role_based.prefixes`).
 *
 * ```php
 * ['email', NotRoleBasedEmailValidator::class],
 * ```
 *
 * Message placeholders: `{attribute}`, `{value}`.
 *
 * @package HarunGecit\EmailValidator\Framework\Yii2
 */
class NotRoleBasedEmailValidator extends AbstractEmailValidator
{
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if ($this->message === null) {
            $this->message = Yii::t('app', '{attribute} must not be a role-based email address.');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validateEmail(string $email, EmailValidator $validator): ?array
    {
        return $validator->isRoleBased($email) ? [(string) $this->message, []] : null;
    }
}
