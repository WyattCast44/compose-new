<?php

declare(strict_types=1);

namespace Compose\Console;

use Compose\AI\AgentReview;
use Compose\AI\AgentReviewer;
use Compose\AI\ReviewDecision;
use Compose\AI\ReviewResponse;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ConsoleAgentReviewer implements AgentReviewer
{
    public function __construct(private SymfonyStyle $io) {}

    public function review(AgentReview $review): ReviewResponse
    {
        $this->io->section('AI review');
        $this->io->writeln($review->output);
        $this->io->writeln($review->patch);
        $decision = $this->io->choice('Accept these changes?', ['continue', 'rollback', 'steer'], 'continue');
        $message = $decision === 'steer' ? $this->io->ask('Steering instructions') : null;

        return new ReviewResponse(ReviewDecision::from($decision), $message);
    }
}
