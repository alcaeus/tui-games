<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Alcaeus\TuiGames\Core\GameWidget;
use Alcaeus\TuiGames\Snake\SnakeGame;
use Symfony\Component\Process\Process;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

// Stand-in for a real long-running task (a build, a deploy, a test suite).
// The host owns this process entirely; neither the game nor the core
// library know or care why the user is waiting.
$task = new Process(['sh', '-c', 'sleep 12']);
$task->start();

$status = new TextWidget('Running background task...');
$snake  = new GameWidget(new SnakeGame());

$root = new ContainerWidget();
$root->setStyle(new Style(direction: Direction::Vertical));
$root->add($status);
$root->add($snake);

$tui = new Tui();
$tui->add($root);
$tui->setFocus($snake);

// task_running    - background task in progress, snake is playable
// awaiting_decision - task just finished, snake is suspended, waiting on Y/N
// bonus_round     - user chose to keep playing after the task finished
$phase   = 'task_running';
$elapsed = 0.0;

$tui->onTick(static function (TickEvent $event) use ($snake, $status, $task, &$elapsed, &$phase): void {
    if ($phase === 'awaiting_decision') {
        // Game loop is suspended: no tick, no render invalidation, just wait for input.
        return;
    }

    $snake->update($event->getDeltaTime());
    $event->setBusy(true); // keep ticking fast so the game stays animated

    if ($phase !== 'task_running') {
        return;
    }

    $elapsed += $event->getDeltaTime();
    $status->setText(sprintf('Running background task... %.1fs', $elapsed));

    if ($task->isRunning()) {
        return;
    }

    $phase = 'awaiting_decision';
    $status->setText(sprintf(
        'Task finished after %.1fs (exit code %d). Keep playing? [Y]es / [N]o',
        $elapsed,
        $task->getExitCode() ?? -1,
    ));
});

$tui->addListener(static function (InputEvent $event) use ($status, $tui, &$phase): void {
    $key = strtolower($event->getData());

    if ($phase === 'awaiting_decision') {
        if ($key === 'y') {
            $phase = 'bonus_round';
            $status->setText('Bonus round! Press Q to quit.');
            $event->stopPropagation();
        } elseif ($key === 'n') {
            $tui->stop();
            $event->stopPropagation();
        }

        return;
    }

    if ($phase !== 'bonus_round' || $key !== 'q') {
        return;
    }

    $tui->stop();
    $event->stopPropagation();
});

$tui->run();

printf("Task finished after %.1fs (exit code %d).\n", $elapsed, $task->getExitCode() ?? -1);
