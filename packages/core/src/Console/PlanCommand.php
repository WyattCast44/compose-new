<?php

declare(strict_types=1);

namespace Compose\Console;

use Compose\Planning\Planner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('plan', 'Compile and display a composition without executing it')]
final class PlanCommand extends Command
{
    public function __construct(private readonly CompositionLoader $loader = new CompositionLoader, private readonly Planner $planner = new Planner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Composition file', 'compose.php');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Print machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plan = $this->planner->plan($this->loader->load((string) $input->getArgument('file')));
        $output->writeln($input->getOption('json')
            ? json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : (string) $plan);

        return Command::SUCCESS;
    }
}
