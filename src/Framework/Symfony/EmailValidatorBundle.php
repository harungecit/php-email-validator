<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use HarunGecit\EmailValidator\Framework\Symfony\DependencyInjection\EmailValidatorExtension;

/**
 * Class EmailValidatorBundle
 *
 * Symfony bundle for email validator.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony
 */
class EmailValidatorBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): EmailValidatorExtension
    {
        return new EmailValidatorExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return \dirname(__DIR__, 2);
    }
}
