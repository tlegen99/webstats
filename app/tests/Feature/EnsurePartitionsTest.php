<?php

use Illuminate\Support\Facades\DB;

test('command creates partition for current month', function () {
    $this->travelTo('2026-10-14 12:00:00');

    $this->artisan('partitions:ensure')->assertSuccessful();

    $exists = DB::selectOne("SELECT to_regclass('events_2026_10') AS name")->name;

    expect($exists)->toBe('events_2026_10');
});

test('command creates partition for next month', function () {
    $this->travelTo('2026-10-14 12:00:00');

    $this->artisan('partitions:ensure')->assertSuccessful();

    $exists = DB::selectOne("SELECT to_regclass('events_2026_11') AS name")->name;

    expect($exists)->toBe('events_2026_11');
});

test('command creates partition for next year', function () {
    $this->travelTo('2026-12-20 12:00:00');

    $this->artisan('partitions:ensure')->assertSuccessful();

    $exists = DB::selectOne("SELECT to_regclass('events_2027_01') AS name")->name;

    expect($exists)->toBe('events_2027_01');
});
