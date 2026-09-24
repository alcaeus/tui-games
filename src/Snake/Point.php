<?php

declare(strict_types=1);

namespace Alcaeus\TuiGames\Snake;

final readonly class Point
{
    public function __construct(
        public int $x,
        public int $y,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }

    public function moved(Direction $direction): self
    {
        return match ($direction) {
            Direction::Up => new self($this->x, $this->y - 1),
            Direction::Down => new self($this->x, $this->y + 1),
            Direction::Left => new self($this->x - 1, $this->y),
            Direction::Right => new self($this->x + 1, $this->y),
        };
    }
}
