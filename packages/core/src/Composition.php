<?php

declare(strict_types=1);

namespace Compose;

use Closure;
use Compose\Definition\StepDefinition;
use Compose\Definition\StepDraft;
use Compose\Exception\DefinitionException;
use ReflectionFunction;
use ReflectionNamedType;

final class Composition
{
    /** @var list<StepDefinition> */
    private array $steps = [];

    private string $cwd = '.';

    /** @var list<class-string<Recipe>> */
    private array $activeRecipes = [];

    public function __construct(public readonly string $name) {}

    public function cwd(string $path): self
    {
        $this->cwd = $this->normalizeRelativePath($path);

        return $this;
    }

    /**
     * @param  (Closure(StepConfig): void)|null  $configure
     */
    public function step(
        string $name,
        Closure $operations,
        ?Closure $configure = null,
    ): self {
        $configuration = new StepConfig;
        
        $configure?->__invoke($configuration) ?? $configuration;

        $draft = new StepDraft(
            name: $name,
            cwd: $this->cwd,
            config: $configuration->freeze(),
        );

        $reflection = new ReflectionFunction($operations);
        $arguments = [];
        $seen = [];

        if ($reflection->getNumberOfParameters() === 0) {
            throw new DefinitionException("Step '{$name}' must request at least one Step parameter.");
        }

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                throw new DefinitionException("Step '{$name}' parameter \${$parameter->getName()} must use one concrete Step class.");
            }

            /** @var class-string $class */
            $class = $type->getName();

            if ($class !== Step::class && ! is_subclass_of($class, Step::class)) {
                throw new DefinitionException("{$class} must extend ".Step::class.'.');
            }

            if (isset($seen[$class])) {
                throw new DefinitionException("Step '{$name}' requests {$class} more than once.");
            }

            $seen[$class] = true;
            $arguments[] = new $class($draft);
        }

        $operations(...$arguments);
        
        $this->steps[] = $draft->freeze();

        return $this;
    }

    /**
     * @template TRecipe of Recipe
     *
     * @param  class-string<TRecipe>|TRecipe  $recipe
     * @param  (Closure(TRecipe): void)|null  $configure
     */
    public function extend(string|Recipe $recipe, ?Closure $configure = null): self
    {
        $instance = is_string($recipe) ? new $recipe : $recipe;
        $class = $instance::class;

        if (in_array($class, $this->activeRecipes, true)) {
            throw new DefinitionException('Circular recipe extension: '.implode(' -> ', [...$this->activeRecipes, $class]));
        }

        $configure?->__invoke($instance);
        $savedCwd = $this->cwd;
        $this->activeRecipes[] = $class;

        try {
            $instance->compose($this);
        } finally {
            array_pop($this->activeRecipes);
            $this->cwd = $savedCwd;
        }

        return $this;
    }

    /** @return list<StepDefinition> */
    public function steps(): array
    {
        return $this->steps;
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || $path === '.') {
            return '.';
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new DefinitionException('Composition cwd paths must be relative to the invocation directory.');
        }

        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                throw new DefinitionException('Composition cwd paths cannot escape with .. segments.');
            }

            $parts[] = $part;
        }

        return $parts === [] ? '.' : implode('/', $parts);
    }
}
