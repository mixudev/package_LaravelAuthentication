<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as BaseRouteServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Dedicated Route Service Provider for advanced route customization if separated.
 */
class AuthenticationRouteServiceProvider extends BaseRouteServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            if (config('authentication.routes.web.enabled', true)) {
                Route::middleware(config('authentication.routes.web.middleware', ['web']))
                    ->group(__DIR__ . '/../../routes/web.php');
            }

            if (config('authentication.routes.api.enabled', false)) {
                Route::prefix(config('authentication.routes.api.prefix', 'api/v1/auth'))
                    ->middleware(config('authentication.routes.api.middleware', ['api']))
                    ->group(__DIR__ . '/../../routes/api.php');
            }
        });
    }
}
