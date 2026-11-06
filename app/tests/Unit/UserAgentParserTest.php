<?php

use App\Services\UserAgentParser;

it('detects device type', function (string $userAgent, string $device) {
    $result = (new UserAgentParser)->parse($userAgent);

    expect($result)->not->toBeNull()
        ->and($result['device'])->toBe($device);
})->with([
    'chrome on mac' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        'desktop',
    ],
    'firefox on windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0',
        'desktop',
    ],
    'safari on iphone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        'mobile',
    ],
    'chrome on android' => [
        'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36',
        'mobile',
    ],
    'safari on ipad' => [
        'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        'tablet',
    ],
]);

it('detects browser and os', function () {
    $result = (new UserAgentParser)->parse(
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'
    );

    expect($result)->toBe([
        'browser' => 'Chrome',
        'os' => 'Mac',
        'device' => 'desktop',
    ]);
});

it('returns null for bots and scripts', function (string $userAgent) {
    expect((new UserAgentParser)->parse($userAgent))->toBeNull();
})->with([
    'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
    'bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'],
    'curl' => ['curl/8.4.0'],
    'python requests' => ['python-requests/2.32.3'],
    'empty string' => [''],
]);
