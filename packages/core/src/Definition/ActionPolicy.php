<?php

declare(strict_types=1);

namespace Compose\Definition;

final readonly class ActionPolicy
{
    public function __construct(
        public bool $optional = false,
        public ?float $timeout = null,
        public ?int $retries = null,
        public ?float $retryDelay = null,
    ) {}
}
