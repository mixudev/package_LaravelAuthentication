<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Vendor\LaravelAuthentication\Support\SetupHealthChecker;

/**
 * SetupWarningController
 *
 * Renders the professional setup warning page when the authentication
 * package detects configuration errors or incomplete dependencies.
 *
 * This controller is only reachable via the internal route
 * [authentication.setup.warning] and is therefore not exposed
 * to end users in production (the middleware is a no-op there).
 */
class SetupWarningController extends Controller
{
    public function __construct(
        private readonly SetupHealthChecker $checker,
    ) {}

    /**
     * Display the authentication setup warning page.
     *
     * Groups issues by category for structured display in the view,
     * and passes summary counts for the header status bar.
     */
    public function index(Request $request): View
    {
        $issues = $this->checker->check();

        $errors   = array_filter($issues, fn ($i) => $i->isError());
        $warnings = array_filter($issues, fn ($i) => $i->isWarning());

        // Group all issues by category for section-based rendering
        $grouped = [];
        foreach ($issues as $issue) {
            $grouped[$issue->category][] = $issue;
        }

        return view('authentication::setup-warning', [
            'issues'        => $issues,
            'errors'        => array_values($errors),
            'warnings'      => array_values($warnings),
            'grouped'       => $grouped,
            'errorCount'    => count($errors),
            'warningCount'  => count($warnings),
            'environment'   => app()->environment(),
            'appName'       => config('app.name', 'Laravel'),
        ]);
    }
}
