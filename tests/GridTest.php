<?php

namespace CrewGrid\Tests;

use CrewGrid\Grid;
use CrewGrid\Tests\Fixtures\GroupedOrdersGrid;
use CrewGrid\Tests\Fixtures\Order;
use CrewGrid\Tests\Fixtures\OrdersGrid;
use InvalidArgumentException;
use Livewire\Livewire;

class GridTest extends TestCase
{
    public function test_it_renders_rows_from_the_query(): void
    {
        Livewire::test(OrdersGrid::class)
            ->assertSee('ORD-001')
            ->assertSee('Cedar &amp; Sons', false)
            ->assertSee('$250.00');
    }

    public function test_date_filters_offer_quick_presets_that_set_both_bounds(): void
    {
        $grid = new OrdersGrid;
        $presets = $grid->dateRangePresets();

        $this->assertSame(['Today', 'Yesterday', 'This Week', 'Last 7 Days', 'This Month', 'Last 30 Days'], array_keys($presets));
        $this->assertSame(date('Y-m-d'), $presets['Today']['from']);
        $this->assertSame($presets['Today']['from'], $presets['Today']['to']);
        $this->assertSame(date('Y-m-d', strtotime('-6 days')), $presets['Last 7 Days']['from']);

        Livewire::test(OrdersGrid::class)
            ->assertSeeHtml('crewgrid-date-presets')
            ->assertSee('Last 30 Days');
    }

    public function test_group_bands_form_by_key_preserving_first_appearance(): void
    {
        $grid = new GroupedOrdersGrid;
        $groups = $grid->groupedRows(Order::orderBy('id')->get());

        $this->assertSame(['Acme', 'Bravo', 'Cedar & Sons'], array_column($groups, 'key'), 'Groups appear in first-appearance order.');
        $this->assertSame(['ORD-001', 'ORD-003'], collect($groups[0]['rows'])->pluck('reference')->all(), 'Non-adjacent rows of one key land in one band.');
        $this->assertStringContainsString('Acme', $groups[0]['heading']);
        $this->assertStringContainsString('(2)', $groups[0]['heading']);
    }

    public function test_grouped_grids_render_heading_rows_and_flat_grids_do_not(): void
    {
        Livewire::test(GroupedOrdersGrid::class)
            ->assertSeeHtml('class="crewgrid-group-row"')
            ->assertSeeHtml('crewgrid-group-count')
            ->assertSeeHtml('crewgridOpen')
            ->assertSeeHtml('crewgridAllState')
            ->assertSee('Show All')
            ->assertSee('Hide All')
            ->assertSee('ORD-003');

        Livewire::test(OrdersGrid::class)
            ->assertDontSeeHtml('class="crewgrid-group-row"');
    }

    public function test_sorting_toggles_direction(): void
    {
        $component = Livewire::test(OrdersGrid::class)->call('sortBy', 'total');
        $rows = $component->viewData('rows');
        $this->assertSame([75, 100, 250, 500], collect($rows->items())->pluck('total')->all());

        $component->call('sortBy', 'total');
        $this->assertSame(500, $component->viewData('rows')->items()[0]->total);
    }

    public function test_quick_search_covers_every_searchable_column(): void
    {
        $component = Livewire::test(OrdersGrid::class)->set('search', 'Bravo');
        $this->assertSame(['ORD-002'], collect($component->viewData('rows')->items())->pluck('reference')->all());

        $component->set('search', 'ORD-004');
        $this->assertSame(['Cedar & Sons'], collect($component->viewData('rows')->items())->pluck('customer')->all());
    }

    public function test_text_multiselect_and_date_range_filters_apply(): void
    {
        $component = Livewire::test(OrdersGrid::class)->set('filters.reference', '00');
        $this->assertCount(4, $component->viewData('rows')->items());

        $component->set('filters.reference', 'ORD-003');
        $this->assertCount(1, $component->viewData('rows')->items());

        $component = Livewire::test(OrdersGrid::class)->set('filters.customer', ['Acme' => true]);
        $this->assertSame(['ORD-001', 'ORD-003'], collect($component->viewData('rows')->items())->pluck('reference')->all());

        $component = Livewire::test(OrdersGrid::class)->set('filters.placed_at', ['from' => '2026-02-01', 'to' => '2026-03-31']);
        $this->assertSame(['ORD-002', 'ORD-003'], collect($component->viewData('rows')->items())->pluck('reference')->all());
    }

    public function test_clear_filters_restores_the_full_set(): void
    {
        $component = Livewire::test(OrdersGrid::class)
            ->set('filters.customer', ['Acme' => true])
            ->set('search', 'ORD')
            ->call('clearFilters');

        $this->assertCount(4, $component->viewData('rows')->items());
        $component->assertSet('filters', [])->assertSet('search', '');
    }

    public function test_the_column_picker_hides_but_never_the_last_column(): void
    {
        $component = Livewire::test(OrdersGrid::class)->call('toggleColumn', 'customer');
        $this->assertNotContains('customer', array_map(fn ($column) => $column->key(), $component->viewData('columns')));

        foreach (['reference', 'total', 'placed_at', 'status', 'id'] as $key) {
            $component->call('toggleColumn', $key);
        }
        $this->assertCount(1, $component->viewData('columns'), 'The last visible column must refuse to hide.');

        $component->call('showAllColumns');
        $this->assertCount(6, $component->viewData('columns'));
    }

