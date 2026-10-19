<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Symfony;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotDisposableEmail;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotDisposableEmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotRoleBasedEmail;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotRoleBasedEmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\ValidEmail;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\ValidEmailValidator;
use HarunGecit\EmailValidator\Tests\Framework\Symfony\Fixtures\SignupDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ConstraintsTest extends TestCase
{
    private EmailValidator $emailValidator;

    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        if (!class_exists(Validation::class)) {
            $this->markTestSkipped('symfony/validator is not installed.');
        }

        $this->emailValidator = new EmailValidator(Configuration::fromArray([
            'checks' => ['mx' => false, 'role_based' => true],
            'cache' => ['driver' => 'memory', 'ttl' => 0],
            'lists' => ['custom_blocklist' => ['blocked-domain.test']],
        ]));

        $builder = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                NotDisposableEmailValidator::class => new NotDisposableEmailValidator($this->emailValidator),
                ValidEmailValidator::class => new ValidEmailValidator($this->emailValidator),
                NotRoleBasedEmailValidator::class => new NotRoleBasedEmailValidator($this->emailValidator),
            ]));

        if (method_exists($builder, 'enableAttributeMapping')) {
            $builder->enableAttributeMapping();
        }

        $this->validator = $builder->getValidator();
    }

    public function testNotDisposableEmail(): void
    {
        $violations = $this->validator->validate('user@eu.mailinator.com', new NotDisposableEmail());

        $this->assertCount(1, $violations);
        $this->assertSame('Disposable email addresses are not allowed.', $violations[0]->getMessage());
        $this->assertSame(NotDisposableEmail::DISPOSABLE_EMAIL_ERROR, $violations[0]->getCode());
        $this->assertSame('DISPOSABLE_EMAIL_ERROR', NotDisposableEmail::getErrorName(NotDisposableEmail::DISPOSABLE_EMAIL_ERROR));

        $this->assertCount(1, $this->validator->validate('user@blocked-domain.test', new NotDisposableEmail()));
        $this->assertCount(0, $this->validator->validate('user@gmail.com', new NotDisposableEmail()));
        $this->assertCount(0, $this->validator->validate(null, new NotDisposableEmail()));
        $this->assertCount(0, $this->validator->validate('', new NotDisposableEmail()));
    }

    public function testNotDisposableEmailNamedArguments(): void
    {
        $constraint = new NotDisposableEmail(message: '{{ domain }} matched {{ matched }}', groups: ['registration'], payload: 'p');

        $this->assertSame(['registration'], $constraint->groups);
        $this->assertSame('p', $constraint->payload);

        $violations = $this->validator->validate('user@eu.mailinator.com', $constraint, ['registration']);
        $this->assertCount(1, $violations);
        $this->assertSame('eu.mailinator.com matched mailinator.com', $violations[0]->getMessage());
    }

    public function testAttributesOnProperties(): void
    {
        $dto = new SignupDto();
        $dto->email = 'user@mailinator.com';
        $dto->contact = 'admin@gmail.com';

        $violations = $this->validator->validate($dto);
        $paths = [];
        foreach ($violations as $violation) {
            $paths[$violation->getPropertyPath()][] = $violation->getCode();
        }

        $this->assertSame([NotDisposableEmail::DISPOSABLE_EMAIL_ERROR], $paths['email'] ?? null);
        $this->assertSame([NotRoleBasedEmail::ROLE_BASED_EMAIL_ERROR], $paths['contact'] ?? null);

        $dto->email = 'jane@gmail.com';
        $dto->contact = 'jane@gmail.com';
        $this->assertCount(0, $this->validator->validate($dto));
    }

    public function testValidEmailReportsFailingCheck(): void
    {
        $cases = [
            'not-an-email' => [ValidEmail::INVALID_FORMAT_ERROR, 'format'],
            'user@mailinator.com' => [ValidEmail::DISPOSABLE_ERROR, 'disposable'],
            'admin@gmail.com' => [ValidEmail::ROLE_BASED_ERROR, 'role_based'],
        ];

        foreach ($cases as $email => [$code, $check]) {
            $violations = $this->validator->validate($email, new ValidEmail(message: '{{ check }}: {{ reason }}'));
            $this->assertCount(1, $violations, $email);
            $this->assertSame($code, $violations[0]->getCode(), $email);
            $this->assertStringStartsWith($check . ': ', (string) $violations[0]->getMessage(), $email);
        }

        $violations = $this->validator->validate('user@mailinator.com', new ValidEmail());
        $this->assertSame('This value is not a valid email address (Disposable email address).', $violations[0]->getMessage());

        $this->assertCount(0, $this->validator->validate('jane.doe@gmail.com', new ValidEmail()));
    }

    public function testValidEmailCheckMxOverrideIsRestored(): void
    {
        $config = $this->emailValidator->getConfiguration();
        $config->enableMxCheck(true);

        $this->assertCount(0, $this->validator->validate('jane.doe@gmail.com', new ValidEmail(checkMx: false)));
        $this->assertTrue($config->isMxCheckEnabled());
    }

    public function testValidEmailSuggestionParameter(): void
    {
        $this->emailValidator->getConfiguration()->enableTypoSuggestion(true);
        $this->emailValidator->getConfiguration()->setCustomBlocklist(['gmial.com']);
        $validator = new EmailValidator($this->emailValidator->getConfiguration());

        $symfonyValidator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                ValidEmailValidator::class => new ValidEmailValidator($validator),
            ]))
            ->getValidator();

        $violations = $symfonyValidator->validate('jane@gmial.com', new ValidEmail(message: 'Did you mean {{ suggestion }}?'));
        $this->assertCount(1, $violations);
        $this->assertSame('Did you mean jane@gmail.com?', $violations[0]->getMessage());
    }

    public function testValidatorsWorkWithoutContainer(): void
    {
        $violations = Validation::createValidator()->validate('user@mailinator.com', new NotDisposableEmail());

        $this->assertCount(1, $violations);
    }
}
