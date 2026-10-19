<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\CodeIgniter;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Config\ConfigurationBuilder;
use HarunGecit\EmailValidator\Config\Registrar;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\CodeIgniter\EmailValidatorRules;
use PHPUnit\Framework\TestCase;

/**
 * CodeIgniter 4 rule set, exercised directly (CodeIgniter itself is not installed).
 */
class EmailValidatorRulesTest extends TestCase
{
    private function rules(?Configuration $config = null): EmailValidatorRules
    {
        $config ??= ConfigurationBuilder::create()->basic()->withCustomBlocklist(['blocked.test'])->build();

        return new EmailValidatorRules(new EmailValidator($config));
    }

    public function testRegistrarAddsTheRuleSetToValidationConfig(): void
    {
        $this->assertSame(['ruleSets' => [EmailValidatorRules::class]], Registrar::Validation());
    }

    public function testRuleSetCanBeInstantiatedWithoutArguments(): void
    {
        // CodeIgniter instantiates rule sets with `new $class()`.
        $rules = new EmailValidatorRules();
        $error = null;

        $this->assertFalse($rules->not_disposable_email('user@mailinator.com', $error));
        $this->assertTrue($rules->not_disposable_email('user@gmail.com'));
    }

    public function testNotDisposableEmail(): void
    {
        $rules = $this->rules();

        $error = null;
        $this->assertTrue($rules->not_disposable_email('john@example.com', $error));
        $this->assertNull($error);

        $this->assertFalse($rules->not_disposable_email('john@mailinator.com', $error));
        $this->assertSame(EmailValidatorRules::MESSAGES['not_disposable_email'], $error);

        $error = null;
        $this->assertFalse($rules->not_disposable_email('john@sub.blocked.test', $error));
        $this->assertNotNull($error);
    }

    public function testNonStringValuesFail(): void
    {
        $rules = $this->rules();

        $this->assertFalse($rules->not_disposable_email(null));
        $this->assertFalse($rules->not_role_based_email(['a@b.com']));
        $this->assertFalse($rules->not_subaddressed_email(42));
        $this->assertFalse($rules->not_spoofed_email(null));
        $this->assertFalse($rules->email_has_mx(null));
        $this->assertFalse($rules->verified_email(null));
    }

    public function testEmailHasMxFailsWithoutDomain(): void
    {
        $error = null;

        $this->assertFalse($this->rules()->email_has_mx('not-an-email', $error));
        $this->assertSame(EmailValidatorRules::MESSAGES['email_has_mx'], $error);
    }

    public function testNotRoleBasedEmail(): void
    {
        $rules = $this->rules();
        $error = null;

        $this->assertTrue($rules->not_role_based_email('john@example.com', $error));
        $this->assertFalse($rules->not_role_based_email('admin@example.com', $error));
        $this->assertSame(EmailValidatorRules::MESSAGES['not_role_based_email'], $error);
    }

    public function testNotSubaddressedEmail(): void
    {
        $rules = $this->rules();
        $error = null;

        $this->assertTrue($rules->not_subaddressed_email('john@example.com', $error));
        $this->assertFalse($rules->not_subaddressed_email('john+news@example.com', $error));
        $this->assertSame(EmailValidatorRules::MESSAGES['not_subaddressed_email'], $error);
    }

    public function testNotSpoofedEmail(): void
    {
        $rules = $this->rules();
        $error = null;

        $this->assertTrue($rules->not_spoofed_email('john@example.com', $error));

        if (!class_exists(\Spoofchecker::class)) {
            // Without ext-intl the check cannot run and the rule passes.
            $this->assertTrue($rules->not_spoofed_email("j\u{043E}hn@example.com", $error));
            return;
        }

        // Cyrillic "о" mixed into a Latin local part.
        $this->assertFalse($rules->not_spoofed_email("j\u{043E}hn@example.com", $error));
        $this->assertSame(EmailValidatorRules::MESSAGES['not_spoofed_email'], $error);
    }

    public function testVerifiedEmailRunsTheConfiguredPipeline(): void
    {
        $config = ConfigurationBuilder::create()->basic()->withRoleBasedCheck()->build();
        $rules = $this->rules($config);
        $error = null;

        $this->assertTrue($rules->verified_email('john@example.com', $error));
        $this->assertNull($error);

        $this->assertFalse($rules->verified_email('john@mailinator.com', $error));
        $this->assertSame('The email address is not valid: Disposable email address.', $error);

        $this->assertFalse($rules->verified_email('info@example.com', $error));
        $this->assertSame('The email address is not valid: Role-based email address.', $error);

        $this->assertFalse($rules->verified_email('not-an-email', $error));
        $this->assertSame('The email address is not valid: Invalid email format.', $error);
    }

    public function testVerifiedEmailFailsInsteadOfThrowingWhenRateLimited(): void
    {
        $config = ConfigurationBuilder::create()->basic()->withRateLimiting(1, 60)->build();
        $rules = $this->rules($config);
        $error = null;

        $this->assertTrue($rules->verified_email('john@example.com', $error));
        $this->assertFalse($rules->verified_email('john@example.com', $error));
        $this->assertStringContainsString('Too many', (string) $error);
    }
}
