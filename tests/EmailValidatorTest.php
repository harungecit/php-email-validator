<?php

namespace HarunGecit\EmailValidator\Tests;

use PHPUnit\Framework\TestCase;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Fetcher;

/**
 * Comprehensive test suite for EmailValidator
 *
 * @covers \HarunGecit\EmailValidator\EmailValidator
 */
class EmailValidatorTest extends TestCase
{
    private EmailValidator $validator;

    /**
     * Sets up the validator instance before each test.
     */
    protected function setUp(): void
    {
        $blocklist = Fetcher::loadBlocklist();
        $allowlist = Fetcher::loadAllowlist();
        $this->validator = new EmailValidator($blocklist, $allowlist);
    }

    // ========================================
    // Format Validation Tests
    // ========================================

    public function testValidEmailFormats(): void
    {
        $validEmails = [
            'user@example.com',
            'user@mail.example.com',
            'user+tag@example.com',
            'first.last@example.com',
            'user123@example.com',
            '123456@example.com',
            'user@ex.co',
            'user@my-domain.com',
            'USER@EXAMPLE.COM',
            'User@Example.Com',
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue(
                $this->validator->isValidFormat($email),
                "Email '{$email}' should have a valid format"
            );
        }
    }

    public function testInvalidEmailFormats(): void
    {
        $invalidEmails = [
            'userexample.com',
            'user@',
            '@example.com',
            'user@@example.com',
            'user @example.com',
            'invalid-email',
            '',
            'example.com',
            'user@example',
            'user<>@example.com',
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                $this->validator->isValidFormat($email),
                "Email '{$email}' should have an invalid format"
            );
        }
    }

    public function testEmptyEmailIsInvalid(): void
    {
        $this->assertFalse($this->validator->isValidFormat(''));
    }

    // ========================================
    // Disposable Email Detection Tests
    // ========================================

    public function testDisposableEmails(): void
    {
        $disposableEmails = [
            'test@mailinator.com',
            'test@guerrillamail.com',
            'test@tempmail.com',
            'test@yopmail.com',
            'test@10minutemail.com',
            'test@throwawaymail.com',
            'test@fakeinbox.com',
            'test@sharklasers.com',
            'test@getnada.com',
            'test@temp-mail.org',
        ];

        foreach ($disposableEmails as $email) {
            $this->assertTrue(
                $this->validator->isDisposable($email),
                "Email '{$email}' should be detected as disposable"
            );
        }
    }

    public function testNonDisposableEmails(): void
    {
        $nonDisposableEmails = [
            'user@gmail.com',
            'user@outlook.com',
            'user@yahoo.com',
            'user@example.com',
            'user@mycompany.com',
            'user@protonmail.com',
            'user@icloud.com',
        ];

        foreach ($nonDisposableEmails as $email) {
            $this->assertFalse(
                $this->validator->isDisposable($email),
                "Email '{$email}' should not be detected as disposable"
            );
        }
    }

    public function testDisposableEmailCaseInsensitive(): void
    {
        $this->assertTrue($this->validator->isDisposable('test@MAILINATOR.COM'));
        $this->assertTrue($this->validator->isDisposable('test@Mailinator.Com'));
    }

    // ========================================
    // MX Record Validation Tests
    // ========================================

    public function testValidMXRecord(): void
    {
        $this->assertTrue(
            $this->validator->hasValidMX('user@google.com'),
            'Google.com should have valid MX records'
        );
    }

    public function testInvalidMXRecord(): void
    {
        $this->assertFalse(
            $this->validator->hasValidMX('user@thisdomain-definitely-does-not-exist-12345.com'),
            'Non-existent domain should not have MX records'
        );
    }

    public function testMXRecordCaching(): void
    {
        $validator = new EmailValidator([], []);
        $validator->setCacheEnabled(true);

        $result1 = $validator->hasValidMX('user@google.com');
        $result2 = $validator->hasValidMX('user@google.com');

        $this->assertEquals($result1, $result2);
    }

    public function testClearMXCache(): void
    {
        $validator = new EmailValidator([], []);
        $validator->hasValidMX('user@google.com');
        $validator->clearCache();

        $this->assertTrue($validator->hasValidMX('user@google.com'));
    }

    // ========================================
    // Complete Validation Tests
    // ========================================

    public function testIsValidWithAllChecks(): void
    {
        $this->assertTrue(
            $this->validator->isValid('user@google.com', true),
            'Valid email with MX should pass'
        );
    }

    public function testIsValidWithoutMXCheck(): void
    {
        $this->assertTrue(
            $this->validator->isValid('user@example.com', false),
            'Valid email without MX check should pass'
        );
    }

    public function testIsValidRejectsDisposable(): void
    {
        $this->assertFalse(
            $this->validator->isValid('user@mailinator.com', false),
            'Disposable email should be rejected'
        );
    }

    public function testIsValidRejectsInvalidFormat(): void
    {
        $this->assertFalse(
            $this->validator->isValid('invalid-email', false),
            'Invalid format should be rejected'
        );
    }

    // ========================================
    // Batch Validation Tests
    // ========================================

    public function testValidateMultiple(): void
    {
        $emails = [
            'valid@example.com',
            'invalid-email',
            'disposable@mailinator.com',
        ];

        $results = $this->validator->validateMultiple($emails, false);

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('valid@example.com', $results);
        $this->assertArrayHasKey('invalid-email', $results);
        $this->assertArrayHasKey('disposable@mailinator.com', $results);

        $this->assertTrue($results['valid@example.com']['valid']);
        $this->assertTrue($results['valid@example.com']['format']);
        $this->assertFalse($results['valid@example.com']['disposable']);

        $this->assertFalse($results['invalid-email']['valid']);
        $this->assertFalse($results['invalid-email']['format']);

        $this->assertFalse($results['disposable@mailinator.com']['valid']);
        $this->assertTrue($results['disposable@mailinator.com']['disposable']);
    }

    public function testValidateWithDetails(): void
    {
        $result = $this->validator->validateWithDetails('user@example.com', false);

        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('format', $result);
        $this->assertArrayHasKey('disposable', $result);
        $this->assertArrayHasKey('mx', $result);
        $this->assertArrayHasKey('domain', $result);
        $this->assertArrayHasKey('errors', $result);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['format']);
        $this->assertFalse($result['disposable']);
        $this->assertEquals('example.com', $result['domain']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateWithDetailsShowsErrors(): void
    {
        $result = $this->validator->validateWithDetails('invalid-email', false);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    // ========================================
    // Filter Tests
    // ========================================

    public function testFilterValid(): void
    {
        $emails = [
            'valid1@example.com',
            'valid2@example.com',
            'invalid-email',
            'disposable@mailinator.com',
        ];

        $validEmails = $this->validator->filterValid($emails, false);

        $this->assertCount(2, $validEmails);
        $this->assertContains('valid1@example.com', $validEmails);
        $this->assertContains('valid2@example.com', $validEmails);
    }

    public function testFilterInvalid(): void
    {
        $emails = [
            'valid@example.com',
            'invalid-email',
            'disposable@mailinator.com',
        ];

        $invalidEmails = $this->validator->filterInvalid($emails, false);

        $this->assertCount(2, $invalidEmails);
        $this->assertContains('invalid-email', $invalidEmails);
        $this->assertContains('disposable@mailinator.com', $invalidEmails);
    }

    // ========================================
    // Domain Extraction Tests
    // ========================================

    public function testExtractDomain(): void
    {
        $this->assertEquals('example.com', $this->validator->extractDomain('user@example.com'));
        $this->assertEquals('sub.example.com', $this->validator->extractDomain('user@sub.example.com'));
    }

    public function testExtractDomainReturnsLowercase(): void
    {
        $this->assertEquals('example.com', $this->validator->extractDomain('user@EXAMPLE.COM'));
    }

    public function testExtractDomainReturnsNullForInvalid(): void
    {
        $this->assertNull($this->validator->extractDomain('invalid-email-no-at'));
    }

    public function testExtractLocalPart(): void
    {
        $this->assertEquals('user', $this->validator->extractLocalPart('user@example.com'));
        $this->assertEquals('first.last', $this->validator->extractLocalPart('first.last@example.com'));
        $this->assertEquals('user+tag', $this->validator->extractLocalPart('user+tag@example.com'));
    }

    public function testExtractLocalPartReturnsNullForInvalid(): void
    {
        $this->assertNull($this->validator->extractLocalPart('no-at-sign'));
    }

    // ========================================
    // Normalization Tests
    // ========================================

    public function testNormalize(): void
    {
        $this->assertEquals('user@example.com', $this->validator->normalize('  USER@EXAMPLE.COM  '));
        $this->assertEquals('user@example.com', $this->validator->normalize('User@Example.Com'));
    }

    public function testNormalizeMultiple(): void
    {
        $emails = ['  USER@EXAMPLE.COM  ', 'Test@Test.Com'];
        $normalized = $this->validator->normalizeMultiple($emails);

        $this->assertEquals(['user@example.com', 'test@test.com'], $normalized);
    }

    // ========================================
    // Allowlist/Blocklist Management Tests
    // ========================================

    public function testAddToBlocklist(): void
    {
        $validator = new EmailValidator([], []);

        $validator->addToBlocklist('custom-disposable.com');

        $this->assertTrue($validator->isDisposable('test@custom-disposable.com'));
    }

    public function testAddMultipleToBlocklist(): void
    {
        $validator = new EmailValidator([], []);

        $validator->addMultipleToBlocklist(['domain1.com', 'domain2.com']);

        $this->assertTrue($validator->isDisposable('test@domain1.com'));
        $this->assertTrue($validator->isDisposable('test@domain2.com'));
    }

    public function testAddToAllowlist(): void
    {
        $validator = new EmailValidator(['test-domain.com'], []);

        $this->assertTrue($validator->isDisposable('test@test-domain.com'));

        $validator->addToAllowlist('test-domain.com');

        $this->assertFalse($validator->isDisposable('test@test-domain.com'));
    }

    public function testRemoveFromBlocklist(): void
    {
        $validator = new EmailValidator(['test.com'], []);

        $this->assertTrue($validator->isDisposable('user@test.com'));

        $validator->removeFromBlocklist('test.com');

        $this->assertFalse($validator->isDisposable('user@test.com'));
    }

    public function testRemoveFromAllowlist(): void
    {
        $validator = new EmailValidator(['test.com'], ['test.com']);

        $this->assertFalse($validator->isDisposable('user@test.com'));

        $validator->removeFromAllowlist('test.com');

        $this->assertTrue($validator->isDisposable('user@test.com'));
    }

    public function testGetBlocklist(): void
    {
        $blocklist = ['domain1.com', 'domain2.com'];
        $validator = new EmailValidator($blocklist, []);

        $this->assertEquals($blocklist, $validator->getBlocklist());
    }

    public function testGetAllowlist(): void
    {
        $allowlist = ['allowed1.com', 'allowed2.com'];
        $validator = new EmailValidator([], $allowlist);

        $this->assertEquals($allowlist, $validator->getAllowlist());
    }

    public function testGetBlocklistCount(): void
    {
        $validator = new EmailValidator(['a.com', 'b.com', 'c.com'], []);

        $this->assertEquals(3, $validator->getBlocklistCount());
    }

    public function testGetAllowlistCount(): void
    {
        $validator = new EmailValidator([], ['a.com', 'b.com']);

        $this->assertEquals(2, $validator->getAllowlistCount());
    }

    // ========================================
    // Is Allowlisted/Blocklisted Tests
    // ========================================

    public function testIsAllowlisted(): void
    {
        $validator = new EmailValidator([], ['allowed.com']);

        $this->assertTrue($validator->isAllowlisted('user@allowed.com'));
        $this->assertFalse($validator->isAllowlisted('user@other.com'));
    }

    public function testIsBlocklisted(): void
    {
        $validator = new EmailValidator(['blocked.com'], []);

        $this->assertTrue($validator->isBlocklisted('user@blocked.com'));
        $this->assertFalse($validator->isBlocklisted('user@other.com'));
    }

    // ========================================
    // Statistics Tests
    // ========================================

    public function testGetStatistics(): void
    {
        $emails = [
            'valid1@example.com',
            'valid2@example.com',
            'invalid-email',
            'disposable@mailinator.com',
        ];

        $stats = $this->validator->getStatistics($emails, false);

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['valid']);
        $this->assertEquals(2, $stats['invalid']);
        $this->assertEquals(1, $stats['invalid_format']);
        $this->assertEquals(1, $stats['disposable']);
    }

    // ========================================
    // Static Factory Method Test
    // ========================================

    public function testCreateFactoryMethod(): void
    {
        $validator = EmailValidator::create();

        $this->assertInstanceOf(EmailValidator::class, $validator);
        $this->assertGreaterThan(0, $validator->getBlocklistCount());
    }

    // ========================================
    // Fluent Interface Tests
    // ========================================

    public function testFluentInterface(): void
    {
        $validator = new EmailValidator([], []);

        $result = $validator
            ->addToBlocklist('test1.com')
            ->addToBlocklist('test2.com')
            ->addToAllowlist('allowed.com')
            ->setCacheEnabled(false)
            ->clearCache();

        $this->assertInstanceOf(EmailValidator::class, $result);
    }

    // ========================================
    // Edge Cases
    // ========================================

    public function testEmptyEmailArray(): void
    {
        $results = $this->validator->validateMultiple([], false);
        $this->assertEmpty($results);
    }

    public function testFilterValidWithEmptyArray(): void
    {
        $result = $this->validator->filterValid([], false);
        $this->assertEmpty($result);
    }

    public function testStatisticsWithEmptyArray(): void
    {
        $stats = $this->validator->getStatistics([], false);

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['valid']);
        $this->assertEquals(0, $stats['invalid']);
    }

    public function testDuplicateAddToBlocklist(): void
    {
        $validator = new EmailValidator([], []);

        $validator->addToBlocklist('test.com');
        $validator->addToBlocklist('test.com');

        $this->assertEquals(1, $validator->getBlocklistCount());
    }

    public function testDuplicateAddToAllowlist(): void
    {
        $validator = new EmailValidator([], []);

        $validator->addToAllowlist('test.com');
        $validator->addToAllowlist('test.com');

        $this->assertEquals(1, $validator->getAllowlistCount());
    }
}
