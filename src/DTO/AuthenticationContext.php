<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\DTO;

use Illuminate\Http\Request;
use Vendor\LaravelAuthentication\Enums\AuthenticationChannel;

/**
 * Purpose:
 * Request telemetry and environmental context (IP, User Agent, Channel, Guard).
 */
final class AuthenticationContext
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
        public readonly AuthenticationChannel $channel = AuthenticationChannel::WEB,
        public readonly string $guard = 'web',
        public readonly array $headers = []
    ) {}

    public static function fromRequest(Request $request, ?string $guard = null, ?AuthenticationChannel $channel = null): self
    {
        if ($channel === null) {
            $channel = $request->is('api/*')
                ? AuthenticationChannel::API
                : AuthenticationChannel::WEB;
        }

        return new self(
            ipAddress: (string) $request->ip(),
            userAgent: $request->userAgent(),
            channel: $channel,
            guard: $guard ?? (string) config('authentication.guard', 'web'),
            // SEC-13 FIX: Only carry non-sensitive, useful headers instead of the entire header bag
            // to avoid leaking internal auth headers into events/audit logs.
            headers: array_intersect_key(
                $request->headers->all(),
                array_flip([
                    'user-agent',
                    'accept',
                    'accept-language',
                    'accept-encoding',
                    'origin',
                    'referer',
                    'sec-fetch-site',
                    'sec-fetch-mode',
                    'sec-fetch-dest',
                ])
            )
        );
    }
}
