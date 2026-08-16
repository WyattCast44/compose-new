<?php

declare(strict_types=1);

namespace Compose\Definition;

use Closure;
use JsonSerializable;

final readonly class ActionDefinition implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<Risk>  $risks
     */
    public function __construct(
        public string $type,
        public string $description,
        public array $payload = [],
        public array $risks = [],
        public bool $reversible = true,
        public ActionPolicy $policy = new ActionPolicy,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->description,
            'payload' => $this->serializablePayload($this->payload),
            'risks' => array_map(static fn (Risk $risk): string => $risk->value, $this->risks),
            'reversible' => $this->reversible,
            'policy' => [
                'optional' => $this->policy->optional,
                'timeout' => $this->policy->timeout,
                'retries' => $this->policy->retries,
                'retry_delay' => $this->policy->retryDelay,
            ],
        ];
    }

    private function serializablePayload(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            return '[callback]';
        }

        if (is_object($value) && ! $value instanceof JsonSerializable) {
            return '['.$value::class.']';
        }

        if (! is_array($value)) {
            return $value;
        }

        return array_map($this->serializablePayload(...), $value);
    }
}
