<?php

declare(strict_types=1);

namespace Compose;

use Compose\Definition\ActionDraft;

final class PendingAction
{
    public function __construct(private readonly ActionDraft $draft) {}

    public function optional(bool $optional = true): self
    {
        $this->draft->optional = $optional;

        return $this;
    }

    public function timeout(float $seconds): self
    {
        $this->draft->timeout = $seconds;

        return $this;
    }

    public function retry(int $times, float $delay = 0): self
    {
        $this->draft->retries = max(0, $times);
        $this->draft->retryDelay = max(0, $delay);

        return $this;
    }
}
