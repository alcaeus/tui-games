<?php

declare(strict_types=1);

namespace Alcaeus\TuiGames\Tests\Snake;

use Alcaeus\TuiGames\Core\Frame;
use Alcaeus\TuiGames\Core\Size;
use Alcaeus\TuiGames\Snake\Point;
use Alcaeus\TuiGames\Snake\SnakeGame;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function array_map;
use function mb_str_split;
use function mb_substr;
use function mb_substr_count;
use function preg_replace;
use function str_repeat;

final class SnakeGameTest extends TestCase
{
    private const float MOVE_INTERVAL = 0.15;

    private function boardSize(): Size
    {
        return new Size(9, 6); // grid: 7 columns x 3 rows
    }

    public function testInitialRenderShowsExactlyOneSnakeAndOneFoodWithinBounds(): void
    {
        $game = new SnakeGame();
        $game->mount($this->boardSize());

        $frame = $game->render();

        self::assertSame('Score: 0' . str_repeat(' ', $this->boardSize()->columns - 8), $frame->lines[0]);
        self::assertSame(3, $this->countCells($frame, '█'));
        self::assertSame(1, $this->countCells($frame, '●'));
    }

    public function testTickAdvancesTheSnakeOneCellInTheCurrentDirection(): void
    {
        $game = new SnakeGame();
        $game->mount($this->boardSize());
        // Head starts at (3, 1), moving right; move it somewhere food can't interfere.
        $this->setFood($game, new Point(0, 0));

        $game->tick(self::MOVE_INTERVAL);

        $frame = $game->render();
        self::assertSame([false, false, true, true, true, false, false], $this->row($frame, 1));
        self::assertSame(3, $this->countCells($frame, '█'));
    }

    public function testEatingFoodIncreasesScoreAndGrowsTheSnake(): void
    {
        $game = new SnakeGame();
        $game->mount($this->boardSize());
        // Head starts at (3, 1); place food directly in its path.
        $this->setFood($game, new Point(4, 1));

        $game->tick(self::MOVE_INTERVAL);

        $frame = $game->render();
        self::assertSame('Score: 1' . str_repeat(' ', $this->boardSize()->columns - 8), $frame->lines[0]);
        self::assertSame(4, $this->countCells($frame, '█'));
    }

    public function testHittingTheWallEndsTheGame(): void
    {
        // Wide enough that the "GAME OVER" status text isn't truncated.
        $size = new Size(25, 6);
        $game = new SnakeGame();
        $game->mount($size);
        $this->setFood($game, new Point(0, 0));

        // Grid is 23 wide; head starts at x=11, needs 12 steps right to hit the wall (x=23 is out of bounds).
        for ($i = 0; $i < 12; ++$i) {
            $game->tick(self::MOVE_INTERVAL);
        }

        $frame = $game->render();
        self::assertStringContainsString('GAME OVER', $frame->lines[0]);
    }

    public function testTurningNinetyDegreesChangesTheSnakesPath(): void
    {
        $game = new SnakeGame();
        $game->mount($this->boardSize());
        $this->setFood($game, new Point(0, 0));

        $game->handleInput('s'); // bound to move_down
        $game->tick(self::MOVE_INTERVAL);

        $frame = $game->render();
        // Head moved from (3, 1) to (3, 2); the rest of the body stayed on row 1.
        self::assertSame([false, false, false, true, false, false, false], $this->row($frame, 2));
        self::assertSame([false, false, true, true, false, false, false], $this->row($frame, 1));
    }

    public function test180DegreeReversalIsIgnored(): void
    {
        $game = new SnakeGame();
        $game->mount($this->boardSize());
        $this->setFood($game, new Point(0, 0));

        // Snake moves right; pressing left would reverse into its own neck.
        $game->handleInput('a'); // bound to move_left
        $game->tick(self::MOVE_INTERVAL);

        $frame = $game->render();
        // Head still moved right, to (4, 1), not left into the body.
        self::assertSame([false, false, true, true, true, false, false], $this->row($frame, 1));
        self::assertStringNotContainsString('GAME OVER', $frame->lines[0]);
    }

    public function testMinimumSizeIsDeclared(): void
    {
        $game = new SnakeGame();

        self::assertNotNull($game->minimumSize());
    }

    private function setFood(SnakeGame $game, Point $point): void
    {
        $property = new ReflectionProperty(SnakeGame::class, 'food');
        $property->setValue($game, $point);
    }

    /** @return list<bool> whether each column in row $y contains a snake cell */
    private function row(Frame $frame, int $y): array
    {
        $line     = $this->stripAnsi($frame->lines[2 + $y]);
        $interior = mb_substr($line, 1, -1);
        $chars    = mb_str_split($interior);

        return array_map(static fn (string $char): bool => $char === '█', $chars);
    }

    private function countCells(Frame $frame, string $needle): int
    {
        $count = 0;

        foreach ($frame->lines as $line) {
            $count += mb_substr_count($this->stripAnsi($line), $needle);
        }

        return $count;
    }

    private function stripAnsi(string $line): string
    {
        return preg_replace('/\x1b\[[0-9;]*m/', '', $line);
    }
}
