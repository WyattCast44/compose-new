<?php

declare(strict_types=1);

namespace Compose\Console;

use Compose\Events\ActionFinished;
use Compose\Events\ActionStarting;
use Compose\Events\EventDispatcher;
use Compose\Events\StepFinished;
use Compose\Events\StepStarting;
use Compose\Execution\ActionResult;
use Compose\Planning\Plan;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleRunReporter
{
    private int $stepIndex = 0;

    private int $actionIndex = 0;

    private int $actionsInStep = 0;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Plan $plan,
    ) {}

    public function attach(EventDispatcher $events): void
    {
        $events
            ->listen(StepStarting::class, $this->stepStarting(...))
            ->listen(ActionStarting::class, $this->actionStarting(...))
            ->listen(ActionFinished::class, $this->actionFinished(...))
            ->listen(StepFinished::class, $this->stepFinished(...));
    }

    private function stepStarting(StepStarting $event): void
    {
        $this->stepIndex++;
        $this->actionIndex = 0;
        $this->actionsInStep = count($event->step->actions);

        if ($this->stepIndex === 1) {
            $steps = count($this->plan->steps);
            $this->output->writeln(sprintf(
                '<info>Running %d %s…</info>',
                $steps,
                $steps === 1 ? 'step' : 'steps',
            ));
            $this->output->writeln('');
        }

        $this->output->writeln(sprintf(
            '<fg=cyan;options=bold>Step %d/%d: %s</> <comment>[%s]</comment>',
            $this->stepIndex,
            count($this->plan->steps),
            $event->step->name,
            $event->step->cwd,
        ));
    }

    private function actionStarting(ActionStarting $event): void
    {
        if ($event->attempt === 1) {
            $this->actionIndex++;
            $this->output->writeln(sprintf(
                '  <fg=cyan>→</> Action %d/%d: %s',
                $this->actionIndex,
                $this->actionsInStep,
                $event->action->description,
            ));

            return;
        }

        $this->output->writeln(sprintf(
            '    <comment>↻ Retry %d:</comment> %s',
            $event->attempt,
            $event->action->description,
        ));
    }

    private function actionFinished(ActionFinished $event): void
    {
        $result = $event->result;
        $duration = $this->duration($result->duration);

        if ($result->successful) {
            $attempts = $result->attempts > 1 ? sprintf(' after %d attempts', $result->attempts) : '';
            $this->output->writeln("    <info>✓ Completed</info>{$attempts} <comment>({$duration})</comment>");
        } elseif ($result->warning) {
            $this->output->writeln("    <comment>! Optional action failed; continuing ({$duration})</comment>");
        } else {
            $this->output->writeln("    <error>✗ Failed</error> <comment>({$duration})</comment>");
        }

        if ($this->output->isVerbose()) {
            $this->renderCapturedOutput($result);
        }
    }

    private function stepFinished(StepFinished $event): void
    {
        if ($event->result->successful) {
            $this->output->writeln('  <info>✓ Step completed</info>');
        } elseif ($event->result->rolledBack) {
            $this->output->writeln('  <comment>↩ Step failed and was rolled back</comment>');
        } else {
            $this->output->writeln('  <error>✗ Step failed</error>');
        }

        $this->output->writeln('');
    }

    private function renderCapturedOutput(ActionResult $result): void
    {
        foreach ([$result->output, $result->errorOutput] as $captured) {
            foreach (preg_split('/\R/', trim($captured)) ?: [] as $line) {
                if ($line !== '') {
                    $this->output->writeln('      '.$line, OutputInterface::OUTPUT_RAW);
                }
            }
        }
    }

    private function duration(float $seconds): string
    {
        if ($seconds < 1) {
            return (string) max(1, (int) round($seconds * 1000)).'ms';
        }

        return number_format($seconds, 2).'s';
    }
}
