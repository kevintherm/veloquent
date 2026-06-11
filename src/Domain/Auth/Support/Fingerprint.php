<?php

namespace Veloquent\Core\Domain\Auth\Support;

use Illuminate\Http\Request;

class Fingerprint
{
    /**
     * Generate a unique fingerprint for the current request.
     */
    public static function generate(?Request $request = null): string
    {
        $request = $request ?? request();

        $clientId = $request->header('X-Fingerprint')
            ?? $request->header('X-Device-ID')
            ?? $request->header('X-Client-ID')
            ?? $request->input('fingerprint')
            ?? $request->input('device_id')
            ?? $request->input('client_id');

        if ($clientId) {
            return hash('sha256', $clientId);
        }

        $ip = $request->ip();
        $userAgent = $request->userAgent();

        $normalizedIp = '';
        if (!empty($ip)) {
            if (str_contains($ip, ':')) {
                $parts = explode(':', $ip);
                $normalizedIp = implode(':', array_slice($parts, 0, 4));
            } else {
                $parts = explode('.', $ip);
                if (count($parts) === 4) {
                    $normalizedIp = implode('.', array_slice($parts, 0, 3));
                } else {
                    $normalizedIp = $ip;
                }
            }
        }

        return hash('sha256', $normalizedIp . '|' . ($userAgent ?? ''));
    }
}
