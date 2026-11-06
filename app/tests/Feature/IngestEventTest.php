<?php

use App\Models\Site;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('partitions:ensure');
    $this->site = Site::factory()->create(['domain' => 'example.com']);
});

function sendEvent(array|string $payload, array $server = [])
{
    $body = is_array($payload) ? json_encode($payload) : $payload;

    return test()->call(
        'POST',
        '/api/event',
        [],
        [],
        [],
        array_merge(['CONTENT_TYPE' => 'text/plain', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'], $server),
        $body
    );
}

it('stores a pageview', function () {
    sendEvent([
        's' => $this->site->public_key,
        'n' => 'pageview',
        'u' => 'https://example.com/pricing?utm_source=google&id=5',
        'r' => 'https://google.com/search',
        'w' => 1440,
    ])->assertStatus(202);

    $event = DB::table('events')->first();

    expect($event->site_id)->toBe($this->site->id)
        ->and($event->name)->toBe('pageview')
        ->and($event->pathname)->toBe('/pricing')
        ->and($event->utm_source)->toBe('google')
        ->and($event->referrer_host)->toBe('google.com')
        ->and($event->screen_w)->toBe(1440)
        ->and($event->browser)->toBe('Chrome')
        ->and($event->os)->toBe('Mac')
        ->and($event->device)->toBe('desktop');
});

it('rejects invalid json', function () {
    sendEvent('not json')->assertStatus(400);

    expect(DB::table('events')->count())->toBe(0);
});

it('rejects event without name', function () {
    sendEvent([
        's' => $this->site->public_key,
        'u' => 'https://example.com/pricing?utm_source=google&id=5',
        'r' => 'https://google.com/search',
        'w' => 1440,
    ])->assertStatus(400);

    expect(DB::table('events')->count())->toBe(0);
});

it('ignores unknown site key', function () {
    sendEvent(['s' => 'pk_nope', 'n' => 'pageview', 'u' => 'https://example.com/'])
        ->assertStatus(202);

    expect(DB::table('events')->count())->toBe(0);
});

it('ignores event from another domain', function () {
    sendEvent(['s' => $this->site->public_key, 'n' => 'pageview', 'u' => 'https://evil.com/'])
        ->assertStatus(202);

    expect(DB::table('events')->count())->toBe(0);
});

it('stores engagement time and scroll depth', function () {
    sendEvent([
        's' => $this->site->public_key,
        'n' => 'engagement',
        'u' => 'https://example.com/blog',
        'e' => 5000,
        'd' => 75,
    ])->assertStatus(202);

    $event = DB::table('events')->first();

    expect($event->name)->toBe('engagement')
        ->and($event->engagement_ms)->toBe(5000)
        ->and($event->scroll_depth)->toBe(75);
});

it('stores custom event props', function () {
    sendEvent([
        's' => $this->site->public_key,
        'n' => 'signup',
        'u' => 'https://example.com/',
        'p' => ['plan' => 'pro'],
    ])->assertStatus(202);

    $event = DB::table('events')->first();

    expect(json_decode($event->props, true))->toBe(['plan' => 'pro']);
});

it('stores empty object when props are missing', function () {
    sendEvent([
        's' => $this->site->public_key,
        'n' => 'pageview',
        'u' => 'https://example.com/',
    ])->assertStatus(202);

    $event = DB::table('events')->first();

    expect($event->props)->toBe('{}');
});

it('counts the same browser as one visitor', function () {
    $payload = ['s' => $this->site->public_key, 'n' => 'pageview', 'u' => 'https://example.com/'];
    $chrome = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
    $firefox = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0';

    sendEvent($payload, ['HTTP_USER_AGENT' => $chrome])->assertStatus(202);
    sendEvent($payload, ['HTTP_USER_AGENT' => $chrome])->assertStatus(202);
    sendEvent($payload, ['HTTP_USER_AGENT' => $firefox])->assertStatus(202);

    $visitors = DB::table('events')->orderBy('seq')->pluck('visitor_id');

    expect($visitors)->toHaveCount(3)
        ->and($visitors[0])->toBe($visitors[1])
        ->and($visitors[2])->not->toBe($visitors[0]);
});

it('ignores bots', function () {
    sendEvent(
        ['s' => $this->site->public_key, 'n' => 'pageview', 'u' => 'https://example.com/'],
        ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
    )->assertStatus(202);

    expect(DB::table('events')->count())->toBe(0);
});
