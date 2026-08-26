<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;

/**
 * SetupHealthChecker
 *
 * Performs a comprehensive audit of the package's configuration and
 * runtime dependencies. Returns a list of SetupIssue DTOs describing
 * any problems found.
 *
 * This checker is intentionally defensive: it never throws exceptions.
 * All checks are wrapped in try/catch so that a buggy check itself
 * cannot bring down the application.
 *
 * Results are cached in-memory for the lifetime of the request via
 * the singleton binding in AuthenticationServiceProvider.
 */
class SetupHealthChecker
{
    /** @var array<SetupIssue>|null Cached result for repeated calls within one request. */
    private ?array $cachedIssues = null;

    public function __construct(
        private readonly Application $app,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * Run all health checks and return the list of detected issues.
     *
     * @return array<SetupIssue>
     */
    public function check(): array
    {
        if ($this->cachedIssues !== null) {
            return $this->cachedIssues;
        }

        $issues = [];

        foreach ($this->getChecks() as $checkMethod) {
            try {
                $result = $this->{$checkMethod}();
                if ($result instanceof SetupIssue) {
                    $issues[] = $result;
                } elseif (is_array($result)) {
                    foreach ($result as $issue) {
                        if ($issue instanceof SetupIssue) {
                            $issues[] = $issue;
                        }
                    }
                }
            } catch (\Throwable) {
                // A broken check must never crash the application.
            }
        }

        $this->cachedIssues = $issues;

        return $issues;
    }

    /**
     * Returns true if there are any blocking errors.
     */
    public function hasErrors(): bool
    {
        return count(array_filter($this->check(), fn (SetupIssue $i) => $i->isError())) > 0;
    }

    /**
     * Returns true if there are any issues (errors or warnings).
     */
    public function hasIssues(): bool
    {
        return count($this->check()) > 0;
    }

    /**
     * Invalidate the cached result (useful in tests).
     */
    public function flush(): void
    {
        $this->cachedIssues = null;
    }

    // ─────────────────────────────────────────────────────────────────
    // Internal: registry of check methods
    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array<string>
     */
    private function getChecks(): array
    {
        return [
            'checkAppKey',
            'checkMigrations',
            'checkConfigPublished',
            'checkSocialitePackage',
            'checkOAuthCredentials',
            'checkMailConfiguration',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Individual Checks
    // ─────────────────────────────────────────────────────────────────

    /**
     * Verify APP_KEY is set. Without it, sessions and encryption fail entirely.
     */
    private function checkAppKey(): ?SetupIssue
    {
        $key = $this->config->get('app.key', '');

        if (empty($key)) {
            return new SetupIssue(
                severity: 'error',
                title: 'Application Key Not Set',
                description: 'The APP_KEY environment variable is empty. Laravel requires this key for session encryption, cookie signing, and password hashing. Authentication cannot function without it.',
                fix: 'php artisan key:generate',
                category: 'security',
            );
        }

        return null;
    }

    /**
     * Verify that the authentication package migrations have been run
     * by checking for the existence of the primary authentication table.
     */
    private function checkMigrations(): ?SetupIssue
    {
        try {
            \Illuminate\Support\Facades\Schema::hasTable('authentication_login_attempts');

            if (!\Illuminate\Support\Facades\Schema::hasTable('authentication_login_attempts')) {
                return new SetupIssue(
                    severity: 'error',
                    title: 'Package Migrations Not Run',
                    description: 'The authentication package tables do not exist in the database. The package requires its migrations to be run before it can store login attempts, OTP codes, or audit logs.',
                    fix: 'php artisan migrate',
                    category: 'database',
                );
            }
        } catch (\Throwable) {
            return new SetupIssue(
                severity: 'error',
                title: 'Database Connection Failed',
                description: 'Unable to connect to the database. The authentication package requires a working database connection to store login attempts and audit logs. Check your DB_* environment variables.',
                fix: "# Check your .env file:\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=your_database\nDB_USERNAME=your_user\nDB_PASSWORD=your_password",
                category: 'database',
            );
        }

        return null;
    }

    /**
     * Warn if the config file has not been published to the host application.
     * We detect this by checking if config_path('authentication.php') exists.
     */
    private function checkConfigPublished(): ?SetupIssue
    {
        $publishedPath = $this->app->configPath('authentication.php');

        if (!file_exists($publishedPath)) {
            return new SetupIssue(
                severity: 'warning',
                title: 'Configuration File Not Published',
                description: 'The package is using its internal default configuration. Publish the config file to your application to customize authentication behavior (strategies, features, redirects, etc.).',
                fix: 'php artisan vendor:publish --tag=authentication-config',
                category: 'config',
            );
        }

        return null;
    }

    /**
     * Check that laravel/socialite is installed when social authentication is enabled.
     */
    private function checkSocialitePackage(): ?SetupIssue
    {
        $socialEnabled = $this->config->get('authentication.features.social.enabled', false);

        if (!$socialEnabled) {
            return null;
        }

        if (!class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            return new SetupIssue(
                severity: 'error',
                title: 'Laravel Socialite Package Not Installed',
                description: 'Social / OAuth authentication is enabled in your config (AUTH_SOCIAL_ENABLED=true), but the required [laravel/socialite] package is not installed. All social login routes will throw exceptions until this is resolved.',
                fix: 'composer require laravel/socialite',
                category: 'package',
            );
        }

        return null;
    }

    /**
     * Check that OAuth credentials are configured for each enabled social provider.
     *
     * @return array<SetupIssue>
     */
    private function checkOAuthCredentials(): array
    {
        $issues = [];

        $socialEnabled = $this->config->get('authentication.features.social.enabled', false);

        if (!$socialEnabled) {
            return $issues;
        }

        $providers = (array) $this->config->get('authentication.features.social.providers', []);

        foreach ($providers as $name => $providerConfig) {
            if (empty($providerConfig['enabled'])) {
                continue;
            }

            $clientId     = $providerConfig['client_id'] ?? null;
            $clientSecret = $providerConfig['client_secret'] ?? null;

            if (empty($clientId) || empty($clientSecret)) {
                $envPrefix = strtoupper($name);
                $issues[] = new SetupIssue(
                    severity: 'error',
                    title: ucfirst($name) . ' OAuth Credentials Missing',
                    description: "Social login with [" . ucfirst($name) . "] is enabled, but the OAuth client credentials are not configured. Any attempt to redirect to " . ucfirst($name) . " for authentication will fail with a configuration error.",
                    fix: "# Add to your .env file:\n{$envPrefix}_CLIENT_ID=your-client-id\n{$envPrefix}_CLIENT_SECRET=your-client-secret\n{$envPrefix}_REDIRECT_URI=\${APP_URL}/auth/{$name}/callback",
                    category: 'oauth',
                );
            }
        }

        return $issues;
    }

    /**
     * Check that mail is configured when features that send email are enabled.
     *
     * @return array<SetupIssue>
     */
    private function checkMailConfiguration(): array
    {
        $issues = [];

        $otpEmailEnabled      = $this->config->get('authentication.features.otp.send_email', false);
        $forgotPasswordEnabled = $this->config->get('authentication.features.forgot_password.enabled', false);

        $requiresMail = $otpEmailEnabled || $forgotPasswordEnabled;

        if (!$requiresMail) {
            return $issues;
        }

        $mailer   = $this->config->get('mail.default', 'log');
        $mailHost = $this->config->get('mail.mailers.smtp.host', '');
        $fromAddr = $this->config->get('mail.from.address', '');

        // Warn if still on the 'log' driver (common default, emails go to log only)
        if ($mailer === 'log') {
            $issues[] = new SetupIssue(
                severity: 'warning',
                title: 'Mail Driver Set to Log (Emails Not Delivered)',
                description: 'Your application\'s mail driver is set to [log], which means emails (OTP codes, password reset links) are written to the log file instead of being delivered to users. This is acceptable for local development but must be changed for production.',
                fix: "# Update your .env file:\nMAIL_MAILER=smtp\nMAIL_HOST=smtp.example.com\nMAIL_PORT=587\nMAIL_USERNAME=your@email.com\nMAIL_PASSWORD=your-password\nMAIL_ENCRYPTION=tls",
                category: 'mail',
            );
        } elseif ($mailer === 'smtp' && empty($mailHost)) {
            // SMTP selected but host not configured
            $issues[] = new SetupIssue(
                severity: 'error',
                title: 'SMTP Host Not Configured',
                description: 'The mail driver is set to [smtp] but MAIL_HOST is empty. Features that send emails (OTP login, password reset) will fail at runtime when attempting to deliver messages.',
                fix: "# Update your .env file:\nMAIL_HOST=smtp.your-provider.com\nMAIL_PORT=587\nMAIL_USERNAME=your@email.com\nMAIL_PASSWORD=your-password\nMAIL_ENCRYPTION=tls",
                category: 'mail',
            );
        }

        if (empty($fromAddr) || $fromAddr === 'hello@example.com') {
            $issues[] = new SetupIssue(
                severity: 'warning',
                title: 'Mail From Address Not Configured',
                description: 'The MAIL_FROM_ADDRESS is not set or is still using the default placeholder [hello@example.com]. Emails sent by the authentication package (OTP codes, password resets) will appear to come from this address.',
                fix: "# Update your .env file:\nMAIL_FROM_ADDRESS=noreply@yourdomain.com\nMAIL_FROM_NAME=\"\${APP_NAME}\"",
                category: 'mail',
            );
        }

        return $issues;
    }
}
