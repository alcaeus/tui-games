<?php

declare(strict_types=1);

namespace Alcaeus\TuiGames\Snake;

use Alcaeus\TuiGames\Core\Frame;
use Alcaeus\TuiGames\Core\Game;
use Alcaeus\TuiGames\Core\Size;
use Random\Randomizer;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Style\Color;

use function array_pad;
use function array_pop;
use function array_slice;
use function array_unshift;
use function count;
use function intdiv;
use function max;
use function sprintf;
use function str_pad;
use function str_repeat;
use function strlen;
use function substr;

/**
 * Classic Nokia-style Snake, played on a grid inside a bordered box with a
 * one-line score/status header.
 */
final class SnakeGame implements Game
{
    private const float INITIAL_MOVE_INTERVAL       = 0.15;
    private const float MINIMUM_MOVE_INTERVAL       = 0.06;
    private const float MOVE_INTERVAL_STEP_PER_FOOD = 0.003;
    private const int MAX_STEPS_PER_TICK            = 5;

    private readonly Keybindings $keybindings;

    private Size $size;
    private int $gridWidth  = 0;
    private int $gridHeight = 0;

    /** @var list<Point> */
    private array $snake = [];

    private Direction $direction             = Direction::Right;
    private Direction|null $pendingDirection = null;

    private Point $food;
    private int $score              = 0;
    private bool $gameOver          = false;
    private float $elapsedSinceMove = 0.0;

    public function __construct(
        private readonly Randomizer $randomizer = new Randomizer(),
    ) {
        $this->keybindings = new Keybindings([
            'move_up' => [Key::UP, 'w'],
            'move_down' => [Key::DOWN, 's'],
            'move_left' => [Key::LEFT, 'a'],
            'move_right' => [Key::RIGHT, 'd'],
        ]);
    }

    public function minimumSize(): Size|null
    {
        return new Size(24, 14);
    }

    public function mount(Size $size): void
    {
        $this->size       = $size;
        $this->gridWidth  = max(0, $size->columns - 2);
        $this->gridHeight = max(0, $size->rows - 3);

        $this->resetBoard();
    }

    public function tick(float $deltaTime): void
    {
        if ($this->gameOver || $this->gridWidth < 3 || $this->gridHeight < 3) {
            return;
        }

        $this->elapsedSinceMove += $deltaTime;
        $moveInterval            = $this->currentMoveInterval();

        $steps = 0;
        while (! $this->gameOver && $this->elapsedSinceMove >= $moveInterval && $steps < self::MAX_STEPS_PER_TICK) {
            $this->step();
            $this->elapsedSinceMove -= $moveInterval;
            $moveInterval            = $this->currentMoveInterval();
            ++$steps;
        }
    }

    public function handleInput(string $key): void
    {
        if ($this->gameOver) {
            return;
        }

        $keybindings = $this->keybindings;

        $turn = match (true) {
            $keybindings->matches($key, 'move_up') => Direction::Up,
            $keybindings->matches($key, 'move_down') => Direction::Down,
            $keybindings->matches($key, 'move_left') => Direction::Left,
            $keybindings->matches($key, 'move_right') => Direction::Right,
            default => null,
        };

        if ($turn === null) {
            return;
        }

        // Ignore a 180-degree reversal: it would collide with the neck.
        if (count($this->snake) > 1 && $turn === $this->direction->opposite()) {
            return;
        }

        $this->pendingDirection = $turn;
    }

    public function render(): Frame
    {
        $lines   = [];
        $lines[] = $this->renderStatusLine();

        if ($this->gridWidth < 3 || $this->gridHeight < 3) {
            return new Frame(array_pad($lines, $this->size->rows, str_repeat(' ', $this->size->columns)));
        }

        $lines[] = '┌' . str_repeat('─', $this->gridWidth) . '┐';

        for ($y = 0; $y < $this->gridHeight; ++$y) {
            $row = '│';
            for ($x = 0; $x < $this->gridWidth; ++$x) {
                $row .= $this->renderCell(new Point($x, $y));
            }

            $row    .= '│';
            $lines[] = $row;
        }

        $lines[] = '└' . str_repeat('─', $this->gridWidth) . '┘';

        return new Frame($lines);
    }

    public function unmount(): void
    {
    }

    private function renderStatusLine(): string
    {
        $text = sprintf('Score: %d', $this->score);

        if ($this->gameOver) {
            $text .= '  GAME OVER';
        }

        if (strlen($text) > $this->size->columns) {
            $text = substr($text, 0, $this->size->columns);
        }

        return str_pad($text, $this->size->columns);
    }

    private function renderCell(Point $point): string
    {
        if ($point->equals($this->food)) {
            return Color::named('red')->toForegroundCode() . '●' . Color::resetForeground();
        }

        foreach ($this->snake as $segment) {
            if ($segment->equals($point)) {
                return Color::named('green')->toForegroundCode() . '█' . Color::resetForeground();
            }
        }

        return ' ';
    }

    private function resetBoard(): void
    {
        $this->gameOver         = false;
        $this->score            = 0;
        $this->elapsedSinceMove = 0.0;
        $this->direction        = Direction::Right;
        $this->pendingDirection = null;

        if ($this->gridWidth < 3 || $this->gridHeight < 3) {
            $this->snake = [];

            return;
        }

        $startX = intdiv($this->gridWidth, 2);
        $startY = intdiv($this->gridHeight, 2);

        $this->snake = [
            new Point($startX, $startY),
            new Point($startX - 1, $startY),
            new Point($startX - 2, $startY),
        ];

        $this->spawnFood();
    }

    private function step(): void
    {
        if ($this->pendingDirection !== null) {
            $this->direction        = $this->pendingDirection;
            $this->pendingDirection = null;
        }

        $newHead = $this->snake[0]->moved($this->direction);

        if ($this->collidesWithWall($newHead) || $this->collidesWithBody($newHead)) {
            $this->gameOver = true;

            return;
        }

        array_unshift($this->snake, $newHead);

        if ($newHead->equals($this->food)) {
            ++$this->score;
            $this->spawnFood();
        } else {
            array_pop($this->snake);
        }
    }

    private function collidesWithWall(Point $point): bool
    {
        return $point->x < 0 || $point->x >= $this->gridWidth
            || $point->y < 0 || $point->y >= $this->gridHeight;
    }

    private function collidesWithBody(Point $point): bool
    {
        // The tail vacates its cell in the same step unless the snake is
        // growing, and food never spawns on the snake, so excluding the
        // tail here is always correct.
        $body = array_slice($this->snake, 0, -1);

        foreach ($body as $segment) {
            if ($segment->equals($point)) {
                return true;
            }
        }

        return false;
    }

    private function spawnFood(): void
    {
        $emptyCells = [];

        for ($y = 0; $y < $this->gridHeight; ++$y) {
            for ($x = 0; $x < $this->gridWidth; ++$x) {
                $candidate = new Point($x, $y);
                $occupied  = false;

                foreach ($this->snake as $segment) {
                    if ($segment->equals($candidate)) {
                        $occupied = true;
                        break;
                    }
                }

                if ($occupied) {
                    continue;
                }

                $emptyCells[] = $candidate;
            }
        }

        if ($emptyCells === []) {
            $this->gameOver = true;

            return;
        }

        $this->food = $emptyCells[$this->randomizer->getInt(0, count($emptyCells) - 1)];
    }

    private function currentMoveInterval(): float
    {
        return max(
            self::MINIMUM_MOVE_INTERVAL,
            self::INITIAL_MOVE_INTERVAL - $this->score * self::MOVE_INTERVAL_STEP_PER_FOOD,
        );
    }
}
