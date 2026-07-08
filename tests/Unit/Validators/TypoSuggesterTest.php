<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Validators;

use HarunGecit\EmailValidator\Validators\TypoSuggester;
use HarunGecit\EmailValidator\Result\SuggestionResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TypoSuggesterTest extends TestCase
{
    private TypoSuggester $suggester;

    protected function setUp(): void
    {
        $this->suggester = new TypoSuggester();
    }

    public static function knownTypoEmailsProvider(): array
    {
        return [
            ['user@gmial.com', 'gmail.com'],
            ['user@gmal.com', 'gmail.com'],
            ['user@gamil.com', 'gmail.com'],
            ['user@yaho.com', 'yahoo.com'],
            ['user@yahooo.com', 'yahoo.com'],
            ['user@hotmal.com', 'hotmail.com'],
            ['user@hotmial.com', 'hotmail.com'],
            ['user@outlok.com', 'outlook.com'],
            ['user@iclod.com', 'icloud.com'],
        ];
    }

    #[DataProvider('knownTypoEmailsProvider')]
    public function testSuggestCorrectsDomainTypos(string $email, string $expectedDomain): void
    {
        $result = $this->suggester->getSuggestion($email);

        $this->assertInstanceOf(SuggestionResult::class, $result);
        $this->assertStringEndsWith('@' . $expectedDomain, $result->getSuggestedEmail());
    }

    public function testSuggestReturnsNullForCorrectDomain(): void
    {
        $result = $this->suggester->getSuggestion('user@gmail.com');

        $this->assertNull($result);
    }

    public function testSuggestReturnsNullForUnknownDomain(): void
    {
        $result = $this->suggester->getSuggestion('user@unknowndomain12345.xyz');

        $this->assertNull($result);
    }

    public function testHasTypo(): void
    {
        $this->assertTrue($this->suggester->hasTypo('user@gmial.com'));
        $this->assertFalse($this->suggester->hasTypo('user@gmail.com'));
    }

    public function testAddCommonDomain(): void
    {
        $this->suggester->addCommonDomain('customdomain.com');

        $result = $this->suggester->getSuggestion('user@customdomian.com');

        $this->assertInstanceOf(SuggestionResult::class, $result);
        $this->assertEquals('user@customdomain.com', $result->getSuggestedEmail());
    }

    public function testAddTypoMapping(): void
    {
        $this->suggester->addTypoMapping('typoexample.com', 'example.com');
        $this->suggester->addCommonDomain('example.com');

        $result = $this->suggester->getSuggestion('user@typoexample.com');

        $this->assertInstanceOf(SuggestionResult::class, $result);
        $this->assertEquals('user@example.com', $result->getSuggestedEmail());
        $this->assertEquals('known_typo', $result->getReason());
        $this->assertEquals(100, $result->getConfidence());
    }

    public function testSuggestionResult(): void
    {
        $result = $this->suggester->getSuggestion('user@gmial.com');

        $this->assertInstanceOf(SuggestionResult::class, $result);
        $this->assertEquals('user@gmial.com', $result->getOriginalEmail());
        $this->assertEquals('user@gmail.com', $result->getSuggestedEmail());
        $this->assertEquals('gmial.com', $result->getOriginalDomain());
        $this->assertEquals('gmail.com', $result->getSuggestedDomain());
    }

    public function testKnownTypoMappingHighConfidence(): void
    {
        $result = $this->suggester->getSuggestion('user@gmial.com');

        $this->assertInstanceOf(SuggestionResult::class, $result);
        $this->assertEquals('known_typo', $result->getReason());
        $this->assertEquals(100, $result->getConfidence());
    }

    public function testSimilarDomainVariableConfidence(): void
    {
        $this->suggester->setCommonDomains(['testdomain.com']);

        $result = $this->suggester->getSuggestion('user@testdomian.com');

        $this->assertInstanceOf(SuggestionResult::class, $result);
        $this->assertEquals('similar_domain', $result->getReason());
        $this->assertGreaterThan(0, $result->getConfidence());
        $this->assertLessThanOrEqual(100, $result->getConfidence());
    }

    public function testInvalidEmailNoAt(): void
    {
        $result = $this->suggester->getSuggestion('invalid-email');

        $this->assertNull($result);
    }

    public function testPreservesLocalPart(): void
    {
        $result = $this->suggester->getSuggestion('john.doe+tag@gmial.com');

        $this->assertInstanceOf(SuggestionResult::class, $result);
        $this->assertEquals('john.doe+tag@gmail.com', $result->getSuggestedEmail());
    }

    public function testGetCommonDomains(): void
    {
        $domains = $this->suggester->getCommonDomains();

        $this->assertIsArray($domains);
        $this->assertContains('gmail.com', $domains);
        $this->assertContains('yahoo.com', $domains);
    }

    public function testGetTypoMappings(): void
    {
        $mappings = $this->suggester->getTypoMappings();

        $this->assertIsArray($mappings);
        $this->assertArrayHasKey('gmial.com', $mappings);
        $this->assertEquals('gmail.com', $mappings['gmial.com']);
    }

    public function testFluentInterface(): void
    {
        $suggester = new TypoSuggester();

        $this->assertSame($suggester, $suggester->setCommonDomains(['test.com']));
        $this->assertSame($suggester, $suggester->addCommonDomain('test2.com'));
        $this->assertSame($suggester, $suggester->addTypoMapping('typo.com', 'test.com'));
        $this->assertSame($suggester, $suggester->setMaxDistance(2));
    }
}
