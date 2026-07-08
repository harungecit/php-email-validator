<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Result;

/**
 * Class SuggestionResult
 *
 * Represents a typo correction suggestion for an email address.
 *
 * @package HarunGecit\EmailValidator\Result
 */
class SuggestionResult
{
    /**
     * @var string The original email address.
     */
    private string $originalEmail;

    /**
     * @var string The suggested email address.
     */
    private string $suggestedEmail;

    /**
     * @var string The original domain.
     */
    private string $originalDomain;

    /**
     * @var string The suggested domain.
     */
    private string $suggestedDomain;

    /**
     * @var string The reason for the suggestion.
     */
    private string $reason;

    /**
     * @var int The confidence score (0-100).
     */
    private int $confidence;

    /**
     * SuggestionResult constructor.
     *
     * @param string $originalEmail The original email address.
     * @param string $suggestedEmail The suggested email address.
     * @param string $originalDomain The original domain.
     * @param string $suggestedDomain The suggested domain.
     * @param string $reason The reason for the suggestion.
     * @param int $confidence The confidence score (0-100).
     */
    public function __construct(
        string $originalEmail,
        string $suggestedEmail,
        string $originalDomain,
        string $suggestedDomain,
        string $reason = 'typo',
        int $confidence = 100
    ) {
        $this->originalEmail = $originalEmail;
        $this->suggestedEmail = $suggestedEmail;
        $this->originalDomain = $originalDomain;
        $this->suggestedDomain = $suggestedDomain;
        $this->reason = $reason;
        $this->confidence = max(0, min(100, $confidence));
    }

    /**
     * Gets the original email address.
     *
     * @return string
     */
    public function getOriginalEmail(): string
    {
        return $this->originalEmail;
    }

    /**
     * Gets the suggested email address.
     *
     * @return string
     */
    public function getSuggestedEmail(): string
    {
        return $this->suggestedEmail;
    }

    /**
     * Gets the original domain.
     *
     * @return string
     */
    public function getOriginalDomain(): string
    {
        return $this->originalDomain;
    }

    /**
     * Gets the suggested domain.
     *
     * @return string
     */
    public function getSuggestedDomain(): string
    {
        return $this->suggestedDomain;
    }

    /**
     * Gets the reason for the suggestion.
     *
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * Gets the confidence score.
     *
     * @return int
     */
    public function getConfidence(): int
    {
        return $this->confidence;
    }

    /**
     * Checks if this is a high confidence suggestion.
     *
     * @param int $threshold Minimum confidence threshold (default: 80).
     * @return bool
     */
    public function isHighConfidence(int $threshold = 80): bool
    {
        return $this->confidence >= $threshold;
    }

    /**
     * Converts the suggestion to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original_email' => $this->originalEmail,
            'suggested_email' => $this->suggestedEmail,
            'original_domain' => $this->originalDomain,
            'suggested_domain' => $this->suggestedDomain,
            'reason' => $this->reason,
            'confidence' => $this->confidence,
        ];
    }

    /**
     * String representation of the suggestion.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->suggestedEmail;
    }
}
