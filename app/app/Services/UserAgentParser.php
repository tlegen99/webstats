<?php

namespace App\Services;

use DeviceDetector\DeviceDetector;

class UserAgentParser
{
    /**
     * @return array{browser: string, os: string, device: string}|null
     *
     * @throws \Exception
     */
    public function parse(string $userAgent): ?array
    {
        if ($userAgent === '') {
            return null;
        }

        $dd = new DeviceDetector($userAgent);
        $dd->parse();

        if ($dd->isBot()) {
            return null;
        }

        if ($dd->getClient('type') === 'library') {
            return null;
        }

        $device = match ($dd->getDeviceName()) {
            'smartphone', 'phablet', 'feature phone' => 'mobile',
            'tablet' => 'tablet',
            default => 'desktop',
        };
        $browser = $dd->getClient('name');
        $os = $dd->getOs('name');

        return [
            'browser' => is_string($browser) ? $browser : '',
            'os' => is_string($os) ? $os : '',
            'device' => $device,
        ];
    }
}
