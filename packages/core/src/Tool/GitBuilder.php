<?php

declare(strict_types=1);

namespace Compose\Tool;

use Compose\Definition\Risk;
use Compose\Definition\StepDraft;
use Compose\PendingAction;
use Compose\Step;

final readonly class GitBuilder
{
    public function __construct(private Step $step, private StepDraft $draft) {}

    public function clone(string $repository, ?string $branch = null): GitClone
    {
        $clone = new GitClone($this->step, $repository, $branch);
        $this->draft->track($clone);

        return $clone;
    }

    public function init(): PendingAction
    {
        return $this->step->queue(
            type: 'git:init',
            description: 'initialize Git repository',
            payload: ['command' => ['git', 'init']],
            risks: [Risk::NonReversible],
            reversible: false,
        );
    }

    public function status(): PendingAction
    {
        return $this->run(['git', 'status', '--short'], 'inspect Git status', reversible: true);
    }

    public function checkout(string $branch): PendingAction
    {
        return $this->run(['git', 'checkout', $branch], "checkout branch {$branch}", refMutation: true);
    }

    public function branch(string $branch, bool $checkout = true): PendingAction
    {
        $command = $checkout ? ['git', 'checkout', '-b', $branch] : ['git', 'branch', $branch];

        return $this->run($command, "create branch {$branch}", refMutation: true);
    }

    public function add(string ...$paths): PendingAction
    {
        return $this->run(array_values(['git', 'add', '--', ...($paths ?: ['.'])]), 'stage Git changes', refMutation: true);
    }

    public function commit(string $message): PendingAction
    {
        return $this->step->queue(
            type: 'git:commit',
            description: "commit Git changes: {$message}",
            payload: ['message' => $message],
            risks: [Risk::NonReversible],
            reversible: false,
        );
    }

    /** @param list<string> $command */
    private function run(array $command, string $description, bool $refMutation = false, bool $reversible = false): PendingAction
    {
        return $this->step->queue(
            type: 'process:run',
            description: $description,
            payload: ['command' => $command],
            risks: $refMutation ? [Risk::NonReversible] : [],
            reversible: $reversible,
        );
    }
}
