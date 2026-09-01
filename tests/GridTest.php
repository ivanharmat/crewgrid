<?php

namespace CrewGrid\Tests;

use CrewGrid\Columns\Column;
use CrewGrid\Grid;
use CrewGrid\Tests\Fixtures\ForgetfulOrdersGrid;
use CrewGrid\Tests\Fixtures\GroupedOrdersGrid;
use CrewGrid\Tests\Fixtures\Order;
use CrewGrid\Tests\Fixtures\OrdersGrid;
use CrewGrid\Tests\Fixtures\SearchCallbackOrdersGrid;
use CrewGrid\Tests\Fixtures\SeededOrdersGrid;
use CrewGrid\Tests\Fixtures\ToolbarOrdersGrid;
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

    public function test_filter_menus_are_viewport_positioned_so_nothing_clips_them(): void
    {
        Livewire::test(OrdersGrid::class)
            ->assertSeeHtml('x-data="crewGridPopover()"')
            ->assertSeeHtml('x-data="crewGridPopover(\'right\')"')
            ->assertSeeHtml('x-ref="trigger"')
            ->assertSeeHtml('x-ref="panel"')
            ->assertSeeHtml('crewgrid-placed')
            // bound, not written onto the element: a Livewire morph would wipe
            // an inline style and drop the panel into the page corner
            ->assertSeeHtml('pos.top')
            ->assertSeeHtml('pos.left');
    }

    public function test_the_table_fills_its_box_scrolls_sideways_and_never_shortens_a_header(): void
    {
        $html = Livewire::test(OrdersGrid::class)
            ->assertSeeHtml('crewgrid-scroll')   // the grid owns its horizontal scrolling
            ->assertSee('Reference')             // headers render in full
            ->html();

        $this->assertStringContainsString('.crewgrid-table { margin-bottom: 0; width: 100%; }', $html);
        $this->assertStringContainsString('.crewgrid-scroll { overflow-x: auto; }', $html);
        $this->assertStringContainsString('> td { white-space: nowrap; }', $html, 'Cells stay on one line by default.');
        $this->assertStringNotContainsString('text-overflow: ellipsis;
        vertical-align: bottom;', $html, 'Headers are not truncated.');
    }

    public function test_dragged_widths_clip_instead_of_spilling_over_the_next_column(): void
    {
        // an unsized grid lays out to content: nothing is cut off
        $html = Livewire::test(OrdersGrid::class)->html();
        $this->assertStringNotContainsString('crewgrid-fixed', $this->tableTag($html));

        // once a width is stored the table is fixed-layout, where a cell or a
        // header narrower than its text must be clipped, not spill sideways
        $this->assertStringContainsString('.crewgrid-table.crewgrid-fixed > tbody > tr > td {', $html);
        $this->assertStringContainsString('.crewgrid-table.crewgrid-fixed .crewgrid-th-label {', $html);

        $sized = Livewire::test(OrdersGrid::class)->call('setColumnWidths', ['total' => 90]);
        $this->assertStringContainsString('crewgrid-fixed', $this->tableTag($sized->html()), 'A stored width puts the table in fixed layout.');

        $sized->call('resetColumnWidths');
        $this->assertStringNotContainsString('crewgrid-fixed', $this->tableTag($sized->html()), 'Reset Widths goes back to content-sized columns.');
    }

    /**
     * The grid's <table> tag on its own - the shipped stylesheet mentions
     * every class name, so the whole page cannot answer "is it fixed?".
     */
    private function tableTag(string $html): string
    {
        preg_match('/<table[^>]*>/', $html, $match);

        return $match[0] ?? '';
    }

    public function test_a_column_can_opt_back_into_wrapping(): void
    {
        $plain = Column::make('Note', 'note');
        $this->assertStringNotContainsString('crewgrid-wrap', $plain->cssClass());

        $wrapped = Column::make('Note', 'note')->wrap();
        $this->assertStringContainsString('crewgrid-wrap', $wrapped->cssClass());
    }

    public function test_number_filters_compare_with_the_chosen_operator(): void
    {
        Livewire::test(OrdersGrid::class)
            ->set('filters.total', ['op' => '>', 'value' => '200'])
            ->assertSee('ORD-002')->assertSee('ORD-004')->assertDontSee('ORD-001')
            ->set('filters.total', ['op' => '!=', 'value' => '100'])
            ->assertSee('ORD-002')->assertDontSee('ORD-001')
            ->set('filters.total', ['op' => '<=', 'value' => '100'])
            ->assertSee('ORD-001')->assertDontSee('ORD-004')
            ->set('filters.total', ['op' => '', 'value' => '150'])
            ->assertSee('ORD-002')->assertDontSee('ORD-001') // blank op defaults to larger-than
            ->set('filters.total', ['op' => 'DROP TABLE', 'value' => '1'])
            ->assertSee('ORD-001') // unknown operators are ignored, filter inert
            ->set('filters.total', ['op' => '>', 'value' => ''])
            ->assertSee('ORD-001'); // no number, no filtering
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

    public function test_the_rows_per_page_menu_shows_the_number_actually_in_use(): void
    {
        // $perPage starts null so the configured default can change under it;
        // a null-bound select showed its first option (15) while the grid was
        // paging by the configured 30
        $component = Livewire::test(OrdersGrid::class)->assertSet('perPage', null);
        $this->assertSame(30, (int) config('crewgrid.per_page'));
        $component->assertSeeHtml('<option value="30" selected>');
        $component->assertDontSeeHtml('<option value="15" selected>');

        $component->call('setPerPage', 15)
            ->assertSet('perPage', 15)
            ->assertSeeHtml('<option value="15" selected>');

        // a number outside the offered list is ignored
        $component->call('setPerPage', 7)->assertSet('perPage', 15);
    }

    public function test_the_clear_filters_button_is_a_danger_button(): void
    {
        $html = Livewire::test(OrdersGrid::class)->set('filters.reference', 'ORD')->html();

        $this->assertStringContainsString('bg-red', $html, 'Clear Filters stands out as destructive.');
        $this->assertStringContainsString('Clear Filters', $html);
    }

    public function test_a_searchable_column_can_narrow_the_search_itself(): void
    {
        // the default matches anywhere in the field
        Livewire::test(OrdersGrid::class)
            ->set('search', '002')
            ->assertSee('ORD-002')->assertDontSee('ORD-001');

        // the callback here matches the start of the reference instead
        Livewire::test(SearchCallbackOrdersGrid::class)
            ->set('search', 'ORD-002')
            ->assertSee('ORD-002')->assertDontSee('ORD-001');

        Livewire::test(SearchCallbackOrdersGrid::class)
            ->set('search', '002')
            ->assertDontSee('ORD-002');
    }

    public function test_a_grid_comes_back_to_the_view_its_user_left(): void
    {
        config()->set('crewgrid.remember_view', true);

        Livewire::test(OrdersGrid::class)
            ->call('sortBy', 'total')
            ->set('filters.customer', ['Acme' => true])
            ->call('setPerPage', 50);

        // a fresh visit, nothing in the URL: the same view is back
        $returning = Livewire::test(OrdersGrid::class)
            ->assertSet('sortField', 'total')
            ->assertSet('sortDirection', 'asc')
            ->assertSet('filters', ['customer' => ['Acme' => true]])
            ->assertSet('perPage', 50);

        $this->assertCount(2, $returning->viewData('rows')->items());

        // and clearing them is remembered too, rather than coming back next time
        $returning->call('clearFilters');
        Livewire::test(OrdersGrid::class)->assertSet('filters', []);
    }

    public function test_the_url_beats_the_remembered_view_and_replaces_it(): void
    {
        config()->set('crewgrid.remember_view', true);

        Livewire::test(OrdersGrid::class)->call('sortBy', 'total');

        // a link someone was sent shows what it says ...
        Livewire::withQueryParams(['sort' => 'customer', 'dir' => 'desc'])
            ->test(OrdersGrid::class)
            ->assertSet('sortField', 'customer')
            ->assertSet('sortDirection', 'desc');

        // ... and is where they pick up from
        Livewire::test(OrdersGrid::class)->assertSet('sortField', 'customer');
    }

    public function test_the_remembered_view_holds_the_sort_and_the_filters_and_nothing_else(): void
    {
        config()->set('crewgrid.remember_view', true);

        $grid = Livewire::test(OrdersGrid::class)->call('sortBy', 'total')->set('paginators.page', 2);

        $stored = session('crewgrid.'.$grid->instance()->getName());

        $this->assertSame(
            ['sortField', 'sortDirection', 'filters', 'search', 'perPage'],
            array_keys($stored['view'])
        );
        // the page number is left out on purpose - coming back on page 2 of a
        // list is disorienting in a way that its filters are not
        $this->assertArrayNotHasKey('page', $stored['view']);
        $this->assertSame('total', $stored['view']['sortField']);

        // and it sits beside the widths and hidden columns, not instead of them
        $this->assertArrayHasKey('widths', $stored);
        $this->assertArrayHasKey('hidden', $stored);
    }

    public function test_a_seeded_filter_opens_the_grid_but_clearing_it_sticks(): void
    {
        config()->set('crewgrid.remember_view', true);

        // nothing remembered yet, so the grid opens on its own filter
        $first = Livewire::test(SeededOrdersGrid::class)
            ->assertSet('filters', ['customer' => ['Acme' => true]]);
        $this->assertCount(2, $first->viewData('rows')->items());

        $first->call('clearFilters');

        // and the clearing is what comes back, not the seed
        $returning = Livewire::test(SeededOrdersGrid::class)->assertSet('filters', []);
        $this->assertCount(4, $returning->viewData('rows')->items());
    }

    public function test_a_grid_can_decline_to_remember_anything(): void
    {
        config()->set('crewgrid.remember_view', true);

        Livewire::test(ForgetfulOrdersGrid::class)->call('sortBy', 'total');

        Livewire::test(ForgetfulOrdersGrid::class)->assertSet('sortField', '');
    }

    public function test_grids_remember_on_by_default(): void
    {
        $shipped = require __DIR__.'/../config/crewgrid.php';

        $this->assertTrue($shipped['remember_view']);
    }

    public function test_a_grid_can_put_its_own_control_in_the_toolbar(): void
    {
        $component = Livewire::test(ToolbarOrdersGrid::class);

        // the control renders inside the component, so it binds a property
        $component->assertSeeHtml('wire:model.live="scope"');

        // and that property scopes the query the grid runs
        $this->assertCount(2, $component->viewData('rows')->items());
        $component->set('scope', 'all');
        $this->assertCount(4, $component->viewData('rows')->items());

        // the toolbar's left half widens to hold it
        $component->assertSeeHtml('class="col-sm-6 crewgrid-toolbar-start"');

        // a grid without one is untouched
        Livewire::test(OrdersGrid::class)
            ->assertSeeHtml('class="col-sm-4 crewgrid-toolbar-start"')
            ->assertDontSeeHtml('wire:model.live="scope"');
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
        $grid = new class extends OrdersGrid
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
