<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialResolverInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialValidatorInterface;
use Vendor\LaravelAuthentication\Contracts\LoginAttemptManagerInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;
use Vendor\LaravelAuthentication\Support\AuthenticationStrategyRegistry;
use Vendor\LaravelAuthentication\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_binds_all_core_contracts(): void
    {
        $this->assertInstanceOf(AuthenticationServiceInterface::class, app(AuthenticationServiceInterface::class));
        $this->assertInstanceOf(CredentialResolverInterface::class, app(CredentialResolverInterface::class));
        $this->assertInstanceOf(CredentialValidatorInterface::class, app(CredentialValidatorInterface::class));
        $this->assertInstanceOf(LoginAttemptManagerInterface::class, app(LoginAttemptManagerInterface::class));
        $this->assertInstanceOf(TokenManagerInterface::class, app(TokenManagerInterface::class));
        $this->assertInstanceOf(AuthenticationConfig::class, app(AuthenticationConfig::class));
        $this->assertInstanceOf(AuthenticationStrategyRegistry::class, app(AuthenticationStrategyRegistry::class));
    }
}
