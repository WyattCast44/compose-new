<?php

declare(strict_types=1);

namespace Compose\Definition;

final class ActionDraft
{
    public bool $optional = false;

    public ?float $timeout = null;

    public ?int $retries = null;

    public ?float $retryDelay = null;

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<Risk>  $risks
     */
    public function __construct(
        public readonly string $type,
        public readonly string $description,
        public readonly array $payload = [],
        public readonly array $risks = [],
        public readonly bool $reversible = true,
    ) {}

    public function freeze(StepConfigData $defaults): ActionDefinition
    {
        return new ActionDefinition(
            type: $this->type,
            description: $this->description,
            payload: $this->payload,
            risks: $this->risks,
            reversible: $this->reversible,
            policy: new ActionPolicy(
                optional: $this->optional,
                timeout: $this->timeout ?? $defaults->timeout,
                retries: $this->retries ?? $defaults->retries,
                retryDelay: $this->retryDelay ?? $defaults->retryDelay,
            ),
        );
    }
}
