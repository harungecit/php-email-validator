<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Validators;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Result\SuggestionResult;

/**
 * Class TypoSuggester
 *
 * Suggests corrections for common email domain typos.
 *
 * @package HarunGecit\EmailValidator\Validators
 */
class TypoSuggester
{
    /**
     * @var array<string> Common email domains for similarity matching.
     */
    private array $commonDomains;

    /**
     * @var array<string, string> Known typo mappings.
     */
    private array $typoMappings;

    /**
     * @var int Maximum Levenshtein distance for suggestions.
     */
    private int $maxDistance = 2;

    /**
     * TypoSuggester constructor.
     *
     * @param Configuration|null $config Optional configuration.
     */
    public function __construct(?Configuration $config = null)
    {
        if ($config !== null) {
            $this->commonDomains = $config->getCommonDomains();
            $this->typoMappings = $config->getTypoMappings();
        } else {
            $this->commonDomains = $this->getDefaultCommonDomains();
            $this->typoMappings = $this->getDefaultTypoMappings();
        }
    }

    /**
     * Gets a suggestion for an email address if a typo is detected.
     *
     * @param string $email The email address to check.
     * @return SuggestionResult|null The suggestion or null if no typo detected.
     */
    public function getSuggestion(string $email): ?SuggestionResult
    {
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return null;
        }

        $localPart = substr($email, 0, $atPos);
        $domain = strtolower(substr($email, $atPos + 1));

        // Skip if domain is already a common domain
        if (in_array($domain, $this->commonDomains, true)) {
            return null;
        }

        // Check known typo mappings first (highest confidence)
        if (isset($this->typoMappings[$domain])) {
            $suggestedDomain = $this->typoMappings[$domain];
            return new SuggestionResult(
                $email,
                $localPart . '@' . $suggestedDomain,
                $domain,
                $suggestedDomain,
                'known_typo',
                100
            );
        }

        // Find similar domain using Levenshtein distance
        $suggestion = $this->findSimilarDomain($domain);
        if ($suggestion !== null) {
            [$suggestedDomain, $distance] = $suggestion;
            $confidence = $this->calculateConfidence($distance, strlen($domain));

            return new SuggestionResult(
                $email,
                $localPart . '@' . $suggestedDomain,
                $domain,
                $suggestedDomain,
                'similar_domain',
                $confidence
            );
        }

