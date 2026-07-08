<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Validators;

use HarunGecit\EmailValidator\Config\Configuration;

/**
 * Class RoleBasedValidator
 *
 * Detects role-based email addresses (admin@, info@, support@, etc.)
 *
 * @package HarunGecit\EmailValidator\Validators
 */
class RoleBasedValidator
{
    /**
     * @var array<string> Role-based prefixes.
     */
    private array $prefixes;

    /**
     * RoleBasedValidator constructor.
     *
     * @param Configuration|null $config Optional configuration.
     */
    public function __construct(?Configuration $config = null)
    {
        if ($config !== null) {
            $this->prefixes = $config->getRoleBasedPrefixes();
        } else {
            $this->prefixes = $this->getDefaultPrefixes();
        }
    }

    /**
     * Checks if an email address is role-based.
     *
     * @param string $email The email address to check.
     * @return bool True if role-based.
     */
    public function isRoleBased(string $email): bool
    {
        $localPart = $this->extractLocalPart($email);

        if ($localPart === null) {
            return false;
        }

        $localPart = strtolower($localPart);

        // Remove subaddress part if present (e.g., admin+test -> admin)
        $plusPos = strpos($localPart, '+');
        if ($plusPos !== false) {
            $localPart = substr($localPart, 0, $plusPos);
        }

        // Exact match
        if (in_array($localPart, $this->prefixes, true)) {
            return true;
        }

        // Check prefixes with separators (admin.user, admin-user, admin_user)
        foreach ($this->prefixes as $prefix) {
            if (
                str_starts_with($localPart, $prefix . '.') ||
                str_starts_with($localPart, $prefix . '-') ||
                str_starts_with($localPart, $prefix . '_')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the role-based prefix from an email if detected.
     *
     * @param string $email The email address.
     * @return string|null The detected prefix or null.
     */
    public function getDetectedPrefix(string $email): ?string
    {
        $localPart = $this->extractLocalPart($email);

        if ($localPart === null) {
            return null;
        }

        $localPart = strtolower($localPart);

        // Remove subaddress part
        $plusPos = strpos($localPart, '+');
        if ($plusPos !== false) {
            $localPart = substr($localPart, 0, $plusPos);
        }

        // Exact match
        if (in_array($localPart, $this->prefixes, true)) {
            return $localPart;
        }

        // Check prefixes
        foreach ($this->prefixes as $prefix) {
            if (
                str_starts_with($localPart, $prefix . '.') ||
                str_starts_with($localPart, $prefix . '-') ||
                str_starts_with($localPart, $prefix . '_') ||
                $localPart === $prefix
            ) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * Gets the current list of role-based prefixes.
     *
     * @return array<string>
     */
    public function getPrefixes(): array
    {
        return $this->prefixes;
    }

    /**
     * Sets the list of role-based prefixes.
     *
     * @param array<string> $prefixes The prefixes to use.
     * @return self
     */
    public function setPrefixes(array $prefixes): self
    {
        $this->prefixes = array_map('strtolower', array_map('trim', $prefixes));
        return $this;
    }

    /**
     * Adds a prefix to the list.
     *
     * @param string $prefix The prefix to add.
     * @return self
     */
    public function addPrefix(string $prefix): self
    {
        $prefix = strtolower(trim($prefix));
        if (!in_array($prefix, $this->prefixes, true)) {
            $this->prefixes[] = $prefix;
        }
        return $this;
    }

    /**
     * Removes a prefix from the list.
     *
     * @param string $prefix The prefix to remove.
     * @return self
     */
    public function removePrefix(string $prefix): self
    {
        $prefix = strtolower(trim($prefix));
        $key = array_search($prefix, $this->prefixes, true);
        if ($key !== false) {
            unset($this->prefixes[$key]);
            $this->prefixes = array_values($this->prefixes);
        }
        return $this;
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
     * Gets the default role-based prefixes.
     *
     * @return array<string>
     */
    private function getDefaultPrefixes(): array
    {
        return [
            'admin', 'administrator', 'info', 'information',
            'support', 'help', 'helpdesk', 'contact',
            'sales', 'marketing', 'billing', 'finance',
            'noreply', 'no-reply', 'donotreply', 'do-not-reply',
            'webmaster', 'postmaster', 'hostmaster', 'abuse',
            'security', 'privacy', 'legal', 'compliance',
            'hr', 'humanresources', 'recruitment', 'jobs', 'careers',
            'press', 'media', 'pr', 'news',
            'office', 'reception', 'frontdesk',
            'team', 'staff', 'all', 'everyone',
            'hello', 'hi', 'hey', 'enquiries', 'enquiry',
            'feedback', 'suggestions', 'complaints',
            'newsletter', 'subscribe', 'unsubscribe',
            'orders', 'order', 'shipping', 'returns',
            'accounts', 'accounting', 'payroll',
            'it', 'tech', 'technical', 'engineering',
            'dev', 'development', 'developers',
            'test', 'testing', 'demo',
            'service', 'services', 'customerservice',
        ];
    }
}
