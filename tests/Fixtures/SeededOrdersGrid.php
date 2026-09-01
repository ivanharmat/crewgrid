<?php

namespace CrewGrid\Tests\Fixtures;

/**
 * A grid that opens on a filter of its own - but only for someone with no
 * view to come back to, so clearing that filter sticks.
 */
class SeededOrdersGrid extends OrdersGrid
{
    public function mount(): void
    {
        parent::mount();

        if (! $this->viewWasRestored() && empty($this->filters)) {
            $this->filters['customer'] = ['Acme' => true];
        }
    }
}
