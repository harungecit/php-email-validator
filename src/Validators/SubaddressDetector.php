<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Validators;

/**
 * Class SubaddressDetector
 *
 * Detects and handles subaddressed emails (plus addressing, e.g., user+tag@domain.com)
 *
 * @package HarunGecit\EmailValidator\Validators
 */
class SubaddressDetector
{
    /**
     * @var array<string> Domains known to support plus addressing.
     */
    private array $supportedDomains = [
        'gmail.com', 'googlemail.com',
        'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'yahoo.com', 'yahoo.co.uk', 'yahoo.fr', 'yahoo.de',
        'protonmail.com', 'proton.me', 'pm.me',
        'fastmail.com', 'fastmail.fm',
        'icloud.com', 'me.com', 'mac.com',
        'zoho.com', 'zohomail.com',
        'hey.com',
        'tutanota.com', 'tutanota.de', 'tutamail.com', 'tuta.com', 'tuta.io',
    ];

    /**
     * Checks if an email uses subaddressing (plus addressing).
     *
     * @param string $email The email address to check.
     * @return bool True if the email uses subaddressing.
     */
    public function isSubaddressed(string $email): bool
    {
        $localPart = $this->extractLocalPart($email);

        if ($localPart === null) {
            return false;
        }

        return str_contains($localPart, '+');
    }

    /**
     * Gets the base email without the subaddress tag.
     *
     * @param string $email The email address.
     * @return string The base email without the tag.
     */
    public function getBaseEmail(string $email): string
    {
        if (!$this->isSubaddressed($email)) {
            return $email;
        }

        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return $email;
        }

        $localPart = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);

        $plusPos = strpos($localPart, '+');
        if ($plusPos !== false) {
            $localPart = substr($localPart, 0, $plusPos);
        }

        return $localPart . '@' . $domain;
    }

    /**
     * Gets the subaddress tag from an email.
     *
     * @param string $email The email address.
     * @return string|null The tag or null if not subaddressed.
     */
    public function getTag(string $email): ?string
    {
        if (!$this->isSubaddressed($email)) {
            return null;
        }

        $localPart = $this->extractLocalPart($email);
        if ($localPart === null) {
            return null;
        }

        $plusPos = strpos($localPart, '+');
        if ($plusPos !== false) {
            return substr($localPart, $plusPos + 1);
        }

        return null;
    }

    /**
     * Creates a subaddressed email with a tag.
     *
     * @param string $email The base email address.
     * @param string $tag The tag to add.
     * @return string The subaddressed email.
     */
    public function createSubaddress(string $email, string $tag): string
    {
        // First get the base email to avoid double-tagging
        $baseEmail = $this->getBaseEmail($email);

        $atPos = strrpos($baseEmail, '@');
        if ($atPos === false) {
            return $email;
        }

        $localPart = substr($baseEmail, 0, $atPos);
        $domain = substr($baseEmail, $atPos + 1);

        return $localPart . '+' . $tag . '@' . $domain;
    }

    /**
     * Checks if the domain supports subaddressing.
     *
     * @param string $email The email address.
     * @return bool True if the domain is known to support subaddressing.
     */
    public function domainSupportsSubaddressing(string $email): bool
    {
        $domain = $this->extractDomain($email);

        if ($domain === null) {
            return false;
        }

        return in_array($domain, $this->supportedDomains, true);
    }

    /**
     * Gets the list of domains known to support subaddressing.
     *
     * @return array<string>
     */
    public function getSupportedDomains(): array
    {
        return $this->supportedDomains;
    }

    /**
     * Adds a domain to the supported list.
     *
     * @param string $domain The domain to add.
     * @return self
     */
    public function addSupportedDomain(string $domain): self
    {
        $domain = strtolower(trim($domain));
        if (!in_array($domain, $this->supportedDomains, true)) {
            $this->supportedDomains[] = $domain;
        }
        return $this;
    }

    /**
     * Normalizes an email by removing the subaddress tag.
     *
     * @param string $email The email address.
     * @return string The normalized email.
     */
    public function normalize(string $email): string
    {
        return strtolower($this->getBaseEmail(trim($email)));
    }

    /**
     * Checks if two emails are equivalent (same after normalization).
     *
     * @param string $email1 First email.
     * @param string $email2 Second email.
     * @return bool True if emails are equivalent.
     */
    public function areEquivalent(string $email1, string $email2): bool
    {
        return $this->normalize($email1) === $this->normalize($email2);
    }

    /**
     * Extracts the local part from an email address.
     *
     * @param string $email The email address.
     * @return string|null The local part or null.
     */
    private function extractLocalPart(string $email): ?string
    {
        $atPos = strrpos($email, '@');
        return $atPos !== false ? substr($email, 0, $atPos) : null;
    }

    /**
     * Extracts the domain from an email address.
     *
     * @param string $email The email address.
     * @return string|null The domain or null.
     */
    private function extractDomain(string $email): ?string
    {
        $atPos = strrpos($email, '@');
        return $atPos !== false ? strtolower(substr($email, $atPos + 1)) : null;
    }
}
