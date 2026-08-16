<?php

declare(strict_types=1);

namespace Compose\Laravel;

use Compose\Definition\Risk;
use Compose\PendingAction;

final readonly class ArtisanBuilder
{
    public function __construct(private LaravelStep $step) {}

    public function run(string $command, string ...$arguments): PendingAction
    {
        return $this->step->queue(
            'process:run',
            "artisan {$command}",
            ['command' => ['php', 'artisan', $command, ...$arguments]],
            [Risk::NonReversible],
            false,
        );
    }

    public function keyGenerate(bool $force = false): PendingAction
    {
        return $this->run('key:generate', ...($force ? ['--force'] : []));
    }

    public function migrate(bool $force = false, bool $seed = false): PendingAction
    {
        return $this->run('migrate', ...($force ? ['--force'] : []), ...($seed ? ['--seed'] : []));
    }

    public function seed(array $classes = []): PendingAction
    {
        return $this->run('db:seed', ...array_map(fn (string $class) => ['--class', $class], $classes));
    }
}
