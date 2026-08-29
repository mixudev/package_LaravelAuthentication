<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Services;

class DeviceDetector
{
    /**
     * Parse User-Agent and request headers into structured device information.
     *
     * @return array{platform: string, browser: string, device_name: string, location: ?string, fingerprint: string}
     */
    public function detect(?string $userAgent, string $ipAddress, int|string $userId = ''): array
    {
        $agent = $userAgent ?? 'Unknown Browser';

        $platform = $this->detectPlatform($agent);
        $browser = $this->detectBrowser($agent);
        $deviceName = "{$browser} on {$platform}";
        $location = $this->detectLocation();

        // Calculate a stable fingerprint based on User-Agent + IP subnet (/24 for IPv4 or /64 for IPv6)
        $ipSubnet = $this->resolveIpSubnet($ipAddress);
        $fingerprint = hash('sha256', "{$userId}|{$platform}|{$browser}|{$ipSubnet}");

        return [
            'platform'    => $platform,
            'browser'     => $browser,
            'device_name' => $deviceName,
            'location'    => $location,
            'fingerprint' => $fingerprint,
        ];
    }

    protected function detectPlatform(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Windows NT 10.0') || str_contains($userAgent, 'Windows NT 11.0') => 'Windows 10/11',
            str_contains($userAgent, 'Windows NT')                                                      => 'Windows',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')                     => 'iOS',
            str_contains($userAgent, 'Android')                                                         => 'Android',
            str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS X')             => 'macOS',
            str_contains($userAgent, 'Linux')                                                          => 'Linux',
            default                                                                                    => 'Unknown OS',
        };
    }

    protected function detectBrowser(string $userAgent): string
    {
        if (str_contains($userAgent, 'Edg/')) {
            return 'Microsoft Edge';
        }
        if (str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera')) {
            return 'Opera';
        }
        if (str_contains($userAgent, 'Chrome/')) {
            return 'Google Chrome';
        }
        if (str_contains($userAgent, 'Firefox/')) {
            return 'Mozilla Firefox';
        }
        if (str_contains($userAgent, 'Safari/')) {
            return 'Apple Safari';
        }

        return 'Unknown Browser';
    }

    protected function detectLocation(): ?string
    {
        try {
            if (!function_exists('app') || !app()->bound('request')) {
                return null;
            }

            // Detect Geo headers commonly provided by Cloudflare or Reverse Proxies
            $countryRaw = request()->header('CF-IPCountry')
                ?? request()->header('X-Country-Code')
                ?? request()->header('X-GeoIP-Country');

            $cityRaw = request()->header('CF-IPCity')
                ?? request()->header('X-City-Name');

            $country = is_string($countryRaw) ? $countryRaw : null;
            $city    = is_string($cityRaw)    ? $cityRaw    : null;

            if ($city !== null && $country !== null) {
                return "{$city}, {$country}";
            }

            return $country;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveIpSubnet(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return count($parts) === 4 ? "{$parts[0]}.{$parts[1]}.{$parts[2]}.0" : $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            return count($parts) >= 4 ? "{$parts[0]}:{$parts[1]}:{$parts[2]}:{$parts[3]}::" : $ip;
        }

        return $ip;
    }
}
