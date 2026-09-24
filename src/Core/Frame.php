<?php

declare(strict_types=1);

namespace Alcaeus\TuiGames\Core;

/**
 * The visual content a Game produces for one render pass: one string per
 * terminal row, ANSI escapes allowed, matching the Size it was asked to
 * render into.
 */
final readonly class Frame
{
    /** @param list<string> $lines */
    public function __construct(
        public array $lines,
    ) {
    }
}
