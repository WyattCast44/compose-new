<?php

declare(strict_types=1);

namespace Compose\AI;

final class Instruction
{
    /** @var list<string> */
    private array $using = [];

    /** @var list<string> */
    private array $allowedChanges = [];

    /** @var list<string> */
    private array $rules = [];

    private bool $review = false;

    private ?Agent $agent = null;

    private ?string $model = null;

    public function __construct(private readonly string $task) {}

    public function using(string ...$paths): self
    {
        array_push($this->using, ...$paths);

        return $this;
    }

    public function allowChanges(string ...$globs): self
    {
        array_push($this->allowedChanges, ...$globs);

        return $this;
    }

    public function rules(string ...$rules): self
    {
        array_push($this->rules, ...$rules);

        return $this;
    }

    public function review(bool $review = true): self
    {
        $this->review = $review;

        return $this;
    }

    public function agent(Agent $agent, ?string $model = null): self
    {
        $this->agent = $agent;
        $this->model = $model;

        return $this;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'task' => $this->task,
            'using' => array_values(array_unique($this->using)),
            'allowed_changes' => array_values(array_unique($this->allowedChanges)),
            'rules' => $this->rules,
            'review' => $this->review,
            'agent' => $this->agent?->value,
            'model' => $this->model,
        ];
    }
}
