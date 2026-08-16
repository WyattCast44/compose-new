<?php

declare(strict_types=1);

namespace Compose\AI;

final readonly class ReviewResponse
{
    public function __construct(public ReviewDecision $decision, public ?string $message = null) {}
}