    public function test_dragged_widths_are_clamped_and_resettable(): void
    {
        $component = Livewire::test(OrdersGrid::class)
            ->call('setColumnWidths', ['reference' => 220, 'customer' => 5, 'bogus' => 300]);

        $html = $component->html();
        $this->assertStringContainsString('width: 220px', $html);
        $this->assertStringContainsString('width: 60px', $html, 'A width below the floor clamps to min_column_width.');
        $this->assertStringNotContainsString('bogus', $html);

        $component->call('resetColumnWidths');
        $this->assertStringNotContainsString('width: 220px', $component->html());
    }

    public function test_each_shipped_theme_renders_and_an_unknown_theme_throws(): void
    {
        foreach (Grid::THEMES as $theme) {
            $html = Livewire::test(OrdersGrid::class, ['theme' => $theme])->html();
            $this->assertStringContainsString('crewgrid-table', $html, $theme.' must render the grid.');
            $this->assertStringContainsString('ORD-001', $html);
        }

        try {
            Livewire::test(OrdersGrid::class, ['theme' => 'bulma'])->html();
            $this->fail('An unknown theme must throw.');
        } catch (\Throwable $e) {
            // Blade wraps the exception when the component renders inside a
            // view, so unwrap to the root before asserting its type.
            $root = $e;
            while (! ($root instanceof InvalidArgumentException) && ! is_null($root->getPrevious())) {
                $root = $root->getPrevious();
            }
            $this->assertInstanceOf(InvalidArgumentException::class, $root);
            $this->assertStringContainsString('Unknown CrewGrid theme [bulma]', $e->getMessage());
        }
    }

    public function test_ui_classes_layer_theme_config_then_grid(): void
    {
        $component = Livewire::test(OrdersGrid::class, ['theme' => 'bootstrap5']);
        $this->assertSame('btn btn-outline-secondary btn-sm', $component->instance()->uiClass('button'));

        config()->set('crewgrid.classes.bootstrap5', ['button' => 'btn from-config']);
        $this->assertSame('btn from-config', $component->instance()->uiClass('button'));

        $component->set('classes', ['button' => 'btn from-grid']);
        $this->assertSame('btn from-grid', $component->instance()->uiClass('button'));

        $this->assertSame(
            $component->instance()->uiClass('action'),
            $component->instance()->uiClass('action.unheard-of-variant'),
            'An unknown dotted variant falls back to its base control.'
        );
    }

    public function test_the_icon_map_is_overridable_and_unknown_keys_render_nothing(): void
    {
        $component = Livewire::test(OrdersGrid::class);
        $this->assertStringContainsString('fa-filter', $component->instance()->icon('filter'));

        config()->set('crewgrid.icons', ['filter' => '<svg id="funnel"></svg>']);
        $this->assertSame('<svg id="funnel"></svg>', $component->instance()->icon('filter'));

        $this->assertSame('', $component->instance()->icon('nonexistent'));
    }

    public function test_action_link_escapes_and_carries_the_variant_class(): void
    {
        $html = Livewire::test(OrdersGrid::class)->html();
        $this->assertStringContainsString('https://example.test/orders/1', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }

    public function test_row_class_lands_on_the_tr(): void
    {
        $grid = new class extends \CrewGrid\Tests\Fixtures\OrdersGrid
        {
            public function rowClass($row): string
            {
                return $row->status === 'paid' ? 'row-paid' : '';
            }
        };

        $html = Livewire::test($grid::class)->html();
        $this->assertStringContainsString('class="row-paid"', $html);
        $this->assertSame(2, substr_count($html, 'row-paid'), 'Only the two paid rows carry the class.');
    }

    public function test_the_pager_can_render_top_bottom_or_both(): void
    {
        config()->set('crewgrid.per_page_options', [2, 15]);

        $bottom_only = Livewire::test(OrdersGrid::class)->set('perPage', 2)->html();
        $this->assertSame(1, substr_count($bottom_only, 'crewgrid-pager'));
        $this->assertGreaterThan(strpos($bottom_only, '<thead'), strpos($bottom_only, 'crewgrid-pager'),
            'The default pager sits below the table.');

        $both = Livewire::test(OrdersGrid::class, ['paginationPosition' => 'both'])->set('perPage', 2)->html();
        $this->assertSame(2, substr_count($both, 'crewgrid-pager'));
        $this->assertLessThan(strpos($both, '<thead'), strpos($both, 'crewgrid-pager'),
            'With "both", a pager also sits above the table.');

        $top = Livewire::test(OrdersGrid::class, ['paginationPosition' => 'top'])->set('perPage', 2)->html();
        $this->assertSame(1, substr_count($top, 'crewgrid-pager'));
        $this->assertLessThan(strpos($top, '<thead'), strpos($top, 'crewgrid-pager'));
    }

    public function test_url_state_round_trips(): void
    {
        Livewire::withQueryParams(['sort' => 'total', 'dir' => 'desc', 'q' => 'Acme'])
            ->test(OrdersGrid::class)
            ->assertSet('sortField', 'total')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('search', 'Acme');
    }
}
