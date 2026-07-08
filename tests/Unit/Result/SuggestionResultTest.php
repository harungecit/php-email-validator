<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Result;

use HarunGecit\EmailValidator\Result\SuggestionResult;
use PHPUnit\Framework\TestCase;

class SuggestionResultTest extends TestCase
{
    public function testConstruction(): void
    {
        $result = new SuggestionResult(
            'user@gmial.com',
            'user@gmail.com',
            'gmial.com',
            'gmail.com',
            'typo',
            95
        );

        $this->assertEquals('user@gmial.com', $result->getOriginalEmail());
        $this->assertEquals('user@gmail.com', $result->getSuggestedEmail());
        $this->assertEquals('gmial.com', $result->getOriginalDomain());
        $this->assertEquals('gmail.com', $result->getSuggestedDomain());
        $this->assertEquals('typo', $result->getReason());
        $this->assertEquals(95, $result->getConfidence());
    }

    public function testDefaultValues(): void
    {
        $result = new SuggestionResult(
            'user@gmial.com',
            'user@gmail.com',
            'gmial.com',
            'gmail.com'
        );

        $this->assertEquals('typo', $result->getReason());
        $this->assertEquals(100, $result->getConfidence());
    }

    public function testConfidenceBounds(): void
    {
        $tooHigh = new SuggestionResult('a@b.com', 'a@c.com', 'b.com', 'c.com', 'typo', 150);
        $this->assertEquals(100, $tooHigh->getConfidence());

        $tooLow = new SuggestionResult('a@b.com', 'a@c.com', 'b.com', 'c.com', 'typo', -10);
        $this->assertEquals(0, $tooLow->getConfidence());
    }

    public function testIsHighConfidence(): void
    {
        $highConfidence = new SuggestionResult('a@b.com', 'a@c.com', 'b.com', 'c.com', 'typo', 90);
        $lowConfidence = new SuggestionResult('a@b.com', 'a@c.com', 'b.com', 'c.com', 'typo', 50);

        $this->assertTrue($highConfidence->isHighConfidence());
        $this->assertFalse($lowConfidence->isHighConfidence());

        $this->assertTrue($highConfidence->isHighConfidence(85));
        $this->assertFalse($highConfidence->isHighConfidence(95));
    }

    public function testToArray(): void
    {
        $result = new SuggestionResult(
            'user@gmial.com',
            'user@gmail.com',
            'gmial.com',
            'gmail.com',
            'typo',
            85
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('user@gmial.com', $array['original_email']);
        $this->assertEquals('user@gmail.com', $array['suggested_email']);
        $this->assertEquals('gmial.com', $array['original_domain']);
        $this->assertEquals('gmail.com', $array['suggested_domain']);
        $this->assertEquals('typo', $array['reason']);
        $this->assertEquals(85, $array['confidence']);
    }

    public function testToString(): void
    {
        $result = new SuggestionResult(
            'user@gmial.com',
            'user@gmail.com',
            'gmial.com',
            'gmail.com'
        );

        $this->assertEquals('user@gmail.com', (string) $result);
    }
}
