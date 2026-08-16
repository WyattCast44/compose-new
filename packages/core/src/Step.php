<?php

declare(strict_types=1);

namespace Compose;

use Closure;
use Compose\AI\Instruction;
use Compose\Definition\ActionDraft;
use Compose\Definition\Risk;
use Compose\Definition\StepDraft;
use Compose\Tool\ComposerBuilder;
use Compose\Tool\FilesBuilder;
use Compose\Tool\GitBuilder;
use Compose\Tool\JsonEditor;
use Compose\Tool\NodeBuilder;
use Compose\Tool\NodeManager;
use Compose\Tool\PhpEditor;
use Compose\Tool\ProcessBuilder;
use Compose\Tool\TextEditor;
use Compose\Tool\VerificationBuilder;

class Step
{
    final public function __construct(protected readonly StepDraft $draft) {}

    final public function composer(): ComposerBuilder
    {
        return new ComposerBuilder($this);
    }

    final public function node(?NodeManager $manager = null): NodeBuilder
    {
        return new NodeBuilder($this, $manager);
    }

    final public function git(): GitBuilder
    {
        return new GitBuilder($this, $this->draft);
    }

    final public function files(): FilesBuilder
    {
        return new FilesBuilder($this);
    }

    final public function process(): ProcessBuilder
    {
        return new ProcessBuilder($this);
    }

    final public function text(string $path): TextEditor
    {
        return new TextEditor($this, $path);
    }

    final public function json(string $path): JsonEditor
    {
        return new JsonEditor($this, $path);
    }

    final public function php(string $path): PhpEditor
    {
        return new PhpEditor($this, $path);
    }

    final public function verify(): VerificationBuilder
    {
        return new VerificationBuilder($this);
    }

    /** @param (Closure(Instruction): void)|null $configure */
    final public function instruct(string $task, ?Closure $configure = null): PendingAction
    {
        $instruction = new Instruction($task);
        $configure?->__invoke($instruction);

        return $this->queue(
            type: 'ai:instruct',
            description: "instruct: {$task}",
            payload: $instruction->payload(),
            risks: [Risk::Ai, Risk::NonReversible],
            reversible: true,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<Risk>  $risks
     */
    final public function queue(
        string $type,
        string $description,
        array $payload = [],
        array $risks = [],
        bool $reversible = true,
    ): PendingAction {
        $action = new ActionDraft($type, $description, $payload, $risks, $reversible);
        $this->draft->add($action);

        return new PendingAction($action);
    }
}
