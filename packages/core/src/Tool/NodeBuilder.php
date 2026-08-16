<?php

declare(strict_types=1);

namespace Compose\Tool;

use Compose\Definition\Risk;
use Compose\PendingAction;
use Compose\Step;

final readonly class NodeBuilder
{
    public function __construct(private Step $step, private ?NodeManager $manager = null) {}

    public function install(): PendingAction
    {
        return $this->command('install', [], 'install Node dependencies');
    }

    public function add(string ...$packages): PendingAction
    {
        return $this->command('add', array_values($packages), 'add Node packages '.implode(', ', $packages));
    }

    public function addDev(string ...$packages): PendingAction
    {
        return $this->command('add-dev', array_values($packages), 'add dev Node packages '.implode(', ', $packages));
    }

    public function remove(string ...$packages): PendingAction
    {
        return $this->command('remove', array_values($packages), 'remove Node packages '.implode(', ', $packages));
    }

    public function run(string $script, string ...$arguments): PendingAction
    {
        return $this->command('run', array_values([$script, ...$arguments]), "run Node script {$script}");
    }

    public function exec(string $binary, string ...$arguments): PendingAction
    {
        return $this->command('exec', array_values([$binary, ...$arguments]), "execute Node binary {$binary}");
    }

    /** @param list<string> $arguments */
    private function command(string $operation, array $arguments, string $description): PendingAction
    {
        return $this->step->queue(
            type: 'node:'.$operation,
            description: $description,
            payload: ['manager' => $this->manager?->value, 'arguments' => $arguments],
            risks: [Risk::Network, Risk::NonReversible],
            reversible: false,
        );
    }
}
