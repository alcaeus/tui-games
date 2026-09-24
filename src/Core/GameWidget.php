<?php

declare(strict_types=1);

namespace Alcaeus\TuiGames\Core;

use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;

use function array_fill;
use function intdiv;
use function mb_strlen;
use function mb_substr;
use function sprintf;
use function str_repeat;

/**
 * Adapts a framework-agnostic Game to a symfony/tui widget. This is the
 * only class in the library coupled to symfony/tui's widget contract.
 */
final class GameWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    private Size|null $lastSize = null;

    public function __construct(
        private readonly Game $game,
    ) {
    }

    /**
     * Advances the wrapped game's state. Call this from the host's
     * Tui::onTick() callback once per frame.
     */
    public function update(float $deltaTime): void
    {
        $this->game->tick($deltaTime);
        $this->invalidate();
    }

    public function handleInput(string $data): void
    {
        $this->game->handleInput($data);
        $this->invalidate();
    }

    protected function onDetach(): void
    {
        $this->game->unmount();
    }

    /** @return list<string> */
    public function render(RenderContext $context): array
    {
        $size = new Size($context->getColumns(), $context->getRows());

        if ($this->lastSize === null || ! $this->lastSize->equals($size)) {
            $this->game->mount($size);
            $this->lastSize = $size;
        }

        $minimumSize = $this->game->minimumSize();
        if ($minimumSize !== null && ! $size->fits($minimumSize)) {
            return $this->renderTooSmallMessage($size, $minimumSize);
        }

        return $this->game->render()->lines;
    }

    /** @return list<string> */
    private function renderTooSmallMessage(Size $size, Size $minimumSize): array
    {
        $message = sprintf(
            "Terminal too small (needs %d\u{00D7}%d, have %d\u{00D7}%d)",
            $minimumSize->columns,
            $minimumSize->rows,
            $size->columns,
            $size->rows,
        );

        $lines = array_fill(0, $size->rows, str_repeat(' ', $size->columns));

        if ($size->rows > 0) {
            $centerRow         = intdiv($size->rows, 2);
            $lines[$centerRow] = $this->centerText($message, $size->columns);
        }

        return $lines;
    }

    private function centerText(string $text, int $columns): string
    {
        $length = mb_strlen($text);

        if ($length > $columns) {
            $text   = mb_substr($text, 0, $columns);
            $length = $columns;
        }

        $left  = intdiv($columns - $length, 2);
        $right = $columns - $length - $left;

        return str_repeat(' ', $left) . $text . str_repeat(' ', $right);
    }
}
