<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature;

use Vendor\LaravelAuthentication\Contracts\AuditLoggerInterface;
use Vendor\LaravelAuthentication\Contracts\AuthenticationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialResolverInterface;
use Vendor\LaravelAuthentication\Contracts\CredentialValidatorInterface;
use Vendor\LaravelAuthentication\Contracts\FeatureRateLimiterInterface;
use Vendor\LaravelAuthentication\Contracts\LoginAttemptManagerInterface;
use Vendor\LaravelAuthentication\Contracts\OtpServiceInterface;
use Vendor\LaravelAuthentication\Contracts\PasswordHistoryRepositoryInterface;
use Vendor\LaravelAuthentication\Contracts\RegistrationServiceInterface;
use Vendor\LaravelAuthentication\Contracts\SocialAuthServiceInterface;
use Vendor\LaravelAuthentication\Contracts\TokenManagerInterface;
use Vendor\LaravelAuthentication\Services\Security\AuthenticationAuditService;
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

    public function test_all_service_contract_bindings_resolve(): void
    {
        $this->assertInstanceOf(AuditLoggerInterface::class, app(AuditLoggerInterface::class));
        $this->assertInstanceOf(FeatureRateLimiterInterface::class, app(FeatureRateLimiterInterface::class));
        $this->assertInstanceOf(OtpServiceInterface::class, app(OtpServiceInterface::class));
        $this->assertInstanceOf(RegistrationServiceInterface::class, app(RegistrationServiceInterface::class));
        $this->assertInstanceOf(SocialAuthServiceInterface::class, app(SocialAuthServiceInterface::class));
        $this->assertInstanceOf(PasswordHistoryRepositoryInterface::class, app(PasswordHistoryRepositoryInterface::class));
    }

    public function test_audit_logger_binding_is_concrete_audit_service(): void
    {
        $this->assertInstanceOf(AuthenticationAuditService::class, app(AuditLoggerInterface::class));
    }
}