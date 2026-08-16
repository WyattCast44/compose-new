<?php

declare(strict_types=1);

namespace Compose\AI;

use Compose\Definition\ActionDefinition;
use Compose\Exception\ComposeException;
use Compose\Execution\ActionResult;
use Compose\Execution\GitCheckpoint;
use Compose\Execution\GitRepository;
use Compose\Execution\RunOptions;
use Throwable;

final readonly class AiExecutor
{
    public function __construct(
        private AgentRegistry $agents = new AgentRegistry,
        private GitRepository $git = new GitRepository,
        private BakeStore $bakes = new BakeStore,
    ) {}

    public function execute(ActionDefinition $action, string $cwd, GitCheckpoint $checkpoint, RunOptions $options): ActionResult
    {
        $started = microtime(true);
        $payload = $action->payload;

        try {
            $key = $this->bakes->key($cwd, $payload);
            if (($payload['bake'] ?? false) && ! $options->rebake && ($patch = $this->bakes->get($checkpoint->root, $key)) !== null) {
                $this->git->applyPatch($checkpoint->root, $patch);

                return new ActionResult($action, true, 'Applied baked AI patch.', duration: microtime(true) - $started, agent: new AgentResult('bake', baked: true));
            }

            $driverId = (string) ($payload['agent'] ?? $options->agent);
            $model = $payload['model'] ?? $options->model;
            $driver = $this->agents->get($driverId);
            if (! $driver->available()) {
                throw new ComposeException("AI agent is not available: {$driverId}");
            }

            $request = new AgentRequest((string) $payload['task'], $this->prompt($payload), $cwd, $model);
            $run = $driver->start($request);
            $steers = 0;

            while (true) {
                if (! $run->successful) {
                    throw new ComposeException($run->error ?: 'AI agent failed.');
                }
                if ($this->git->currentHead($checkpoint) !== $checkpoint->head) {
                    throw new ComposeException('AI agent changed Git HEAD, which is not allowed.');
                }

                $changed = $this->git->changedPaths($checkpoint);
                $this->assertAllowed($changed, $payload['allowed_changes'] ?? []);
                $patch = $this->git->patch($checkpoint);

                if (! ($payload['review'] ?? false) || $options->acceptAi) {
                    break;
                }
                if ($options->reviewer === null) {
                    throw new ComposeException('AI review is required; use an interactive reviewer or --accept-ai.');
                }

                $review = $options->reviewer->review(new AgentReview((string) $payload['task'], $run->output, $patch, $changed));
                if ($review->decision === ReviewDecision::Continue) {
                    break;
                }
                if ($review->decision === ReviewDecision::Rollback) {
                    throw new ComposeException('AI changes were rejected.');
                }
                if (! $driver->supportsResume() || $run->sessionId === null || ++$steers > 5) {
                    throw new ComposeException('The AI agent cannot be steered further.');
                }
                $run = $driver->resume($run->sessionId, new AgentRequest(
                    (string) $payload['task'],
                    $review->message ?: 'Revise your changes based on the review.',
                    $cwd,
                    $model,
                ));
            }

            if ($payload['bake'] ?? false) {
                $this->bakes->put($checkpoint->root, $key, $patch, [
                    'task' => $payload['task'],
                    'driver' => $driverId,
                    'model' => $model,
                    'created_at' => date(DATE_ATOM),
                ]);
            }

            return new ActionResult(
                $action,
                true,
                $run->output,
                duration: microtime(true) - $started,
                agent: new AgentResult($driverId, $run->model, $run->sessionId, $run->inputTokens, $run->outputTokens, $run->cost),
            );
        } catch (Throwable $exception) {
            try {
                $this->git->restore($checkpoint);
            } catch (Throwable $rollback) {
                return new ActionResult($action, false, errorOutput: $exception->getMessage()."\nRollback failed: ".$rollback->getMessage(), exitCode: 1, duration: microtime(true) - $started);
            }

            return new ActionResult($action, false, errorOutput: $exception->getMessage(), exitCode: 1, duration: microtime(true) - $started);
        }
    }

    /** @param array<string, mixed> $payload */
    private function prompt(array $payload): string
    {
        $parts = [(string) $payload['task']];
        if (($payload['using'] ?? []) !== []) {
            $parts[] = 'Read these files first: '.implode(', ', $payload['using']);
        }
        if (($payload['allowed_changes'] ?? []) !== []) {
            $parts[] = 'Only change files matching: '.implode(', ', $payload['allowed_changes']);
        }
        foreach ($payload['rules'] ?? [] as $rule) {
            $parts[] = 'Rule: '.$rule;
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $patterns
     */
    private function assertAllowed(array $paths, array $patterns): void
    {
        if ($patterns === []) {
            return;
        }

        foreach ($paths as $path) {
            foreach ($patterns as $pattern) {
                $regex = '#^'.str_replace(['\\*\\*', '\\*', '\\?'], ['.*', '[^/]*', '[^/]'], preg_quote($pattern, '#')).'$#';
                if (preg_match($regex, $path) === 1) {
                    continue 2;
                }
            }
            throw new ComposeException("AI agent changed a disallowed path: {$path}");
        }
    }
}
