# tui-games

A collection of CLI mini-games for embedding into [symfony/tui](https://github.com/symfony/tui)
applications — something for a user to play while a real background task
(a build, a deploy, a test suite) runs.

Games are decoupled from *why* the user is waiting: the host owns the actual
background process and drives the game's tick loop; the game only knows
about the bounding box it's given and the input it receives.

> `symfony/tui` is experimental (no BC promise yet), so treat this package
> the same way for now.

## Installation

```bash
composer require alcaeus/tui-games
```

## The `Game` contract

Every game implements `Alcaeus\TuiGames\Core\Game`, a small,
framework-agnostic interface:

```php
interface Game
{
    public function mount(Size $size): void;
    public function tick(float $deltaTime): void;
    public function handleInput(string $key): void;
    public function render(): Frame;
    public function unmount(): void;
    public function minimumSize(): ?Size;
}
```

- `mount()` is called once when the game is attached, and again whenever its
  bounding box changes (e.g. a terminal resize).
- `tick()` advances game state by `$deltaTime` seconds; the host calls this
  once per frame.
- `handleInput()` receives the raw byte sequence for a key press; games
  built on `symfony/tui` typically match it via
  `Symfony\Component\Tui\Input\Keybindings`.
- `render()` returns a `Frame` (a list of row strings) matching the last
  `Size` passed to `mount()`.
- `minimumSize()` lets a game refuse to run below a size it needs; return
  `null` if it has no minimum.

`Game` implementations have no dependency on `symfony/tui` and can be unit
tested in isolation.

## Embedding a game in a symfony/tui app

`Alcaeus\TuiGames\Core\GameWidget` adapts any `Game` into a `symfony/tui`
widget:

```php
use Alcaeus\TuiGames\Core\GameWidget;
use Alcaeus\TuiGames\Snake\SnakeGame;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Tui;

$snake = new GameWidget(new SnakeGame());

$tui = new Tui();
$tui->add($snake);
$tui->setFocus($snake);

$tui->onTick(function (TickEvent $event) use ($snake): void {
    $snake->update($event->getDeltaTime());
    $event->setBusy(true); // keep ticking fast while the game is animating
});

$tui->run();
```

`GameWidget` handles remounting the game when its size changes, and falls
back to a "terminal too small" message if the available space is below the
game's `minimumSize()`.

## Games

### Snake

`Alcaeus\TuiGames\Snake\SnakeGame` — classic Nokia-style Snake.

- **Controls:** arrow keys or `W`/`A`/`S`/`D`.
- **Rules:** eat food to grow and score; hitting a wall or yourself ends the
  game; the snake speeds up slightly as your score grows; reversing directly
  into yourself is ignored, not fatal.
- **Minimum size:** 24×14 cells.

## Demo

`demo/snake-with-task.php` shows the intended usage end-to-end: a real
`Symfony\Component\Process\Process` stands in for a long-running task, with
Snake playable alongside it. When the task finishes, the game loop suspends
and asks whether to keep playing (a bonus round with no time pressure) or
quit.

```bash
composer install
php demo/snake-with-task.php
```

## Development

```bash
composer install
vendor/bin/phpunit      # tests
vendor/bin/phpcs        # coding standard (Doctrine ruleset)
vendor/bin/phpcbf       # auto-fix what phpcs can
```
