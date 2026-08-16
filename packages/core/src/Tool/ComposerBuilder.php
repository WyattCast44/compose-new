<?php

declare(strict_types=1);

namespace Compose\Tool;

use Compose\Definition\Risk;
use Compose\PendingAction;
use Compose\Step;

final readonly class ComposerBuilder
{
    public function __construct(private Step $step) {}

    public function install(string ...$arguments): PendingAction
    {
        return $this->command('install', array_values($arguments), 'install Composer dependencies');
    }

    public function update(string ...$packages): PendingAction
    {
        return $this->command('update', array_values($packages), 'update Composer dependencies');
    }

    public function require(string ...$packages): PendingAction
    {
        return $this->command('require', array_values($packages), 'require '.implode(', ', $packages));
    }

    public function requireDev(string ...$packages): PendingAction
    {
        return $this->command('require', array_values(['--dev', ...$packages]), 'require dev '.implode(', ', $packages));
    }

    public function remove(string ...$packages): PendingAction
    {
        return $this->command('remove', array_values($packages), 'remove '.implode(', ', $packages));
    }

    public function run(string $script, string ...$arguments): PendingAction
    {
        return $this->command('run-script', array_values([$script, ...$arguments]), "run Composer script {$script}");
    }

    /** @param list<string> $arguments */
    private function command(string $command, array $arguments, string $description): PendingAction
    {
        return $this->step->queue(
            type: 'process:run',
            description: $description,
            payload: ['command' => ['composer', $command, ...$arguments]],
            risks: [Risk::Network, Risk::NonReversible],
            reversible: false,
        );
    }
}
