<?php

declare(strict_types=1);

namespace Compose\AI;

use JsonSerializable;

final readonly class AgentResult implements JsonSerializable
{
    public function __construct(
        public string $driver,
        public ?string $model = null,
        public ?string $sessionId = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?float $cost = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
