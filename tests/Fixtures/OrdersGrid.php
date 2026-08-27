<?php

namespace CrewGrid\Tests\Fixtures;

use CrewGrid\Columns\Column;
use CrewGrid\Grid;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;

/**
 * Exercises every Column feature the suite asserts on: sorting, quick search,
 * all three filter types, format/html cells and the export contract.
 */
class OrdersGrid extends Grid
{
    protected function query(): BuilderContract
    {
        return Order::query();
    }

    protected function columns(): array
    {
        return [
            Column::make('Reference', 'reference')->sortable()->searchable()->filterText(),

            Column::make('Customer', 'customer')
                ->searchable()
                ->filterMultiSelect(fn () => Order::query()->distinct()->orderBy('customer')->pluck('customer', 'customer')->all()),

            Column::make('Total', 'total')
                ->sortable()
                ->filterNumber()
                ->format(fn ($value) => '$'.number_format($value, 2))
                ->exportAs(fn ($value) => $value),

            Column::make('Placed', 'placed_at')->sortable()->filterDateRange(),

            Column::make('Status', 'status')
                ->html()
                ->format(fn ($value) => '<span class="badge">'.e($value).'</span>'),

            Column::make('', 'id')
                ->html()
                ->notExportable()
                ->format(fn ($value, $row) => $this->actionLink('View', 'https://example.test/orders/'.$row->id, 'info')),
        ];
    }
}
