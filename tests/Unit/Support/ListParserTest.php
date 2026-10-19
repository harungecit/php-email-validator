<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Support;

use HarunGecit\EmailValidator\Support\ListParser;
use PHPUnit\Framework\TestCase;

class ListParserTest extends TestCase
{
    public function testParsesPlainTextWithCommentsAndNoise(): void
    {
        $content = "\xEF\xBB\xBF# comment\r\n; another comment\n// and another\nMailinator.COM\n\n  yopmail.com  \n"
            . "*.wildcard.org\ninline.net # trailing note\nmailinator.com\nnot a domain\nai100999.com.\n";

        $domains = ListParser::parse($content, $invalid);

        $this->assertSame(['mailinator.com', 'yopmail.com', 'wildcard.org', 'inline.net', 'ai100999.com'], $domains);
        $this->assertSame(1, $invalid);
    }

    public function testParsesFlatJsonArray(): void
    {
        $this->assertSame(['a-temp.com', 'b-temp.net'], ListParser::parse('["a-temp.com", "B-TEMP.net", "a-temp.com"]'));
    }

    public function testParsesNestedJson(): void
    {
        $json = '{"updated": "2026-10-01", "domains": ["a-temp.com", {"more": ["c-temp.org"]}], "count": 2}';

        $this->assertSame(['a-temp.com', 'c-temp.org'], ListParser::parse($json));
    }

    public function testInvalidJsonFallsBackToText(): void
    {
        $this->assertSame([], ListParser::parse('[not json'));
    }

    public function testParseFileReturnsNullForMissingFile(): void
    {
        $this->assertNull(ListParser::parseFile(sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.txt'));
    }

    public function testLooksLikeHtml(): void
    {
        $this->assertTrue(ListParser::looksLikeHtml("<!DOCTYPE html>\n<html><body>Rate limited</body></html>"));
        $this->assertTrue(ListParser::looksLikeHtml('  <html lang="en">'));
        $this->assertFalse(ListParser::looksLikeHtml("mailinator.com\nyopmail.com\n"));
        $this->assertFalse(ListParser::looksLikeHtml('["mailinator.com"]'));
    }
}
