<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Composition;
use Compose\Definition\ActionDefinition;
use Compose\Events\ActionFinished;
use Compose\Events\ActionStarting;
use Compose\Events\EventDispatcher;
use Compose\Events\StepFinished;
use Compose\Events\StepStarting;
use Compose\Exception\ComposeException;
use Throwable;

final class Runner
{
    /** @var array<string, true> */
    private array $initializedRepositories = [];

    public function __construct(
        private readonly ActionExecutor $actions = new ActionExecutor,
        private readonly GitRepository $git = new GitRepository,
        private readonly EventDispatcher $events = new EventDispatcher,
        private readonly PathResolver $paths = new PathResolver,
    ) {}

    public function run(Composition $composition, ?RunOptions $options = null): RunResult
    {
        $this->initializedRepositories = [];
        $options ??= new RunOptions(getcwd() ?: '.');
        $steps = [];

        foreach ($composition->steps() as $stepIndex => $step) {
            $this->events->dispatch(new StepStarting($step));
            $cwd = $this->paths->resolve($options->root, $step->cwd);
            if (! is_dir($cwd)) {
                $result = new StepResult($step->name, false, [
                    new ActionResult($step->actions[0] ?? throw new ComposeException("Empty step directory does not exist: {$step->cwd}"), false, errorOutput: "Step directory does not exist: {$step->cwd}", exitCode: 1),
                ]);
                $steps[] = $result;

                return new RunResult(false, $steps, $stepIndex);
            }

            $repository = $this->git->root($cwd);
            $checkpoint = null;
            if ($repository !== null) {
                try {
                    if (! isset($this->initializedRepositories[$repository])) {
                        $this->git->assertClean($repository);
                        $this->initializedRepositories[$repository] = true;
                    }
                    $checkpoint = $this->git->checkpoint($repository);
                } catch (Throwable $exception) {
                    $result = new StepResult($step->name, false, [new ActionResult(
                        $step->actions[0] ?? throw new ComposeException('A step must have at least one action.'),
                        false,
                        errorOutput: $exception->getMessage(),
                        exitCode: 1,
                    )]);
                    $steps[] = $result;
                    $this->events->dispatch(new StepFinished($result));

                    return new RunResult(false, $steps, $stepIndex);
                }
            }

            $results = [];
            $failure = false;

            foreach ($step->actions as $action) {
                $actionCheckpoint = $repository !== null ? $this->git->checkpoint($repository) : null;
                $retries = $action->policy->retries ?? 0;
                $timeout = $action->policy->timeout;
                $attempt = 0;

                do {
                    $attempt++;
                    $this->events->dispatch(new ActionStarting($action, $attempt));
                    $actionResult = $this->actions->execute($action, $cwd, $actionCheckpoint, $options, $timeout);

                    if ($actionResult->successful || $attempt > $retries) {
                        break;
                    }
                    if ($actionCheckpoint !== null) {
                        $this->git->restore($actionCheckpoint);
                    }
                    if (($action->policy->retryDelay ?? 0) > 0) {
                        usleep((int) ($action->policy->retryDelay * 1_000_000));
                    }
                } while (true);

                if ($actionResult->successful) {
                    $this->registerCreatedRepository($action, $cwd);
                }

                $actionResult = new ActionResult(
                    $actionResult->action,
                    $actionResult->successful,
                    $actionResult->output,
                    $actionResult->errorOutput,
                    $actionResult->exitCode,
                    $actionResult->duration,
                    ! $actionResult->successful && $action->policy->optional,
                    $attempt,
                    $actionResult->agent,
                );

                if (! $actionResult->successful && $action->policy->optional && $actionCheckpoint !== null) {
                    $this->git->restore($actionCheckpoint);
                }

                $results[] = $actionResult;
                $this->events->dispatch(new ActionFinished($actionResult));

                if (! $actionResult->successful && ! $action->policy->optional) {
                    $failure = true;
                    break;
                }
            }

            $rolledBack = false;
            $rollbackError = null;
            if ($failure && $checkpoint !== null) {
                try {
                    $this->git->restore($checkpoint);
                    $rolledBack = true;
                } catch (Throwable $exception) {
                    $rollbackError = $exception->getMessage();
                }
            }

            $stepResult = new StepResult($step->name, ! $failure, $results, $rolledBack, $rollbackError);
            $steps[] = $stepResult;
            $this->events->dispatch(new StepFinished($stepResult));

            if ($failure) {
                return new RunResult(false, $steps, $stepIndex);
            }
        }

        return new RunResult(true, $steps);
    }

    public function events(): EventDispatcher
    {
        return $this->events;
    }

    private function registerCreatedRepository(ActionDefinition $action, string $cwd): void
    {
        $target = match ($action->type) {
            'git:init' => $cwd,
            'git:clone' => $action->payload['here']
                ? $cwd
                : $this->paths->resolve($cwd, $action->payload['path']),
            default => null,
        };

        if ($target === null) {
            return;
        }

        $repository = $this->git->root($target);
        if ($repository !== null) {
            $this->initializedRepositories[$repository] = true;
        }
    }
}
