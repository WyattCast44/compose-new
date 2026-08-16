<?php

declare(strict_types=1);

namespace Compose\Console;

use Compose\Config\Configuration;
use Compose\Execution\Runner;
use Compose\Execution\RunOptions;
use Compose\Execution\RunResult;
use Compose\Planning\Planner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('run', 'Plan and execute a composition')]
final class RunCommand extends Command
{
    public function __construct(
        private readonly CompositionLoader $loader = new CompositionLoader,
        private readonly Planner $planner = new Planner,
        private readonly Runner $runner = new Runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Composition file', 'compose.php');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Approve the compiled plan');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Print machine-readable results');
        $this->addOption('agent', null, InputOption::VALUE_REQUIRED, 'Default AI agent (codex or claude)');
        $this->addOption('model', null, InputOption::VALUE_REQUIRED, 'Default AI model');
        $this->addOption('rebake', null, InputOption::VALUE_NONE, 'Ignore cached AI patches');
        $this->addOption('accept-ai', null, InputOption::VALUE_NONE, 'Accept review-gated AI changes non-interactively');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');
        $root = getcwd() ?: '.';
        $composition = $this->loader->load($file);
        $plan = $this->planner->plan($composition);

        if (! $input->getOption('json')) {
            $output->writeln((string) $plan);
        }
        if (! $input->getOption('yes') && ! $io->confirm('Execute this plan?', false)) {
            $io->warning('Composition cancelled.');

            return Command::SUCCESS;
        }

        $configuration = Configuration::load(
            $root,
            is_string($input->getOption('agent')) ? $input->getOption('agent') : null,
            is_string($input->getOption('model')) ? $input->getOption('model') : null,
        );
        $reviewer = $input->isInteractive() ? new ConsoleAgentReviewer($io) : null;
        if (! $input->getOption('json')) {
            (new ConsoleRunReporter($output, $plan))->attach($this->runner->events());
        }
        $onAgentOutput = $input->getOption('json') ? null : static function (string $message) use ($output): void {
            foreach (preg_split('/\R/', trim($message)) ?: [] as $line) {
                if ($line !== '') {
                    $output->writeln('      '.$line, OutputInterface::OUTPUT_RAW);
                }
            }
        };
        $result = $this->runner->run($composition, new RunOptions(
            $root,
            $configuration->agent,
            $configuration->model,
            (bool) $input->getOption('rebake'),
            (bool) $input->getOption('accept-ai'),
            $reviewer,
            $onAgentOutput,
        ));

        if ($input->getOption('json')) {
            $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } elseif ($result->successful) {
            $io->success('Composition completed.');
        } else {
            $this->renderFailure($io, $result);
        }

        return $result->successful ? Command::SUCCESS : Command::FAILURE;
    }

    private function renderFailure(SymfonyStyle $io, RunResult $result): void
    {
        $failedStep = $result->failedAt !== null ? ($result->steps[$result->failedAt] ?? null) : null;
        $failedAction = null;

        if ($failedStep !== null) {
            foreach ($failedStep->actions as $action) {
                if (! $action->successful && ! $action->warning) {
                    $failedAction = $action;
                    break;
                }
            }
        }

        $message = ['Composition failed.'];
        if ($failedStep !== null) {
            $message[] = 'Step: '.$failedStep->name;
        }
        if ($failedAction !== null) {
            $message[] = 'Action: '.$failedAction->action->description;
            $error = trim($failedAction->errorOutput);
            if ($error !== '') {
                $message[] = 'Reason: '.$error;
            }
        }
        if ($failedStep?->rolledBack) {
            $message[] = 'The step was rolled back.';
        } elseif ($failedStep?->rollbackError !== null) {
            $message[] = 'Rollback failed: '.$failedStep->rollbackError;
        }

        $io->error($message);
    }
}
