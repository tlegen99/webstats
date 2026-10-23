<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class IngestEventController
{
    public function __invoke(Request $request): Response|JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return response()->json(['message' => 'Invalid JSON'], 400);
        }

        $validator = Validator::make($data, [
            's' => ['required', 'string', 'max:32'],
            'n' => ['required', 'string', 'max:64'],
            'u' => ['required', 'string', 'max:2048'],
            'r' => ['nullable', 'string', 'max:2048'],
            'w' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'e' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'd' => ['nullable', 'integer', 'min:0', 'max:100'],
            'p' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $site = Site::where('public_key', $data['s'])->first();
        if (! $site) {
            return response()->noContent(202);
        }

        $url = parse_url($data['u']);
        $host = is_array($url) ? ($url['host'] ?? null) : null;
        if ($host === null || $host !== $site->domain) {
            return response()->noContent(202);
        }

        parse_str($url['query'] ?? '', $query);

        $salt = config('app.key').now()->utc()->toDateString();
        $hash = hash('sha256', $salt.$site->id.$request->ip().($request->userAgent() ?? ''), true);
        $unpacked = unpack('J', substr($hash, 0, 8));
        $visitorId = $unpacked === false ? 0 : $unpacked[1];

        $utm = fn (string $key): string => is_string($query[$key] ?? null)
            ? mb_substr($query[$key], 0, 255)
            : '';

        DB::table('events')->insert([
            'event_id' => Str::uuid7(),
            'site_id' => $site->id,
            'ts' => now()->format('Y-m-d H:i:s.uP'),
            'name' => $data['n'],
            'visitor_id' => $visitorId,
            'session_id' => Str::uuid7(),
            'hostname' => $host,
            'pathname' => $url['path'] ?? '/',
            'referrer_host' => parse_url($data['r'] ?? '', PHP_URL_HOST) ?: '',
            'utm_source' => $utm('utm_source'),
            'utm_medium' => $utm('utm_medium'),
            'utm_campaign' => $utm('utm_campaign'),
            'screen_w' => $data['w'] ?? null,
            'engagement_ms' => $data['e'] ?? 0,
            'scroll_depth' => $data['d'] ?? 0,
            'props' => empty($data['p']) ? '{}' : json_encode($data['p']),
        ]);

        return response()->noContent(202);
    }
}
