<?php

namespace CrewGrid\Tests\Fixtures;

use CrewGrid\Columns\Column;

/**
 * A grid whose searchable column narrows the search itself - what a grid
 * joining another table has to do, since a bare column name would be
 * ambiguous. Here it matches the start of the reference rather than any
 * part of it, so the callback's effect is visible.
 */
class SearchCallbackOrdersGrid extends OrdersGrid
{
    protected function columns(): array
    {
        return [
            Column::make('Reference', 'reference')
                ->searchable(fn ($query, $search) => $query->orWhere('orders.reference', 'like', $search.'%')),

            Column::make('Customer', 'customer'),
        ];
    }
}
