<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use Illuminate\Contracts\Container\Container;
use Vendor\LaravelAuthentication\Contracts\AuthenticationStrategyInterface;
use Vendor\LaravelAuthentication\Exceptions\InvalidStrategyException;

/**
 * Dynamic registry managing authentication strategies.
 * Allows applications to plug in custom strategies at runtime.
 */
final class AuthenticationStrategyRegistry
{
    /**
     * @var array<string, class-string<AuthenticationStrategyInterface>|AuthenticationStrategyInterface>
     */
    private array $strategies = [];

    public function __construct(
        private readonly Container $container
    ) {}

    /**
     * Register a new strategy into the registry.
     *
     * @param class-string<AuthenticationStrategyInterface>|AuthenticationStrategyInterface $strategy
     */
    public function register(string $name, string|AuthenticationStrategyInterface $strategy): self
    {
        $this->strategies[$name] = $strategy;
        return $this;
    }

    /**
     * Retrieve an instantiated strategy instance.
     *
     * @throws InvalidStrategyException
     */
    public function get(string $name): AuthenticationStrategyInterface
    {
        if (!isset($this->strategies[$name])) {
            throw new InvalidStrategyException("Authentication strategy [{$name}] is not registered.");
        }

        $strategy = $this->strategies[$name];

        if (is_string($strategy)) {
            $instance = $this->container->make($strategy);
            if (!$instance instanceof AuthenticationStrategyInterface) {
                throw new InvalidStrategyException("Class [{$strategy}] does not implement AuthenticationStrategyInterface.");
            }
            return $instance;
        }

        return $strategy;
    }

    /**
     * Check if a strategy is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->strategies[$name]);
    }

    /**
     * Get all registered strategy names.
     *
     * @return array<int, string>
     */
    public function getRegisteredNames(): array
    {
        return array_keys($this->strategies);
    }
}
