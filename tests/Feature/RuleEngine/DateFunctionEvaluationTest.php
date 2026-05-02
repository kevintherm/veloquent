<?php

use App\Domain\RuleEngine\RuleEngine;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-05-02 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('evaluates simple date functions', function ($expression, $expected) {
    $engine = RuleEngine::make(['created_at']);
    $context = ['created_at' => $expected];

    expect($engine->evaluate("created_at = $expression", $context))->toBeTrue();
})->with([
    ['now()', '2026-05-02 12:00:00'],
    ['today()', '2026-05-02'],
    ['yesterday()', '2026-05-01'],
    ['tomorrow()', '2026-05-03'],
    ['thisweek()', '2026-04-27'], // 2026-05-02 is Saturday, Monday is 2026-04-27
    ['lastweek()', '2026-04-20'],
    ['nextweek()', '2026-05-04'],
    ['thismonth()', '2026-05-01'],
    ['lastmonth()', '2026-04-01'],
    ['nextmonth()', '2026-06-01'],
    ['thisyear()', '2026-01-01'],
    ['lastyear()', '2025-01-01'],
    ['nextyear()', '2027-01-01'],
    ['startofday()', '2026-05-02 00:00:00'],
    ['endofday()', '2026-05-02 23:59:59'],
]);

it('evaluates parameterized date functions', function ($expression, $expected) {
    $engine = RuleEngine::make(['created_at']);
    $context = ['created_at' => $expected];

    expect($engine->evaluate("created_at = $expression", $context))->toBeTrue();
})->with([
    ['daysago(5)', '2026-04-27 12:00:00'],
    ['daysfromnow(5)', '2026-05-07 12:00:00'],
    ['weeksago(2)', '2026-04-18 12:00:00'],
    ['weeksfromnow(2)', '2026-05-16 12:00:00'],
    ['monthsago(3)', '2026-02-02 12:00:00'],
    ['monthsfromnow(3)', '2026-08-02 12:00:00'],
    ['yearsago(1)', '2025-05-02 12:00:00'],
    ['yearsfromnow(1)', '2027-05-02 12:00:00'],
]);

it('combines date functions with operators', function () {
    $engine = RuleEngine::make(['created_at']);

    // created_at was 5 days ago
    $context = ['created_at' => '2026-04-27 12:00:00'];

    expect($engine->evaluate('created_at >= daysago(10)', $context))->toBeTrue();
    expect($engine->evaluate('created_at <= daysago(2)', $context))->toBeTrue();
    expect($engine->evaluate('created_at > daysago(1)', $context))->toBeFalse();
});
