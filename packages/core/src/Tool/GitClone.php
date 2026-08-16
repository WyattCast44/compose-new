<?php

declare(strict_types=1);

namespace Compose\Tool;

use Compose\Definition\Finalizable;
use Compose\Definition\Risk;
use Compose\Exception\DefinitionException;
use Compose\PendingAction;
use Compose\Step;

final class GitClone implements Finalizable
{
    private bool $finalized = false;

    public function __construct(
        private readonly Step $step,
        private readonly string $repository,
        private readonly ?string $branch,
    ) {}

    public function into(string $path): PendingAction
    {
        return $this->finalize($path, false);
    }

    public function here(): PendingAction
    {
        return $this->finalize('.', true);
    }

    public function assertFinalized(): void
    {
        if (! $this->finalized) {
            throw new DefinitionException("git clone for {$this->repository} must end with into() or here().");
        }
    }

    private function finalize(string $path, bool $here): PendingAction
    {
        if ($this->finalized) {
            throw new DefinitionException('A git clone definition can only be finalized once.');
        }

        $this->finalized = true;

        return $this->step->queue(
            type: 'git:clone',
            description: "clone {$this->repository} into {$path}",
            payload: [
                'repository' => $this->repository,
                'branch' => $this->branch,
                'path' => $path,
                'here' => $here,
            ],
            risks: [Risk::Network, Risk::Destructive, Risk::NonReversible],
            reversible: false,
        );
    }
}
