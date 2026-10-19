<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Support;

use HarunGecit\EmailValidator\Support\Domain;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DomainTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function normalizeProvider(): array
    {
        return [
            'email' => ['User@Mailinator.COM', 'mailinator.com'],
            'bare domain' => ['Mailinator.com', 'mailinator.com'],
            'trailing dot' => ['user@mailinator.com.', 'mailinator.com'],
            'whitespace' => ['  user@example.org  ', 'example.org'],
            'last at sign wins' => ['"a@b"@example.net', 'example.net'],
            'empty domain' => ['user@', null],
            'empty input' => ['', null],
        ];
    }

    #[DataProvider('normalizeProvider')]
    public function testNormalize(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Domain::normalize($input));
    }

    public function testNormalizeConvertsInternationalizedDomainsToPunycode(): void
    {
        if (!function_exists('idn_to_ascii')) {
            $this->markTestSkipped('ext-intl is not available');
        }

        $this->assertSame('xn--mnchen-3ya.de', Domain::normalize('user@münchen.de'));
    }

    public function testIsValid(): void
    {
        $this->assertTrue(Domain::isValid('mailinator.com'));
        $this->assertTrue(Domain::isValid('a.b.c.example.co.uk'));
        $this->assertTrue(Domain::isValid('xn--mnchen-3ya.de'));
        $this->assertFalse(Domain::isValid('localhost'));
        $this->assertFalse(Domain::isValid('-bad.com'));
        $this->assertFalse(Domain::isValid('bad-.com'));
        $this->assertFalse(Domain::isValid('example.c'));
        $this->assertFalse(Domain::isValid('exa mple.com'));
        $this->assertFalse(Domain::isValid('10.11.9+ubu2404'));
    }

    public function testWithParentsStopsBeforeTheTld(): void
    {
        $this->assertSame(
            ['a.b.example.com', 'b.example.com', 'example.com'],
            Domain::withParents('a.b.example.com')
        );
        $this->assertSame([], Domain::withParents('com'));
    }

    public function testExtract(): void
    {
        $this->assertSame('Example.com', Domain::extract('user@Example.com'));
        $this->assertSame('example.com', Domain::extract('example.com'));
    }
}
