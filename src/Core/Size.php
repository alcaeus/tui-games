<?php

declare(strict_types=1);

namespace Alcaeus\TuiGames\Core;

/**
 * A bounding box in terminal character cells.
 */
final readonly class Size
{
    public function __construct(
        public int $columns,
        public int $rows,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->columns === $other->columns
            && $this->rows === $other->rows;
    }

    public function fits(self $minimum): bool
    {
        return $this->columns >= $minimum->columns
            && $this->rows >= $minimum->rows;
    }
}
