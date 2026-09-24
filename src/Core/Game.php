<?php

declare(strict_types=1);

namespace Alcaeus\TuiGames\Core;

/**
 * Contract for a mini-game that can be embedded as a widget in a host TUI
 * application. A Game knows nothing about why the user is waiting, how big
 * the terminal is beyond the bounding box it's given, or which rendering
 * framework is hosting it - all of that is the host's/adapter's concern.
 */
interface Game
{
    /**
     * Called once the game is attached, and again whenever the bounding box
     * it's rendered into changes (e.g. the host terminal was resized).
     */
    public function mount(Size $size): void;

    /**
     * Advance game state by $deltaTime seconds. Called once per host tick
     * while the game is on screen.
     */
    public function tick(float $deltaTime): void;

    /**
     * Handle a raw key input event. $key is the raw byte sequence as
     * delivered by the host's input layer; implementations built on
     * symfony/tui can match it via Symfony\Component\Tui\Input\Keybindings.
     */
    public function handleInput(string $key): void;

    /**
     * Produce the current frame to display. Frame::$lines must contain
     * exactly as many rows as the Size last passed to mount(), each no
     * wider than that Size's columns.
     */
    public function render(): Frame;

    /**
     * Called once the game is removed from the host's layout.
     */
    public function unmount(): void;

    /**
     * The smallest Size this game can be usefully played in, or null if it
     * has no minimum and will do its best at any size.
     */
    public function minimumSize(): Size|null;
}
