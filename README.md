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

Markup is rendered from `crewgrid::themes.{theme}` Blade views. Set the theme app-wide in config, or per grid with `public ?string $theme = 'bootstrap5';`. Publish the views to override any markup. An unrecognised theme name throws a named exception rather than a "view not found".

Available themes:

- `bootstrap3` — AdminLTE-friendly
- `bootstrap5`
- `tailwind`

Only the markup differs. Sorting, filters, the column picker, resizing and the width-carrying `<colgroup>` are driven by the component, so every theme behaves identically. The stylesheet and resize script are shared (`crewgrid::assets`); each theme adds only what its framework needs.

### Restyling buttons, inputs and links

Every control the grid renders takes its classes from the theme, and you can override them without publishing a view. App-wide, naming only the keys you want to change:

```php
// config/crewgrid.php
'classes' => [
    'bootstrap5' => ['button' => 'btn btn-primary btn-sm'],
],
```

Or per grid:

```php
public array $classes = ['button' => 'btn btn-danger btn-sm'];
```

The keys are `button`, `input`, `select`, `link`, `badge` and `action`; defaults live in `CrewGrid\Grid::DEFAULT_CLASSES`. Anything you leave out keeps the theme's default, so there is never a need to restate a whole theme.

**Variants.** Any control takes a dotted suffix, and an unknown one falls back to the base control rather than rendering unstyled:

```php
$this->uiClass('action.danger');   // the theme's danger action button
$this->uiClass('action.warning');  // not shipped -> plain 'action', until you define it
```

So you are not limited to one kind of button. Define as many as you like:

```php
'classes' => [
    'tailwind' => [
        'action.warning' => 'inline-flex items-center rounded bg-amber-500 px-2 py-0.5 text-xs text-white',
    ],
],
```

**Buttons inside cells.** Action buttons in a column are rendered by *your* `format()` callback, so hardcoding `btn btn-xs bg-purple` there ties that grid to Bootstrap 3. `actionLink()` builds a themed, escaped link instead — and because `columns()` is a method on your component, `$this` is available inside the callback:

```php
Column::make('Links', 'id')
    ->html()
    ->format(fn ($value, $row) => $this->actionLink('Estimate', url('estimates/'.$row->estimate_id), 'info'));
```

Shipped variants are `primary`, `info`, `success` and `danger`, plus the plain `action`. For markup `actionLink()` can't produce (a button with an icon, a `wire:click`), build it yourself and reach for the same classes: `'<button class="'.$this->uiClass('action.danger').'" wire:click="...">'`.

For anything CSS can reach, you don't need either — the grid's own hooks are stable class names: `.crewgrid-table`, `.crewgrid-bordered`, `.crewgrid-th-label`, `.crewgrid-resizer`, `.crewgrid-popover`, `.crewgrid-popover-footer`, `.crewgrid-options`.

Publish the views only when the *markup* has to change (different icons, an extra toolbar control):

```bash
php artisan vendor:publish --tag=crewgrid-views
```

Views resolve per file, so delete the ones you didn't mean to own — whatever you keep is frozen at that version and stops receiving updates.

### Icons

The themes ship **Font Awesome 4** markup, because that is what they were built against. Nothing is hardcoded in the views — every icon comes from a map you can override, a key at a time or wholesale:

```php
// config/crewgrid.php
'icons' => [
    'filter' => '<i class="bi bi-funnel"></i>',                 // Bootstrap Icons
    'sort_asc' => '<svg class="h-3 w-3" viewBox="0 0 20 20">…</svg>',  // inline SVG
    'loading' => '',                                            // render nothing
],
```

Values are **markup, not class names**, so any icon set works — Font Awesome 6, Bootstrap Icons, Heroicons, Lucide, inline SVG. A key mapped to `''` renders nothing rather than a broken glyph, and a key you leave out keeps its default. Per grid, use `public array $icons = [...]`.

Keys: `filter`, `columns`, `clear`, `show_all`, `resize`, `load_more`, `loading`, `sort`, `sort_asc`, `sort_desc` — see `CrewGrid\Grid::DEFAULT_ICONS`.

**Your own icons.** Column headers and action buttons take them too:

```php
Column::make('Date', 'timestamp')->icon('<i class="fa fa-calendar"></i>');

$this->actionLink('Estimate', $url, 'info', icon: '<i class="fa fa-file-o"></i>');
```

Both take markup and render it unescaped — it is yours, not user data. The action link's *label* is still escaped.

If you use no icon font at all, map every key to `''` and the grid renders on text alone.

### Tailwind setup

The Tailwind theme uses utility classes, so Tailwind has to be told to scan the package or they will be purged. It needs **two** paths — the Blade views, and the PHP file the control classes live in:

```js
// tailwind.config.js
content: [
    './vendor/ivanharmat/crewgrid/resources/views/**/*.blade.php',
    './vendor/ivanharmat/crewgrid/src/**/*.php',
    // ...
],
```

The second one is easy to miss. Theme markup is in Blade, but the button, input and `action.*` variant classes are values in `Grid::DEFAULT_CLASSES` — a PHP file the scanner has no reason to look at otherwise. Leaving it out costs 21 utilities (measured against Tailwind 3): the table renders correctly, but every `action.*` button loses its background colour, the per-page select and the hidden-column badge lose their padding and shape, and — the one that actually matters — inputs lose `focus:ring-1` / `focus:border-indigo-500` / `focus:outline-none`, so keyboard focus becomes invisible.

The alternative, if you would rather not point Tailwind at `vendor/` twice, is to define the classes in your own config, where your scanner already looks:

```php
// config/crewgrid.php
'classes' => [
    'tailwind' => [
        'button' => 'inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-sm',
        'action.info' => 'inline-flex items-center rounded bg-purple-600 px-2 py-0.5 text-xs text-white',
    ],
],
```

Config files are not scanned either by default, so add `'./config/crewgrid.php'` to `content` if you take that route — the point is that it is *your* file, in a path you control, rather than one inside `vendor/`.

Table padding, striping, hover and row lines ship as plain CSS with the theme rather than as utilities, so a grid still renders legibly if you get any of this wrong — you'll just get an unstyled toolbar rather than an unreadable page. Icons are Font Awesome class names (`fa fa-filter`), the same as the Bootstrap themes; swap them in a published view if you use a different icon set.

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
