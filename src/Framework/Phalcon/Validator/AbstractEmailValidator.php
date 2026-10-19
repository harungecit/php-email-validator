<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Phalcon\Validator;

use HarunGecit\EmailValidator\EmailValidator;
use Phalcon\Di\DiInterface;
use Phalcon\Filter\Validation;
use Phalcon\Filter\Validation\AbstractValidator;
use Phalcon\Filter\Validation\Exception as ValidationException;
use Phalcon\Messages\Message;

/**
 * Class AbstractEmailValidator
 *
 * Base class of the Phalcon 5 validators. Resolves the {@see EmailValidator}
 * to use, in this order:
 *
 * 1. the `validator` option (an EmailValidator instance);
 * 2. the DI service named by the `service` option (default `emailValidator`,
 *    see {@see \HarunGecit\EmailValidator\Framework\Phalcon\EmailValidatorServiceProvider}),
 *    if the validation has a DI container and the service is registered
 *    (`'service' => false` disables this lookup);
 * 3. a shared default `new EmailValidator()` instance (library defaults).
 *
 * Supported common options: `message`/`template` (string or per-field array),
 * `label`, `code`, `allowEmpty`, `cancelOnFail`.
 *
 * @package HarunGecit\EmailValidator\Framework\Phalcon\Validator
 */
abstract class AbstractEmailValidator extends AbstractValidator
{
    /**
     * Default DI service name of the email validator.
     */
    public const DEFAULT_SERVICE = 'emailValidator';

    /**
     * @var EmailValidator|null Shared fallback instance used when nothing else is configured.
     */
    private static ?EmailValidator $defaultValidator = null;

    /**
     * Returns the email validator used by this rule.
     *
     * @param Validation $validation The validation being run (used for DI lookup).
     * @return EmailValidator
     * @throws ValidationException If the `validator` option or the DI service has an unsupported type.
     */
    public function getEmailValidator(Validation $validation): EmailValidator
    {
        $explicit = $this->getOption('validator');
        if ($explicit !== null) {
            if (!$explicit instanceof EmailValidator) {
                throw new ValidationException(
                    'The "validator" option must be an instance of ' . EmailValidator::class
                );
            }

            return $explicit;
        }

        // `'service' => false` disables the DI lookup.
        $service = $this->getOption('service', self::DEFAULT_SERVICE);
        $container = is_string($service) && $service !== '' ? $this->getContainer($validation) : null;

        if ($container !== null && is_string($service) && $container->has($service)) {
            $resolved = $container->getShared($service);
            if (!$resolved instanceof EmailValidator) {
                throw new ValidationException(sprintf(
                    'The DI service "%s" must be an instance of %s, %s given',
                    (string) $service,
                    EmailValidator::class,
                    get_debug_type($resolved)
                ));
            }

            return $resolved;
        }

        return self::$defaultValidator ??= new EmailValidator();
    }

    /**
     * Returns the DI container of the validation, or null when none is available.
     *
     * @param Validation $validation The validation being run.
     * @return DiInterface|null
     */
    protected function getContainer(Validation $validation): ?DiInterface
    {
        try {
            return $validation->getDI();
        } catch (\Throwable $e) {
            // No container set and no default container (Phalcon\Di\Exception\ContainerRequired).
            return null;
        }
    }

    /**
     * Appends an error message for the field built from the given template.
     *
     * Same output as {@see AbstractValidator::messageFactory()} (`:field`
     * replaced by the label, `code` option honoured), but with an explicit
     * template.
     *
     * @param Validation $validation The validation being run.
     * @param string $field The field name.
     * @param string $template The message template.
     * @param array<string, string> $replacements Additional placeholder replacements.
     * @return void
     */
    protected function appendError(Validation $validation, string $field, string $template, array $replacements = []): void
    {
        $replacements = array_merge([':field' => (string) $this->prepareLabel($validation, $field)], $replacements);

        $validation->appendMessage(new Message(
            strtr($template, $replacements),
            $field,
            static::class,
            $this->prepareCode($field)
        ));
    }

    /**
     * Whether the user configured an explicit template for the field
     * (`message`/`template` option, or a global default message registered
     * with `Validation::setDefaultMessages()` on Phalcon versions supporting it).
     *
     * @param string $field The field name.
     * @return bool
     */
    protected function hasCustomTemplate(string $field): bool
    {
        if (isset($this->getTemplates()[$field]) || is_string($this->getOption('message'))) {
            return true;
        }

        // setTemplate() called after construction (flag available in later Phalcon 5 releases).
        if (property_exists($this, 'templateChanged') && $this->templateChanged === true) {
            return true;
        }

        // Validation::getDefaultMessage() is a static method added in later Phalcon 5 releases.
        if (!method_exists(Validation::class, 'getDefaultMessage')) {
            return false;
        }

        $method = new \ReflectionMethod(Validation::class, 'getDefaultMessage');

        return $method->isStatic() && $method->invoke(null, static::class) !== '';
    }
}
