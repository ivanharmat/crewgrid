<?php

namespace CrewGrid\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Livewire\Attributes\Url;

/**
 * A grid whose toolbar carries a control of its own - a scope the whole
 * query answers to, which no single column's filter could express.
 */
class ToolbarOrdersGrid extends OrdersGrid
{
    #[Url(except: 'open')]
    public string $scope = 'open';

    protected function toolbarView(): ?string
    {
        return 'toolbar';
    }

    protected function rememberedProperties(): array
    {
        return parent::rememberedProperties() + ['scope' => 'scope'];
    }

    protected function query(): BuilderContract
    {
        $orders = parent::query();

        if ($this->scope !== 'all') {
            $orders->where('orders.status', $this->scope);
        }

        return $orders;
    }
}