        return null;
    }

    /**
     * Checks if an email has a potential typo.
     *
     * @param string $email The email address to check.
     * @return bool True if a typo is detected.
     */
    public function hasTypo(string $email): bool
    {
        return $this->getSuggestion($email) !== null;
    }

    /**
     * Finds a similar domain from the common domains list.
     *
     * @param string $domain The domain to match.
     * @return array{0: string, 1: int}|null [suggested_domain, distance] or null.
     */
    private function findSimilarDomain(string $domain): ?array
    {
        $minDistance = PHP_INT_MAX;
        $suggestion = null;

        foreach ($this->commonDomains as $commonDomain) {
            // Skip if lengths differ too much
            if (abs(strlen($domain) - strlen($commonDomain)) > $this->maxDistance) {
                continue;
            }

            $distance = levenshtein($domain, $commonDomain);

            // Only consider close matches
            if ($distance > 0 && $distance <= $this->maxDistance && $distance < $minDistance) {
                $minDistance = $distance;
                $suggestion = $commonDomain;
            }
        }

        return $suggestion !== null ? [$suggestion, $minDistance] : null;
    }

    /**
     * Calculates confidence score based on distance and domain length.
     *
     * @param int $distance Levenshtein distance.
     * @param int $domainLength Original domain length.
     * @return int Confidence score (0-100).
     */
    private function calculateConfidence(int $distance, int $domainLength): int
    {
        // Higher confidence for smaller relative distance
        $relativeDistance = $distance / max($domainLength, 1);
        $confidence = (int) round((1 - $relativeDistance) * 100);

        return max(0, min(100, $confidence));
    }

    /**
     * Gets the common domains list.
     *
     * @return array<string>
     */
    public function getCommonDomains(): array
    {
        return $this->commonDomains;
    }

    /**
     * Sets the common domains list.
     *
     * @param array<string> $domains The domains to use.
     * @return self
     */
    public function setCommonDomains(array $domains): self
    {
        $this->commonDomains = array_map('strtolower', array_map('trim', $domains));
        return $this;
    }

    /**
     * Adds a common domain.
     *
     * @param string $domain The domain to add.
     * @return self
     */
    public function addCommonDomain(string $domain): self
    {
        $domain = strtolower(trim($domain));
        if (!in_array($domain, $this->commonDomains, true)) {
            $this->commonDomains[] = $domain;
        }
        return $this;
    }

    /**
     * Gets the typo mappings.
     *
     * @return array<string, string>
     */
    public function getTypoMappings(): array
    {
        return $this->typoMappings;
    }

    /**
     * Adds a typo mapping.
     *
     * @param string $typo The typo domain.
     * @param string $correct The correct domain.
     * @return self
     */
    public function addTypoMapping(string $typo, string $correct): self
    {
        $this->typoMappings[strtolower($typo)] = strtolower($correct);
        return $this;
    }

    /**
     * Sets the maximum Levenshtein distance for suggestions.
     *
     * @param int $distance Maximum distance (1-3 recommended).
     * @return self
     */
    public function setMaxDistance(int $distance): self
    {
        $this->maxDistance = max(1, min(5, $distance));
        return $this;
    }

    /**
     * Gets the default common domains.
     *
     * @return array<string>
     */
    private function getDefaultCommonDomains(): array
    {
        return [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'icloud.com', 'aol.com', 'protonmail.com', 'mail.com',
            'yandex.com', 'zoho.com', 'gmx.com', 'fastmail.com',
            'live.com', 'msn.com', 'me.com', 'mac.com',
            'yahoo.co.uk', 'hotmail.co.uk', 'btinternet.com',
            'googlemail.com', 'yahoo.fr', 'orange.fr', 'free.fr',
            'web.de', 'gmx.de', 't-online.de',
            'yahoo.de', 'yahoo.es', 'yahoo.it',
            'hotmail.fr', 'hotmail.de', 'hotmail.es', 'hotmail.it',
            'outlook.fr', 'outlook.de', 'outlook.es', 'outlook.it',
            'proton.me', 'pm.me',
        ];
    }

    /**
     * Gets the default typo mappings.
     *
     * @return array<string, string>
     */
    private function getDefaultTypoMappings(): array
    {
        return [
            // Gmail typos
            'gmial.com' => 'gmail.com',
            'gmal.com' => 'gmail.com',
            'gmali.com' => 'gmail.com',
            'gmaill.com' => 'gmail.com',
            'gmail.co' => 'gmail.com',
            'gmail.cm' => 'gmail.com',
            'gmail.om' => 'gmail.com',
            'gamil.com' => 'gmail.com',
            'gnail.com' => 'gmail.com',
            'gmai.com' => 'gmail.com',
            'gmailc.om' => 'gmail.com',
            'gmaul.com' => 'gmail.com',
            'gmsil.com' => 'gmail.com',
            'g]mail.com' => 'gmail.com',
            'gemail.com' => 'gmail.com',
            'gimail.com' => 'gmail.com',

            // Yahoo typos
            'yaho.com' => 'yahoo.com',
            'yahooo.com' => 'yahoo.com',
            'yhoo.com' => 'yahoo.com',
            'yahoo.co' => 'yahoo.com',
            'yhaoo.com' => 'yahoo.com',
            'yaoo.com' => 'yahoo.com',
            'yajoo.com' => 'yahoo.com',
            'uahoo.com' => 'yahoo.com',

            // Hotmail typos
            'hotmal.com' => 'hotmail.com',
            'hotmial.com' => 'hotmail.com',
            'hotmil.com' => 'hotmail.com',
            'hotamil.com' => 'hotmail.com',
            'hotmail.co' => 'hotmail.com',
            'hotmain.com' => 'hotmail.com',
            'hotmaol.com' => 'hotmail.com',
            'hoymail.com' => 'hotmail.com',
            'jotmail.com' => 'hotmail.com',

            // Outlook typos
            'outlok.com' => 'outlook.com',
            'outloo.com' => 'outlook.com',
            'outlook.co' => 'outlook.com',
            'outloook.com' => 'outlook.com',
            'outlool.com' => 'outlook.com',
            'oitlook.com' => 'outlook.com',
            'putlook.com' => 'outlook.com',

            // iCloud typos
            'icoud.com' => 'icloud.com',
            'iclod.com' => 'icloud.com',
            'icloud.co' => 'icloud.com',

            // AOL typos
            'aol.co' => 'aol.com',
            'alo.com' => 'aol.com',
            'aoll.com' => 'aol.com',

            // Protonmail typos
            'protonmal.com' => 'protonmail.com',
            'protonmai.com' => 'protonmail.com',
            'protonmail.co' => 'protonmail.com',
            'protonmaill.com' => 'protonmail.com',

            // Common TLD typos
            'gmail.con' => 'gmail.com',
            'yahoo.con' => 'yahoo.com',
            'hotmail.con' => 'hotmail.com',
            'outlook.con' => 'outlook.com',
        ];
    }
}
