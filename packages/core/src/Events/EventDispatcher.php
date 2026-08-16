<?php

declare(strict_types=1);

namespace Compose\Events;

use Psr\EventDispatcher\EventDispatcherInterface;

final class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    /**
     * @template TEvent of object
     *
     * @param  class-string<TEvent>  $event
     * @param  callable(TEvent): void  $listener
     */
    public function listen(string $event, callable $listener): self
    {
        $this->listeners[$event][] = $listener;

        return $this;
    }

    public function dispatch(object $event): object
    {
        foreach ($this->listeners as $class => $listeners) {
            if (! $event instanceof $class) {
                continue;
            }
            foreach ($listeners as $listener) {
                $listener($event);
            }
        }

        return $event;
    }
}
