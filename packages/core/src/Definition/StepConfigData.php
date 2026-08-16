<?php

declare(strict_types=1);

namespace Compose\Definition;

final readonly class StepConfigData
{
    public function __construct(
        public ?float $timeout = null,
        public int $retries = 0,
        public float $retryDelay = 0,
    ) {}
}
