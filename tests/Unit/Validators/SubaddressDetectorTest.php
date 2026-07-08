<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Validators;

use HarunGecit\EmailValidator\Validators\SubaddressDetector;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SubaddressDetectorTest extends TestCase
{
    private SubaddressDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new SubaddressDetector();
    }

    public static function subaddressedEmailsProvider(): array
    {
        return [
            ['user+tag@gmail.com', true],
            ['user+newsletter@example.com', true],
            ['john+work@company.com', true],
            ['test+abc+def@domain.com', true],
            ['user@gmail.com', false],
            ['user.name@example.com', false],
            ['user-name@example.com', false],
        ];
    }

    #[DataProvider('subaddressedEmailsProvider')]
    public function testIsSubaddressed(string $email, bool $expected): void
    {
        $this->assertEquals($expected, $this->detector->isSubaddressed($email));
    }

    public static function baseEmailProvider(): array
    {
        return [
            ['user+tag@gmail.com', 'user@gmail.com'],
            ['user+newsletter@example.com', 'user@example.com'],
            ['john+work+extra@company.com', 'john@company.com'],
            ['user@gmail.com', 'user@gmail.com'],
            ['user.name@example.com', 'user.name@example.com'],
        ];
    }

    #[DataProvider('baseEmailProvider')]
    public function testGetBaseEmail(string $email, string $expectedBase): void
    {
        $this->assertEquals($expectedBase, $this->detector->getBaseEmail($email));
    }

    public static function tagProvider(): array
    {
        return [
            ['user+tag@gmail.com', 'tag'],
            ['user+newsletter@example.com', 'newsletter'],
            ['john+work+extra@company.com', 'work+extra'],
            ['user@gmail.com', null],
        ];
    }

    #[DataProvider('tagProvider')]
    public function testGetTag(string $email, ?string $expectedTag): void
    {
        $this->assertEquals($expectedTag, $this->detector->getTag($email));
    }

    public function testInvalidEmail(): void
    {
        $this->assertFalse($this->detector->isSubaddressed('invalid-email'));
        $this->assertEquals('invalid-email', $this->detector->getBaseEmail('invalid-email'));
        $this->assertNull($this->detector->getTag('invalid-email'));
    }

    public function testEmptyEmail(): void
    {
        $this->assertFalse($this->detector->isSubaddressed(''));
        $this->assertEquals('', $this->detector->getBaseEmail(''));
        $this->assertNull($this->detector->getTag(''));
    }

    public function testCreateSubaddress(): void
    {
        $this->assertEquals(
            'user+newsletter@gmail.com',
            $this->detector->createSubaddress('user@gmail.com', 'newsletter')
        );
    }

    public function testCreateSubaddressFromExisting(): void
    {
        $this->assertEquals(
            'user+new@gmail.com',
            $this->detector->createSubaddress('user+old@gmail.com', 'new')
        );
    }

    public function testDomainSupportsSubaddressing(): void
    {
        $this->assertTrue($this->detector->domainSupportsSubaddressing('user@gmail.com'));
        $this->assertTrue($this->detector->domainSupportsSubaddressing('user@outlook.com'));
        $this->assertTrue($this->detector->domainSupportsSubaddressing('user@protonmail.com'));
        $this->assertFalse($this->detector->domainSupportsSubaddressing('user@unknowndomain.xyz'));
    }

    public function testGetSupportedDomains(): void
    {
        $domains = $this->detector->getSupportedDomains();

        $this->assertIsArray($domains);
        $this->assertContains('gmail.com', $domains);
        $this->assertContains('outlook.com', $domains);
    }

    public function testAddSupportedDomain(): void
    {
        $this->assertFalse($this->detector->domainSupportsSubaddressing('user@custom.com'));

        $this->detector->addSupportedDomain('custom.com');

        $this->assertTrue($this->detector->domainSupportsSubaddressing('user@custom.com'));
    }

    public function testNormalize(): void
    {
        $this->assertEquals('user@gmail.com', $this->detector->normalize('User+Tag@Gmail.com'));
        $this->assertEquals('user@gmail.com', $this->detector->normalize('  User@Gmail.com  '));
    }

    public function testAreEquivalent(): void
    {
        $this->assertTrue($this->detector->areEquivalent('user@gmail.com', 'user+tag@gmail.com'));
        $this->assertTrue($this->detector->areEquivalent('User@Gmail.com', 'user+newsletter@gmail.com'));
        $this->assertFalse($this->detector->areEquivalent('user1@gmail.com', 'user2@gmail.com'));
    }

    public function testFluentInterface(): void
    {
        $this->assertSame($this->detector, $this->detector->addSupportedDomain('test.com'));
    }
}
