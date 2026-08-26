<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

/**
 * Immutable Data Transfer Object representing a single configuration
 * issue detected by the SetupHealthChecker.
 *
 * Severity levels:
 *   - 'error'   : Blocking — authentication will fail or produce exceptions.
 *   - 'warning' : Non-blocking — a feature may be degraded or unavailable.
 *
 * Categories map to icons in the setup-warning UI view:
 *   'package' | 'oauth' | 'mail' | 'database' | 'security' | 'config'
 */
readonly class SetupIssue
{
    public function __construct(
        /**
         * Severity of the issue: 'error' (blocking) or 'warning' (degraded).
         */
        public string $severity,

        /**
         * Short, human-readable title for the issue card heading.
         */
        public string $title,

        /**
         * Detailed description explaining what is wrong and why it matters.
         */
        public string $description,

        /**
         * Actionable fix instruction: typically a CLI command or config key to set.
         */
        public string $fix,

        /**
         * Category for grouping and icon selection in the UI.
         * One of: 'package', 'oauth', 'mail', 'database', 'security', 'config'
         */
        public string $category,
    ) {}

    /**
     * Returns true when this is a blocking error (not just a warning).
     */
    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    /**
     * Returns true when this is a non-blocking warning.
     */
    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }
}
