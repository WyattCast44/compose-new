<?php

declare(strict_types=1);

namespace Compose;

use Compose\Definition\StepConfigData;
use InvalidArgumentException;

final class StepConfig
{
    private ?float $timeout = null;

    private int $retries = 0;

    private float $retryDelay = 0;

    public function timeout(?float $seconds = null, ?float $minutes = null): self
    {
        if ($seconds === null && $minutes === null) {
            throw new InvalidArgumentException('timeout() requires seconds or minutes.');
        }

        $value = ($seconds ?? 0) + (($minutes ?? 0) * 60);

        if ($value <= 0) {
            throw new InvalidArgumentException('A timeout must be greater than zero.');
        }

        $this->timeout = $value;

        return $this;
    }

    /**
     * Set the number of times to retry and the delay between retries.
     *
     * @param  int  $times  number of times to retry
     * @param  float  $delay  delay in seconds between retries
     */
    public function retry(int $times, float $delay = 0): self
    {
        if ($times < 0 || $delay < 0) {
            throw new InvalidArgumentException('Retry values cannot be negative.');
        }

        $this->retries = $times;
        $this->retryDelay = $delay;

        return $this;
    }

    public function freeze(): StepConfigData
    {
        return new StepConfigData($this->timeout, $this->retries, $this->retryDelay);
    }
}
