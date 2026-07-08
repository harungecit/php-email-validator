<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Contracts;

use HarunGecit\EmailValidator\Config\Configuration;

/**
 * Interface ConfigurableInterface
 *
 * Defines the contract for configurable components.
 *
 * @package HarunGecit\EmailValidator\Contracts
 */
interface ConfigurableInterface
{
    /**
     * Sets the configuration for this component.
     *
     * @param Configuration $config The configuration instance.
     * @return static
     */
    public function setConfiguration(Configuration $config): static;

    /**
     * Gets the current configuration.
     *
     * @return Configuration The configuration instance.
     */
    public function getConfiguration(): Configuration;
}
