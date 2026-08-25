<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Contracts;

use Vendor\LaravelAuthentication\DTO\LoginData;
use Vendor\LaravelAuthentication\DTO\AuthenticationResult;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;

/**
 * Purpose:
 * Central interface contract for executing modular authentication workflows.
 *
 * Responsibility:
 * Defines the public entrypoint for authenticating users, processing logouts, and verifying status.
 *
 * Security considerations:
 * Must fail-closed on unhandled exceptions and maintain user enumeration mitigations.
 *
 * Dependencies:
 * Vendor\LaravelAuthentication\DTO\LoginData, AuthenticationResult, AuthenticationContext
 */
interface AuthenticationServiceInterface
{
    /**
     * Authenticate a user request using configured strategies and policies.
     */
    public function authenticate(LoginData $data, AuthenticationContext $context): AuthenticationResult;

    /**
     * Log the current user out across active sessions or tokens.
     */
    public function logout(AuthenticationContext $context): void;

    /**
     * Check if authentication is enabled and available.
     */
    public function isEnabled(): bool;
}
