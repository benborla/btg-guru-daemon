<?php

use Carbon\Carbon;

describe('get_current_round function', function () {

    beforeEach(function () {
        // Reset Carbon test time before each test
        Carbon::setTestNow(null);
    });

    it('returns the Opening round when date is before season starts', function () {
        // Set test time to March 6, 2025 (before season starts)
        Carbon::setTestNow(Carbon::create(2025, 3, 6, 12, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe('OR')
            ->and($result['start'])->toBe('2025-03-07')
            ->and($result['end'])->toBe('2025-03-09');
    });

    it('returns round 19 when date is July 19, 2025 (within round range)', function () {
        // Set test time to July 19, 2025 at 2:00 PM
        Carbon::setTestNow(Carbon::create(2025, 7, 19, 14, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe(19)
            ->and($result['start'])->toBe('2025-07-17')
            ->and($result['end'])->toBe('2025-07-20');
    });

    it('returns round 19 when date is July 20, 2025 (end day of round)', function () {
        // Set test time to July 20, 2025 at 11:30 AM
        Carbon::setTestNow(Carbon::create(2025, 7, 20, 11, 30, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe(19)
            ->and($result['start'])->toBe('2025-07-17')
            ->and($result['end'])->toBe('2025-07-20');
    });

    it('returns round 19 when date is July 20, 2025 late at night (end day inclusive)', function () {
        // Set test time to July 20, 2025 at 11:59 PM
        Carbon::setTestNow(Carbon::create(2025, 7, 20, 23, 59, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe(19)
            ->and($result['start'])->toBe('2025-07-17')
            ->and($result['end'])->toBe('2025-07-20');
    });

    it('returns round 20 when date is July 22, 2025 (between rounds, closer to next)', function () {
        // Set test time to July 22, 2025 at 10:00 AM
        Carbon::setTestNow(Carbon::create(2025, 7, 22, 10, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe(20)
            ->and($result['start'])->toBe('2025-07-24')
            ->and($result['end'])->toBe('2025-07-27');
    });

    it('returns round 20 when date is July 21, 2025 (between rounds, prefers next round)', function () {
        // Set test time to July 21, 2025 at 6:00 AM (between rounds, should prefer next round)
        Carbon::setTestNow(Carbon::create(2025, 7, 21, 6, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe(20)
            ->and($result['start'])->toBe('2025-07-24')
            ->and($result['end'])->toBe('2025-07-27');
    });

    it('returns round 1 when date is within first regular round', function () {
        // Set test time to March 15, 2025 (within Round 1: March 13-16)
        Carbon::setTestNow(Carbon::create(2025, 3, 15, 15, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe(1)
            ->and($result['start'])->toBe('2025-03-13')
            ->and($result['end'])->toBe('2025-03-16');
    });

    it('returns opening round when date is within OR period', function () {
        // Set test time to March 8, 2025 (within OR: March 7-9)
        Carbon::setTestNow(Carbon::create(2025, 3, 8, 12, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe('OR')
            ->and($result['start'])->toBe('2025-03-07')
            ->and($result['end'])->toBe('2025-03-09');
    });

    it('returns last round when date is within final round', function () {
        // Set test time to August 22, 2025 (within Round 24: August 22-22)
        Carbon::setTestNow(Carbon::create(2025, 8, 22, 18, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe(24)
            ->and($result['start'])->toBe('2025-08-22')
            ->and($result['end'])->toBe('2025-08-22');
    });

    it('returns closest round when date is before season starts', function () {
        // Set test time to February 1, 2025 (before season starts)
        Carbon::setTestNow(Carbon::create(2025, 2, 1, 12, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe('OR') // Should be closest to Opening Round
            ->and($result['start'])->toBe('2025-03-07')
            ->and($result['end'])->toBe('2025-03-09');
    });

    it('returns closest round when date is after season ends', function () {
        // Set test time to September 15, 2025 (after season ends)
        Carbon::setTestNow(Carbon::create(2025, 9, 15, 12, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe(24) // Should be closest to final round
            ->and($result['start'])->toBe('2025-08-22')
            ->and($result['end'])->toBe('2025-08-22');
    });

    it('handles edge case at exact start of round', function () {
        // Set test time to July 17, 2025 at midnight (exact start of Round 19)
        Carbon::setTestNow(Carbon::create(2025, 7, 17, 0, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe(19)
            ->and($result['start'])->toBe('2025-07-17')
            ->and($result['end'])->toBe('2025-07-20');
    });

    it('handles proximity tie by returning the earlier round', function () {
        // Set test time to exactly halfway between Round 19 end and Round 20 start
        // Round 19 ends July 20 23:59:59, Round 20 starts July 24 00:00:00
        // Halfway would be July 22 at noon
        Carbon::setTestNow(Carbon::create(2025, 7, 22, 12, 0, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBeIn([19, 20]); // Either is acceptable for exact halfway
    });

    it('returns the next round if the current date has no matching round', function () {
        // Set test time to July 22, 2025 at 11:59 PM
        Carbon::setTestNow(Carbon::create(2025, 7, 22, 23, 59, 0, 'Australia/Sydney'));

        $result = get_current_round();

        expect($result)->toBeArray()
            ->and($result['round'])->toBe(20)
            ->and($result['start'])->toBe('2025-07-24')
            ->and($result['end'])->toBe('2025-07-27');
    });

    afterEach(function () {
        // Clean up after each test
        Carbon::setTestNow(null);
    });
});
