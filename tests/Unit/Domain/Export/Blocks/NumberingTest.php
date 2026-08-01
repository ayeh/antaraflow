<?php

declare(strict_types=1);

use App\Domain\Export\Blocks\Numbering;

// ── next() generates correct depth-0 strings ──────────────────────────────────

it('generates depth-0 numbers with .0 suffix', function (): void {
    $n = new Numbering;

    expect($n->next(0))->toBe('1.0');
    expect($n->next(0))->toBe('2.0');
    expect($n->next(0))->toBe('3.0');
});

// ── depth-1 inherits parent counter ──────────────────────────────────────────

it('generates depth-1 numbers relative to current depth-0', function (): void {
    $n = new Numbering;

    $n->next(0); // 1.0
    expect($n->next(1))->toBe('1.1');
    expect($n->next(1))->toBe('1.2');

    $n->next(0); // 2.0
    expect($n->next(1))->toBe('2.1');
});

// ── depth-2 generates roman numerals ─────────────────────────────────────────

it('generates roman numerals at depth 2', function (int $call, string $expected): void {
    $n = new Numbering;
    $n->next(0);
    $n->next(1);

    foreach (range(1, $call) as $i) {
        $result = $n->next(2);
    }

    expect($result)->toBe($expected);
})->with([
    'i' => [1, 'i)'],
    'ii' => [2, 'ii)'],
    'iv' => [4, 'iv)'],
    'viii' => [8, 'viii)'],
    'ix' => [9, 'ix)'],
]);

// ── depth-3 generates letters ─────────────────────────────────────────────────

it('generates letters at depth 3', function (int $call, string $expected): void {
    $n = new Numbering;
    $n->next(0);
    $n->next(1);
    $n->next(2);

    foreach (range(1, $call) as $i) {
        $result = $n->next(3);
    }

    expect($result)->toBe($expected);
})->with([
    'a' => [1, 'a)'],
    'b' => [2, 'b)'],
    'z' => [26, 'z)'],
]);

// ── deeper entries reset when parent advances ─────────────────────────────────

it('resets deeper counters when a shallower depth advances', function (): void {
    $n = new Numbering;

    $n->next(0); // 1.0
    $n->next(1); // 1.1
    $n->next(2); // i)
    $n->next(3); // a)

    $n->next(1); // 1.2 — should reset depth 2 and 3

    expect($n->next(2))->toBe('i)');   // back to i), not ii)
    expect($n->next(3))->toBe('a)');   // back to a)
});

// ── reset() clears all counters ───────────────────────────────────────────────

it('can reset all counters', function (): void {
    $n = new Numbering;

    $n->next(0);
    $n->next(0);
    $n->next(1);
    $n->reset();

    expect($n->next(0))->toBe('1.0');
    expect($n->next(1))->toBe('1.1');
});

// ── depth ≥ 4 emits a dash indent ─────────────────────────────────────────────

it('returns dash for depth 4 and deeper', function (): void {
    $n = new Numbering;

    // depth 4 → str_repeat(' ', 4-3) = ' -'
    expect($n->next(4))->toBe(' -');
    // depth 5 → str_repeat(' ', 5-3) = '  -'
    expect($n->next(5))->toBe('  -');
});
