<?php

declare(strict_types=1);

namespace Compose\AI;

interface AgentReviewer
{
    public function review(AgentReview $review): ReviewResponse;
}
