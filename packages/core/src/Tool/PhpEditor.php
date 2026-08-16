<?php

declare(strict_types=1);

namespace Compose\Tool;

use Compose\PendingAction;
use Compose\Step;

final readonly class PhpEditor
{
    public function __construct(private Step $step, private string $path) {}

    public function addImport(string $class): PendingAction
    {
        return $this->edit('add_import', compact('class'));
    }

    public function addTrait(string $trait): PendingAction
    {
        return $this->edit('add_trait', compact('trait'));
    }

    public function addInterface(string $interface): PendingAction
    {
        return $this->edit('add_interface', compact('interface'));
    }

    public function addAttribute(string $attribute): PendingAction
    {
        return $this->edit('add_attribute', compact('attribute'));
    }

    public function addMethod(string $name, string $body, string $visibility = 'public', ?string $returnType = null): PendingAction
    {
        return $this->edit('add_method', compact('name', 'body', 'visibility', 'returnType'));
    }

    public function removeMethod(string $name): PendingAction
    {
        return $this->edit('remove_method', compact('name'));
    }

    public function configSet(string $key, mixed $value): PendingAction
    {
        return $this->edit('config_set', compact('key', 'value'));
    }

    public function configRemove(string $key): PendingAction
    {
        return $this->edit('config_remove', compact('key'));
    }

    /** @param array<string, mixed> $arguments */
    private function edit(string $operation, array $arguments): PendingAction
    {
        return $this->step->queue(
            'edit:php',
            "{$operation} in {$this->path}",
            ['path' => $this->path, 'operation' => $operation, 'arguments' => $arguments],
        );
    }
}
