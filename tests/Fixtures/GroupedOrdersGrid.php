<?php

namespace CrewGrid\Tests\Fixtures;

class GroupedOrdersGrid extends OrdersGrid
{
    protected function groupBy(): ?string
    {
        return 'customer';
    }
}
