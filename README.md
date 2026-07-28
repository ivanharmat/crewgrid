# CrewGrid

A server-driven [Livewire](https://livewire.laravel.com) data grid for Laravel — sorting, per-column filters, quick search, pagination or infinite loading, and shareable URL state. Themeable markup with **Bootstrap 3 support**, for the many production apps that modern grid packages have left behind.

Born out of replacing a legacy Kendo UI grid system in a large Laravel app; extracted as a package so any Laravel/Livewire project can use it.

## Why CrewGrid?

- **Server-side everything** — filtering, sorting and pagination run in the database, so a grid over a 2M-row table behaves the same as one over 200 rows.
- **Bootstrap 3 theme out of the box** — legacy AdminLTE/Bootstrap 3 apps get a modern Livewire grid without a frontend rewrite. (Tailwind and Bootstrap 5 themes are on the roadmap.)
- **Kendo-style filter popovers** — a funnel icon on each filterable column opens a text, checkbox-list or date-range filter; active filters are highlighted.
- **Shareable URLs** — sort, filters, search and page live in the query string, so a filtered view can be bookmarked or sent to a coworker.
- **Plain Blade cells** — no JavaScript row templates; format cells with closures or render them with your own Blade views.

## Requirements

| | Minimum |
|---|---|
| PHP | 8.1 |
| Laravel | 10 |
| Livewire | 3.5 (Livewire 2 is not supported) |

## Installation

```bash
composer require ivanharmat/crewgrid
```

The service provider is auto-discovered. Optionally publish the config or views:

```bash
php artisan vendor:publish --tag=crewgrid-config
php artisan vendor:publish --tag=crewgrid-views
```

## Quick start

Create a Livewire component extending `CrewGrid\Grid` and describe your grid with a query and columns:

```php
<?php

namespace App\Livewire;

use App\Models\Order;
use CrewGrid\Columns\Column;
use CrewGrid\Grid;
use Illuminate\Contracts\Database\Eloquent\Builder;

class OrdersGrid extends Grid
{
    protected function query(): Builder
    {
        return Order::query()->with('customer');
    }

    protected function columns(): array
    {
        return [
            Column::make('Date', 'created_at')
                ->sortable()
                ->filterDateRange()
                ->format(fn ($value) => $value->format('M j, Y')),

            Column::make('Customer', 'customer_id')
                ->filterMultiSelect(fn () => Customer::pluck('name', 'id')->all())
                ->format(fn ($value, $row) => e($row->customer?->name)),

            Column::make('Reference', 'reference')
                ->sortable()
                ->searchable()
                ->filterText(),

            Column::make('Total', 'total')
                ->sortable()
                ->format(fn ($value) => '$'.number_format($value, 2)),

            Column::make('Actions', 'id')
                ->view('orders.grid-actions'), // gets $row and $value
        ];
    }
}
```

Drop it on any page:

```blade
<livewire:orders-grid />
```

That's it — sorting, filtering, quick search, pagination and URL state all work.

## Column API

| Method | What it does |
|---|---|
| `Column::make($label, $field)` | Define a column over a model attribute (dot paths work for display). |
| `->sortable(?Closure $callback)` | Header click sorts. Optional callback: `fn ($query, $direction)` for custom order logic. |
| `->searchable()` | Include the field in the grid-wide quick search box. |
| `->filterText(?Closure $callback)` | Funnel popover with a text input (`LIKE %…%` by default). |
| `->filterMultiSelect($options, ?Closure $callback)` | Checkbox-list popover. `$options` is `[value => label]` or a closure returning it; lists over 8 options get a find-box. Callback receives `fn ($query, array $selectedValues)`. |
| `->filterDateRange(?Closure $callback)` | From/To date popover. Callback receives `fn ($query, array $value)` with `from`/`to` keys — useful for unix-timestamp columns. |
| `->format(Closure $callback)` | Transform the cell value: `fn ($value, $row)`. |
| `->html()` | Render the formatted value as raw HTML (you escape user data). |
| `->view('some.blade')` | Render the cell with your Blade view (receives `$row` and `$value`). |
| `->width('180px')` | Starting width — an `int` is taken as pixels, strings pass through so `'20%'` works. Users can still drag from here. |
| `->align('right')` | `left` (default), `center` or `right`. |
| `->nowrap()` | Keep the cell on one line and ellipsize. Cells wrap by default, so narrowing a column never hides content. |
| `->notResizable()` | Fix this column's width — no drag handle. |

All default filter behaviors have callback escape hatches, so computed columns, joins and cross-database filters are all expressible.

## Appearance, column widths and show/hide

Column borders and row lines are on by default; turn them off app-wide with `'bordered' => false` in the config, or per grid with `public ?bool $bordered = false;`.

**Resizing.** Every column is resizable unless you call `->notResizable()`. Drag the divider in the header; a **Reset Widths** button appears once anything has been dragged. Columns without an explicit `->width()` keep whatever the browser lays out naturally — the grid freezes those widths at the moment a drag starts and only then switches to a fixed layout, so nothing snaps to equal widths. `config('crewgrid.min_column_width')` (default `60`) is the floor.

**Show/hide.** The **Columns** button in the toolbar lists every column with a checkbox, badges how many are hidden, and offers *Show All*. The last visible column can't be hidden. Filters and quick search always run over the full column set — hiding a column changes the view, not the result set — so a filter left on a hidden column keeps applying and is flagged with a funnel in the picker rather than silently shrinking the results.

**Where preferences live.** Both are server-side, kept in the session under `preferenceKey()`, so they survive a reload with no flash of unstyled columns and the `<colgroup>` can be rendered correctly on the server. They deliberately stay out of the query string: sorting and filtering describe *what* you are looking at and are worth sharing, while widths and hidden columns are personal viewing preferences that would only make shared links noisy. Override `loadPreferences()` / `savePreferences()` to store them per user instead and they will outlive the session:

```php
protected function loadPreferences(): array
{
    return auth()->user()->grid_preferences[$this->preferenceKey()] ?? [];
}
```

No build step and nothing to add to your layout — the small stylesheet and resize script are emitted inline once per page.

## Loading modes

```php
public string $loadMode = 'pager';    // classic paginator (default)
public string $loadMode = 'infinite'; // "Load More" appends rows
```

Set per grid. Page size comes from `config('crewgrid.per_page')`, with a per-page picker driven by `per_page_options`.

## Theming

```php
// config/crewgrid.php
'theme' => 'bootstrap3',
```

Markup is rendered from `crewgrid::themes.{theme}` Blade views. Set the theme app-wide in config, or per grid with `public ?string $theme = 'bootstrap3';`. Publish the views to override any markup.

Available themes:

- `bootstrap3` — ✅ available (AdminLTE-friendly)
- `bootstrap5` — planned
- `tailwind` — planned

## URL state

Grid state serializes to the query string automatically:

```
/orders?sort=created_at&dir=desc&q=smith&f[customer_id][42]=true&page=3
```

Opening that link restores the exact grid view.

## Status

Early release (`0.x`) — extracted from production use, but the API may still move before `1.0`. Feedback and issues welcome.

## License

[MIT](LICENSE)
