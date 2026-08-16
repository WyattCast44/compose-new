<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Exception\ComposeException;
use PhpParser\BuilderHelpers;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Throwable;

final readonly class PhpFileEditor
{
    private Parser $parser;

    public function __construct(
        private PathResolver $paths = new PathResolver,
        private FileEditor $files = new FileEditor,
        private Standard $printer = new Standard,
    ) {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    /** @param array<string, mixed> $arguments */
    public function edit(string $cwd, string $path, string $operation, array $arguments): string
    {
        $target = $this->paths->resolve($cwd, $path);
        $source = file_get_contents($target);

        if ($source === false) {
            throw new ComposeException("Unable to read {$path}");
        }

        try {
            $statements = array_values($this->parser->parse($source) ?? []);
            $this->apply($statements, $operation, $arguments);
            $this->files->atomicWrite($target, $this->printer->prettyPrintFile($statements).PHP_EOL);
        } catch (ComposeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ComposeException("Unable to edit PHP file {$path}: {$exception->getMessage()}", previous: $exception);
        }

        return "Edited {$path}";
    }

    /**
     * @param  list<Stmt>  $statements
     * @param  array<string, mixed>  $arguments
     */
    private function apply(array &$statements, string $operation, array $arguments): void
    {
        if (str_starts_with($operation, 'config_')) {
            $this->editConfig($statements, $operation, $arguments);

            return;
        }

        $class = (new NodeFinder)->findFirstInstanceOf($statements, Stmt\Class_::class);

        if (! $class instanceof Stmt\Class_) {
            throw new ComposeException('The PHP file does not contain a class.');
        }

        match ($operation) {
            'add_import' => $this->addImport($statements, (string) $arguments['class']),
            'add_trait' => $this->addTrait($class, (string) $arguments['trait']),
            'add_interface' => $this->addInterface($class, (string) $arguments['interface']),
            'add_attribute' => $this->addAttribute($class, (string) $arguments['attribute']),
            'add_method' => $this->addMethod($class, $arguments),
            'remove_method' => $this->removeMethod($class, (string) $arguments['name']),
            default => throw new ComposeException("Unknown PHP edit: {$operation}"),
        };
    }

    /** @param list<Stmt> $statements */
    private function addImport(array &$statements, string $class): void
    {
        $uses = (new NodeFinder)->findInstanceOf($statements, Stmt\Use_::class);

        foreach ($uses as $use) {
            foreach ($use->uses as $item) {
                if ($item->name->toString() === ltrim($class, '\\')) {
                    return;
                }
            }
        }

        $use = new Stmt\Use_([new Stmt\UseUse(new Name(ltrim($class, '\\')))]);
        $namespace = (new NodeFinder)->findFirstInstanceOf($statements, Stmt\Namespace_::class);

        if ($namespace instanceof Stmt\Namespace_) {
            $index = 0;
            while (isset($namespace->stmts[$index]) && ($namespace->stmts[$index] instanceof Stmt\Declare_ || $namespace->stmts[$index] instanceof Stmt\Use_)) {
                $index++;
            }
            array_splice($namespace->stmts, $index, 0, [$use]);

            return;
        }

        $index = isset($statements[0]) && $statements[0] instanceof Stmt\Declare_ ? 1 : 0;
        array_splice($statements, $index, 0, [$use]);
    }

    private function addTrait(Stmt\Class_ $class, string $trait): void
    {
        foreach ($class->getTraitUses() as $use) {
            foreach ($use->traits as $existing) {
                if ($existing->toString() === ltrim($trait, '\\')) {
                    return;
                }
            }
        }

        array_unshift($class->stmts, new Stmt\TraitUse([new Name(ltrim($trait, '\\'))]));
    }

    private function addInterface(Stmt\Class_ $class, string $interface): void
    {
        foreach ($class->implements as $existing) {
            if ($existing->toString() === ltrim($interface, '\\')) {
                return;
            }
        }

        $class->implements[] = new Name(ltrim($interface, '\\'));
    }

    private function addAttribute(Stmt\Class_ $class, string $attribute): void
    {
        $parsed = $this->parser->parse("<?php #[{$attribute}] class __ComposeAttribute {}") ?? [];
        $temporary = (new NodeFinder)->findFirstInstanceOf($parsed, Stmt\Class_::class);

        if (! $temporary instanceof Stmt\Class_ || $temporary->attrGroups === []) {
            throw new ComposeException('Invalid PHP attribute.');
        }

        $rendered = $this->printer->prettyPrint($temporary->attrGroups);
        foreach ($class->attrGroups as $group) {
            if ($this->printer->prettyPrint([$group]) === $rendered) {
                return;
            }
        }

        $class->attrGroups[] = $temporary->attrGroups[0];
    }

    /** @param array<string, mixed> $arguments */
    private function addMethod(Stmt\Class_ $class, array $arguments): void
    {
        $name = (string) $arguments['name'];
        if ($class->getMethod($name) !== null) {
            throw new ComposeException("Method {$name} already exists.");
        }

        $visibility = (string) $arguments['visibility'];
        $flags = match ($visibility) {
            'public' => Stmt\Class_::MODIFIER_PUBLIC,
            'protected' => Stmt\Class_::MODIFIER_PROTECTED,
            'private' => Stmt\Class_::MODIFIER_PRIVATE,
            default => throw new ComposeException("Invalid method visibility: {$visibility}"),
        };
        $return = $arguments['returnType'] !== null ? ': '.$arguments['returnType'] : '';
        $snippet = "<?php class __ComposeMethod { {$visibility} function {$name}(){$return} {\n{$arguments['body']}\n} }";
        $parsed = $this->parser->parse($snippet) ?? [];
        $temporary = (new NodeFinder)->findFirstInstanceOf($parsed, Stmt\Class_::class);
        $method = $temporary instanceof Stmt\Class_ ? $temporary->getMethod($name) : null;

        if (! $method instanceof Stmt\ClassMethod) {
            throw new ComposeException('Invalid method definition.');
        }

        $method->flags = $flags;
        $class->stmts[] = $method;
    }

    private function removeMethod(Stmt\Class_ $class, string $name): void
    {
        $before = count($class->stmts);
        $class->stmts = array_values(array_filter(
            $class->stmts,
            static fn (Stmt $statement): bool => ! ($statement instanceof Stmt\ClassMethod && $statement->name->toString() === $name),
        ));

        if ($before === count($class->stmts)) {
            throw new ComposeException("Method {$name} does not exist.");
        }
    }

    /**
     * @param  list<Stmt>  $statements
     * @param  array<string, mixed>  $arguments
     */
    private function editConfig(array &$statements, string $operation, array $arguments): void
    {
        $return = (new NodeFinder)->findFirstInstanceOf($statements, Stmt\Return_::class);
        if (! $return instanceof Stmt\Return_ || ! $return->expr instanceof Array_) {
            throw new ComposeException('The PHP config file must return an array.');
        }

        $segments = explode('.', (string) $arguments['key']);
        if ($operation === 'config_set') {
            $this->setArrayValue($return->expr, $segments, $arguments['value']);
        } elseif ($operation === 'config_remove') {
            $this->removeArrayValue($return->expr, $segments);
        } else {
            throw new ComposeException("Unknown PHP config edit: {$operation}");
        }
    }

    /** @param list<string> $segments */
    private function setArrayValue(Array_ $array, array $segments, mixed $value): void
    {
        $key = array_shift($segments);
        if ($key === null) {
            throw new ComposeException('Config keys cannot be empty.');
        }
        $item = $this->arrayItem($array, $key);

        if ($segments === []) {
            if ($item instanceof ArrayItem) {
                $item->value = BuilderHelpers::normalizeValue($value);
            } else {
                $array->items[] = new ArrayItem(BuilderHelpers::normalizeValue($value), BuilderHelpers::normalizeValue($key));
            }

            return;
        }

        if (! $item instanceof ArrayItem) {
            $nested = new Array_([], ['kind' => Array_::KIND_SHORT]);
            $item = new ArrayItem($nested, BuilderHelpers::normalizeValue($key));
            $array->items[] = $item;
        }

        if (! $item->value instanceof Array_) {
            throw new ComposeException("Config key {$key} is not an array.");
        }

        $this->setArrayValue($item->value, $segments, $value);
    }

    /** @param list<string> $segments */
    private function removeArrayValue(Array_ $array, array $segments): void
    {
        $key = array_shift($segments);
        foreach ($array->items as $index => $item) {
            if ($this->keyValue($item->key) !== $key) {
                continue;
            }

            if ($segments === []) {
                unset($array->items[$index]);
                $array->items = array_values($array->items);
            } elseif ($item->value instanceof Array_) {
                $this->removeArrayValue($item->value, $segments);
            }

            return;
        }
    }

    private function arrayItem(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if ($this->keyValue($item->key) === $key) {
                return $item;
            }
        }

        return null;
    }

    private function keyValue(?Node\Expr $key): ?string
    {
        return match (true) {
            $key instanceof Node\Scalar\String_ => $key->value,
            $key instanceof Node\Scalar\Int_ => (string) $key->value,
            default => null,
        };
    }
}
