<?php

namespace CrewGrid;

use CrewGrid\Columns\Column;
use CrewGrid\Export\XlsxWriter;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Base class for a CrewGrid grid: subclass, return an Eloquent query from
 * query() and a Column[] from columns(). Sorting, per-column filters, quick
 * search and pagination run server-side; sort/filter/page state lives in the
 * URL so any grid view is shareable. Markup comes from the configured theme
 * (bootstrap3 / bootstrap5 / tailwind), overridable per grid via $theme.
 */
abstract class Grid extends Component
{
    use WithPagination;

    /** Themes shipped with the package. @var array<int, string> */
    public const THEMES = ['bootstrap3', 'bootstrap5', 'tailwind'];

    /**
     * Per-theme classes for the controls the grid renders. Read in the theme
     * views as $this->uiClass('button'); override a few keys in
     * config('crewgrid.classes.{theme}') or per grid via $classes, and only
     * fork a view when the markup itself has to change.
     *
     * Tailwind users: these are utility classes in a PHP file, so this path
     * has to be in tailwind.config.js content or they get purged - see the
     * Tailwind setup section of the README.
     *
     * @var array<string, array<string, string>>
     */
    public const DEFAULT_CLASSES = [
        'bootstrap3' => [
            'button' => 'btn btn-default btn-sm',
            'button.danger' => 'btn bg-red btn-sm',
            'input' => 'form-control input-sm',
            'select' => 'form-control input-sm',
            'link' => '',
            'badge' => 'badge',
            'action' => 'btn btn-xs btn-default',
            'action.primary' => 'btn btn-xs bg-blue',
            'action.info' => 'btn btn-xs bg-purple',
            'action.success' => 'btn btn-xs bg-green',
            'action.danger' => 'btn btn-xs bg-red',
        ],
        'bootstrap5' => [
            'button' => 'btn btn-outline-secondary btn-sm',
            'button.danger' => 'btn btn-danger btn-sm',
            'input' => 'form-control form-control-sm',
            'select' => 'form-select form-select-sm d-inline-block w-auto',
            'link' => '',
            'badge' => 'badge bg-secondary',
            'action' => 'btn btn-sm btn-outline-secondary',
            'action.primary' => 'btn btn-sm btn-primary',
            'action.info' => 'btn btn-sm btn-info',
            'action.success' => 'btn btn-sm btn-success',
            'action.danger' => 'btn btn-sm btn-danger',
        ],
        'tailwind' => [
            'button' => 'inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-sm text-gray-700 shadow-sm hover:bg-gray-50',
            'button.danger' => 'inline-flex items-center gap-1 rounded-md border border-transparent bg-red-600 px-2.5 py-1 text-sm text-white shadow-sm hover:bg-red-700',
            'input' => 'block w-full rounded-md border border-gray-300 px-2 py-1 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500',
            'select' => 'rounded-md border border-gray-300 py-1 pl-2 pr-7 text-sm shadow-sm',
            'link' => 'text-sm text-indigo-600 hover:underline',
            'badge' => 'ml-1 rounded-full bg-gray-600 px-1.5 text-xs text-white',
            'action' => 'inline-flex items-center rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50',
            'action.primary' => 'inline-flex items-center rounded bg-indigo-600 px-2 py-0.5 text-xs text-white hover:bg-indigo-700',
            'action.info' => 'inline-flex items-center rounded bg-purple-600 px-2 py-0.5 text-xs text-white hover:bg-purple-700',
            'action.success' => 'inline-flex items-center rounded bg-green-600 px-2 py-0.5 text-xs text-white hover:bg-green-700',
            'action.danger' => 'inline-flex items-center rounded bg-red-600 px-2 py-0.5 text-xs text-white hover:bg-red-700',
        ],
    ];

    /**
     * Class overrides for this grid, e.g. ['button' => 'btn btn-primary btn-sm'].
     *
     * @var array<string, string>
     */
    public array $classes = [];

    /**
     * Markup for the icons the grid renders. Font Awesome 4 by default because
     * that is what the themes were written against - override the whole map or
     * a few keys in config('crewgrid.icons') for a different set, including
     * inline SVG (Heroicons, Lucide). An icon set that has no equivalent for a
     * key can map it to '' and that spot simply renders nothing.
     *
     * @var array<string, string>
     */
    /**
     * The comparisons a number filter accepts, in the order the dropdown
     * offers them: label => SQL operator.
     */
    public const NUMBER_OPERATOR_LABELS = [
        'Larger than' => '>',
        'Equal or larger' => '>=',
        'Lower than' => '<',
        'Equal or lower' => '<=',
        'Equal to' => '=',
        'Not equal to' => '!=',
    ];

    public const NUMBER_OPERATORS = ['>', '>=', '<', '<=', '=', '!='];

    public const DEFAULT_ICONS = [
        'filter' => '<i class="fa fa-filter"></i>',
        'columns' => '<i class="fa fa-columns"></i>',
        'clear' => '<i class="fa fa-times"></i>',
        'show_all' => '<i class="fa fa-eye"></i>',
        'resize' => '<i class="fa fa-arrows-h"></i>',
        'load_more' => '<i class="fa fa-angle-double-down"></i>',
        'loading' => '<i class="fa fa-spinner fa-spin"></i>',
        // crewgrid-muted rather than text-muted / text-gray-400: the icon map
        // is shared by every theme, so it cannot lean on a framework's class.
        'sort' => '<i class="fa fa-sort crewgrid-muted"></i>',
        'sort_asc' => '<i class="fa fa-sort-asc"></i>',
        'sort_desc' => '<i class="fa fa-sort-desc"></i>',
        'export' => '<i class="fa fa-file-excel-o"></i>',
        'group_closed' => '<i class="fa fa-chevron-right"></i>',
        'group_open' => '<i class="fa fa-chevron-down"></i>',
    ];

    /**
     * Icon overrides for this grid.
     *
     * @var array<string, string>
     */
    public array $icons = [];

    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDirection = 'asc';

    /** @var array<string, mixed> */
    #[Url(as: 'f', except: [])]
    public array $filters = [];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: null)]
    public ?int $perPage = null;

    /** Override to force a theme for this grid ("bootstrap3", "tailwind", ...). */
    public ?string $theme = null;

    /** Whether this grid offers an Excel export. Null falls back to config. */
    public ?bool $exportable = null;

    /** Where the pager row renders: "bottom", "top" or "both". Null falls back to config. */
    public ?string $paginationPosition = null;

    /** "pager" or "infinite" - how additional rows load. */
    public string $loadMode = 'pager';

    /** Column borders and row lines. Null falls back to config. */
    public ?bool $bordered = null;

    /**
     * Whether the grid remembers its sort, filters, quick search and page
     * size between visits. Null falls back to config.
     */
    public ?bool $rememberView = null;

    /** Rows currently shown in infinite mode. */
    public int $infiniteLoaded = 0;

    /**
     * Resolved viewing preferences, memoised per request. Not a public
     * property: this is server state, and Livewire would round-trip it
     * through the client on every request for nothing.
     *
     * @var array{widths: array<string, int>, hidden: array<int, string>, view: array<string, mixed>}|null
     */
    private ?array $gridPreferences = null;

    abstract protected function query(): BuilderContract;

    /** @return array<int, Column> */
    abstract protected function columns(): array;

    /**
     * What a grid remembers between visits, property => the query string name
     * the same value travels under. The page number is deliberately absent:
     * coming back on page 47 of a list is disorienting in a way that coming
     * back to the filters that produced it is not.
     */
    private const REMEMBERED = [
        'sortField' => 'sort',
        'sortDirection' => 'dir',
        'filters' => 'f',
        'search' => 'q',
        'perPage' => 'perPage',
    ];

    /**
     * Restores the view the user left behind, and must be called by any grid
     * that defines a mount() of its own:
     *
     *     public function mount(): void
     *     {
     *         parent::mount();
     *         ...
     *     }
     *
     * A value named in the URL wins over the stored one and replaces it, so a
     * link someone was sent shows what it says and picks up from there, while
     * a bare address returns to whatever that user last had on screen. A grid
     * setting its own defaults in mount() does so after this, and only sees
     * the untouched property when there was nothing to restore.
     */
    public function mount(): void
    {
        if (! $this->remembersView()) {
            return;
        }

        $stored = $this->preferences()['view'];
        $named = request()->query();

        foreach (self::REMEMBERED as $property => $parameter) {
            if (array_key_exists($parameter, $named) || ! array_key_exists($property, $stored)) {
                continue;
            }
            $this->{$property} = $stored[$property];
        }
    }

    public function remembersView(): bool
    {
        return $this->rememberView ?? (bool) config('crewgrid.remember_view', true);
    }

    /**
     * Stores the view as it now stands. Called from render(), so it records
     * whatever the request settled on however it got there - a sort click, a
     * filter, a shared link, a default a grid applies in its own mount() -
     * without every one of those having to remember to save.
     */
    private function rememberCurrentView(): void
    {
        if (! $this->remembersView()) {
            return;
        }

        $view = [];
        foreach (array_keys(self::REMEMBERED) as $property) {
            $view[$property] = $this->{$property};
        }

        if ($view !== $this->preferences()['view']) {
            $this->writePreferences(['view' => $view]);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetRows();
    }

    public function updatedFilters(): void
    {
        $this->resetRows();
    }

    public function updatedPerPage(): void
    {
        $this->resetRows();
    }

    /**
     * The rows-per-page menu picks through here rather than binding straight
     * to $perPage: the property starts null so the configured default can
     * change under it, and a null-bound select shows its first option while
     * the grid pages by the configured number - the menu said 15 while 30
     * rows were on screen.
     */
    public function setPerPage(int $perPage): void
    {
        if (! in_array($perPage, (array) config('crewgrid.per_page_options', [15, 30, 50, 100]), true)) {
            return;
        }

        $this->perPage = $perPage;
        $this->resetRows();
    }

    public function sortBy(string $key): void
    {
        if ($this->sortField === $key) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $key;
            $this->sortDirection = 'asc';
        }
        $this->resetRows();
    }

    public function clearFilters(): void
    {
        $this->filters = [];
        $this->search = '';
        $this->resetRows();
    }

    public function loadMore(): void
    {
        $this->infiniteLoaded = max($this->effectivePerPage(), $this->infiniteLoaded) + $this->effectivePerPage();
    }

    protected function resetRows(): void
    {
        $this->resetPage();
        $this->infiniteLoaded = 0;
    }

    protected function effectivePerPage(): int
    {
        $allowed = (array) config('crewgrid.per_page_options', [15, 30, 50, 100]);

        return in_array($this->perPage, $allowed, true) ? $this->perPage : (int) config('crewgrid.per_page', 30);
    }

    protected function buildQuery(): BuilderContract
    {
        $query = $this->query();
        $columns = collect($this->columns())->keyBy(fn (Column $column) => $column->key());

        $search = trim($this->search);
        if ($search !== '') {
            $searchable = $columns->filter(fn (Column $column) => $column->searchable);
            if ($searchable->isNotEmpty()) {
                $query->where(function ($outer) use ($searchable, $search) {
                    foreach ($searchable as $column) {
                        if (! is_null($column->searchCallback)) {
                            call_user_func($column->searchCallback, $outer, $search);

                            continue;
                        }
                        $outer->orWhere($column->field, 'like', '%'.$search.'%');
                    }
                });
            }
        }

        foreach ($this->filters as $key => $value) {
            $column = $columns->get($key);
            if (is_null($column) || is_null($column->filterType) || $value === '' || $value === [] || is_null($value)) {
                continue;
            }

            if ($column->filterType === 'multiselect') {
                // Checkboxes bind a per-option map {value: bool}; only arrays
                // count (a stray scalar from a hand-edited URL is ignored).
                $selected = is_array($value) ? array_keys(array_filter($value)) : [];
                if ($selected === []) {
                    continue;
                }
                if (! is_null($column->filterCallback)) {
                    call_user_func($column->filterCallback, $query, $selected);
                } else {
                    $query->whereIn($column->field, $selected);
                }

                continue;
            }

            if ($column->filterType === 'number') {
                // ['op' => comparison, 'value' => number]; nothing applies
                // until the number is filled and both parts are valid.
                $operator = is_array($value) ? (string) ($value['op'] ?? '') : '';
                $operator = $operator === '' ? '>' : $operator;
                $number = is_array($value) ? trim((string) ($value['value'] ?? '')) : '';
                if ($number === '' || ! is_numeric($number) || ! in_array($operator, self::NUMBER_OPERATORS, true)) {
                    continue;
                }
                if (! is_null($column->filterCallback)) {
                    call_user_func($column->filterCallback, $query, $operator, (float) $number);
                } else {
                    $query->where($column->field, $operator, (float) $number);
                }

                continue;
            }

            if (! is_null($column->filterCallback)) {
                call_user_func($column->filterCallback, $query, $value);

                continue;
            }

            match ($column->filterType) {
                'text' => $query->where($column->field, 'like', '%'.trim((string) $value).'%'),
                'date_range' => $this->applyDateRange($query, $column->field, (array) $value),
                default => null,
            };
        }

        $sort_column = $columns->get($this->sortField);
        if (! is_null($sort_column) && $sort_column->sortable) {
            $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';
            if (! is_null($sort_column->sortCallback)) {
                call_user_func($sort_column->sortCallback, $query, $direction);
            } else {
                $query->orderBy($sort_column->field, $direction);
            }
        }

        return $query;
    }

    /**
     * Columns actually rendered. Filters and quick search still run over the
     * full set - hiding a column changes the view, not the result set - so a
     * filter left on a hidden column keeps applying, and the column picker
     * marks it so it can still be found.
     *
     * @return array<int, Column>
     */
    public function visibleColumns(): array
    {
        $hidden = $this->preferences()['hidden'];
        $visible = array_values(array_filter(
            $this->columns(),
            fn (Column $column) => ! in_array($column->key(), $hidden, true)
        ));

        // A grid with no columns is a broken page, not a preference.
        return $visible === [] ? array_slice(array_values($this->columns()), 0, 1) : $visible;
    }

    public function isColumnHidden(string $key): bool
    {
        return in_array($key, $this->preferences()['hidden'], true);
    }

    /**
     * Rendered width for a column: what the user dragged, else what the
     * column declared, else nothing (the browser sizes it).
     */
    public function columnWidth(Column $column): ?string
    {
        $width = $this->preferences()['widths'][$column->key()] ?? null;

        return is_null($width) ? $column->width : $width.'px';
    }

    public function hasDraggedWidths(): bool
    {
        return $this->preferences()['widths'] !== [];
    }

    /**
     * Widths only bite under a fixed table layout, and a fixed layout with no
     * widths would make every column equal - so it is switched on only once
     * something has a width to honour.
     */
    public function hasFixedLayout(): bool
    {
        return $this->hasDraggedWidths()
            || collect($this->visibleColumns())->contains(fn (Column $column) => ! is_null($column->width));
    }

    /**
     * Whether a filter is currently set on this column - shown in the picker
     * so a filter on a hidden column is still findable.
     */
    public function hasActiveFilter(Column $column): bool
    {
        $value = $this->filters[$column->key()] ?? null;

        return match ($column->filterType) {
            'multiselect' => is_array($value) && count(array_filter($value)) > 0,
            'date_range' => is_array($value) && (! empty($value['from']) || ! empty($value['to'])),
            'text' => is_string($value) && trim($value) !== '',
            'number' => is_array($value) && trim((string) ($value['value'] ?? '')) !== '',
            default => false,
        };
    }

    public function toggleColumn(string $key): void
    {
        $keys = array_map(fn (Column $column) => $column->key(), $this->columns());
        if (! in_array($key, $keys, true)) {
            return;
        }

        $hidden = $this->preferences()['hidden'];
        if (in_array($key, $hidden, true)) {
            $hidden = array_values(array_diff($hidden, [$key]));
        } elseif (count($hidden) + 1 < count($keys)) { // never hide the last one
            $hidden[] = $key;
        }

        $this->writePreferences(['hidden' => $hidden]);
    }

    public function showAllColumns(): void
    {
        $this->writePreferences(['hidden' => []]);
    }

    /**
     * Persist the whole width map at the end of a drag. The browser sends
     * every column, not just the dragged one, because the drag freezes the
     * previously auto-sized columns at the same moment.
     *
     * @param  array<string, int|string>  $widths
     */
    public function setColumnWidths(array $widths): void
    {
        $minimum = (int) config('crewgrid.min_column_width', 60);
        $keys = array_map(fn (Column $column) => $column->key(), $this->columns());

        $clean = [];
        foreach ($widths as $key => $width) {
            if (in_array($key, $keys, true) && is_numeric($width)) {
                $clean[$key] = max($minimum, (int) $width);
            }
        }

        $this->writePreferences(['widths' => $clean]);
    }

    public function resetColumnWidths(): void
    {
        $this->writePreferences(['widths' => []]);
    }

    /**
     * @return array{widths: array<string, int>, hidden: array<int, string>, view: array<string, mixed>}
     */
    protected function preferences(): array
    {
        if (is_null($this->gridPreferences)) {
            $stored = $this->loadPreferences();
            $this->gridPreferences = [
                'widths' => is_array($stored['widths'] ?? null) ? $stored['widths'] : [],
                'hidden' => is_array($stored['hidden'] ?? null) ? array_values($stored['hidden']) : [],
                'view' => is_array($stored['view'] ?? null) ? $stored['view'] : [],
            ];
        }

        return $this->gridPreferences;
    }

    /**
     * @param  array{widths?: array<string, int>, hidden?: array<int, string>, view?: array<string, mixed>}  $changes
     */
    protected function writePreferences(array $changes): void
    {
        $this->gridPreferences = array_merge($this->preferences(), $changes);
        $this->savePreferences($this->gridPreferences);
    }

    /**
     * Where viewing preferences live. Session by default so nothing extra is
     * required of the host app; override both halves to persist per user (a
     * preferences table, a JSON column) and they will outlive the session.
     *
     * @return array<string, mixed>
     */
    protected function loadPreferences(): array
    {
        return (array) session()->get($this->preferenceKey(), []);
    }

    /**
     * @param  array<string, mixed>  $preferences
     */
    protected function savePreferences(array $preferences): void
    {
        session()->put($this->preferenceKey(), $preferences);
    }

    protected function preferenceKey(): string
    {
        return 'crewgrid.'.$this->getName();
    }

    public function resolvedTheme(): string
    {
        return $this->theme ?? (string) config('crewgrid.theme', 'bootstrap3');
    }

    /**
     * Classes for one of the grid's controls: button, input, select, link,
     * badge or action. Theme defaults, then config overrides, then this grid's
     * own - each layer only has to name the keys it wants to change.
     *
     * Any control takes dotted variants: uiClass('action.danger') uses that
     * key if it exists and otherwise falls back to plain 'action', so a name
     * the theme has never heard of still renders as a button rather than as
     * unstyled text. Invent your own ('action.warning') in config or $classes.
     */
    public function uiClass(string $control): string
    {
        $theme = $this->resolvedTheme();

        $classes = array_merge(
            self::DEFAULT_CLASSES[$theme] ?? [],
            (array) config('crewgrid.classes.'.$theme, []),
            $this->classes
        );

        if (isset($classes[$control])) {
            return (string) $classes[$control];
        }

        $base = strstr($control, '.', true);

        return (string) ($base === false ? '' : ($classes[$base] ?? ''));
    }

    /**
     * Markup for one of the grid's icons, rendered unescaped - it is author
     * markup from the icon map, never user data. An unknown or blank key
     * renders nothing rather than a broken glyph, so an icon set that has no
     * equivalent can simply leave it out.
     */
    public function icon(string $name): string
    {
        $icons = array_merge(
            self::DEFAULT_ICONS,
            (array) config('crewgrid.icons', []),
            $this->icons
        );

        return (string) ($icons[$name] ?? '');
    }

    /**
     * A themed link for an action cell, escaped and ready to return from a
     * Column::format() callback on an ->html() column. $variant picks an
     * "action.{variant}" class, falling back to the plain action button.
     *
     * Column::make('', 'id')->html()->format(fn ($value, $row) =>
     *     $this->actionLink('Estimate', url('estimates/'.$row->estimate_id), 'info'))
     *
     * $icon is markup, not a class name, so any icon set works:
     *
     *     $this->actionLink('Estimate', $url, 'info', '<i class="fa fa-file-o"></i>')
     */
    public function actionLink(string $label, string $url, string $variant = '', string $icon = '', bool $new_tab = true): string
    {
        return '<a href="'.e($url).'" class="'.e($this->uiClass($variant === '' ? 'action' : 'action.'.$variant)).'"'.
            ($new_tab ? ' target="_blank" rel="noopener"' : '').'>'.
            ($icon === '' ? '' : $icon.' ').e($label).'</a>';
    }

    /**
     * CSS classes for a row's <tr>, e.g. status colouring. Override in a grid:
     *
     *     protected function rowClass($row): string
     *     {
     *         return $row->overdue ? 'row-overdue' : '';
     *     }
     *
     * Classes, not inline styles, so the page owns its palette and themes can
     * restyle without the grid knowing any colours.
     */
    public function rowClass($row): string
    {
        return '';
    }

    /**
     * Attribute whose value bands the page's rows into collapsible groups -
     * one clickable heading row per group, member rows folded beneath it.
     * Null (the default) renders the flat grid. Rows are banded in the order
     * the query returns them, so groups appear wherever their best-sorted
     * row lands and never re-sort the data:
     *
     *     protected function groupBy(): ?string
     *     {
     *         return 'rfp_id';
     *     }
     */
    protected function groupBy(): ?string
    {
        return null;
    }

    /**
     * Heading markup for a group band, given the group's first row (in the
     * current sort) and its row count on this page. Override for a richer
     * heading; the result renders unescaped, so escape any data you include.
     */
    protected function groupHeading($row, int $count): string
    {
        return e((string) $row->{$this->groupBy()}).' <span class="crewgrid-group-count">('.$count.')</span>';
    }

    /**
     * Whether group bands start folded. Collapsed by default - one row per
     * group until it is clicked open.
     */
    protected function groupStartsCollapsed(): bool
    {
        return true;
    }

    /**
     * Quick ranges offered inside every date filter popover, label =>
     * ['from' => Y-m-d, 'to' => Y-m-d]. Override for a different set; return
     * [] to hide the presets on a grid.
     *
     * @return array<string, array{from: string, to: string}>
     */
    public function dateRangePresets(): array
    {
        $today = date('Y-m-d');

        return [
            'Today' => ['from' => $today, 'to' => $today],
            'Yesterday' => ['from' => date('Y-m-d', strtotime('-1 day')), 'to' => date('Y-m-d', strtotime('-1 day'))],
            'This Week' => ['from' => date('Y-m-d', strtotime('monday this week')), 'to' => $today],
            'Last 7 Days' => ['from' => date('Y-m-d', strtotime('-6 days')), 'to' => $today],
            'This Month' => ['from' => date('Y-m-01'), 'to' => $today],
            'Last 30 Days' => ['from' => date('Y-m-d', strtotime('-29 days')), 'to' => $today],
        ];
    }

    public function isGrouped(): bool
    {
        return $this->groupBy() !== null;
    }

    public function groupDefaultOpen(): bool
    {
        return ! $this->groupStartsCollapsed();
    }

    /**
     * The page's rows banded by the groupBy() attribute, preserving each
     * group's first appearance in the current sort order.
     *
     * @return list<array{key: string, hash: string, heading: string, rows: list<mixed>}>
     */
    public function groupedRows($rows): array
    {
        $key_field = $this->groupBy();
        $groups = [];
        foreach ($rows as $row) {
            $key = (string) $row->{$key_field};
            if (! isset($groups[$key])) {
                $groups[$key] = ['key' => $key, 'hash' => md5($key), 'first' => $row, 'heading' => '', 'rows' => []];
            }
            $groups[$key]['rows'][] = $row;
        }
        foreach ($groups as &$group) {
            $group['heading'] = $this->groupHeading($group['first'], count($group['rows']));
            unset($group['first']);
        }

        return array_values($groups);
    }

    /**
     * Whether the pager row renders at this edge of the table ("top" or
     * "bottom"). Infinite mode ignores the top pager - its Load More belongs
     * under the rows it extends.
     */
    public function showsPagerAt(string $edge): bool
    {
        $position = $this->paginationPosition ?? (string) config('crewgrid.pagination_position', 'bottom');

        return $position === 'both' || $position === $edge;
    }

    public function isExportable(): bool
    {
        return $this->exportable ?? (bool) config('crewgrid.export', true);
    }

    /**
     * A view rendered into the grid's toolbar, beside the quick search and
     * before the Excel / Columns buttons - for a control that scopes the
     * whole grid rather than one column, which no column filter can express:
     * an office picker, a date scope, active / archived / deleted.
     *
     *     protected function toolbarView(): ?string
     *     {
     *         return 'estimates.partials.estimate-type';
     *     }
     *
     * The view renders inside the component, so it binds a public property
     * with wire:model.live and reads $this like any other part of the grid.
     * Null (the default) leaves the toolbar as it is.
     */
    protected function toolbarView(): ?string
    {
        return null;
    }

    /**
     * Download the current result set - filters, quick search and sort
     * applied, every page, following what the Kendo grids' Excel button does.
     * Visible columns only, so the column picker shapes the file, minus any
     * marked notExportable(); each cell goes through Column::exportValue().
     */
    public function export()
    {
        if (! $this->isExportable()) {
            abort(403);
        }

        $columns = array_values(array_filter(
            $this->visibleColumns(),
            fn (Column $column) => $column->exportable
        ));
        if ($columns === []) {
            return null;
        }

        $query = $this->buildQuery();
        // lazy() pages with limit/offset, so an unordered query could repeat
        // or drop rows between chunks - anchor it on the primary key.
        if (empty($query->getQuery()->orders)) {
            $query->orderBy($query->getModel()->getQualifiedKeyName());
        }

        $writer = new XlsxWriter;
        $writer->writeRow(array_map(fn (Column $column) => $column->label, $columns), bold: true);
        foreach ($query->lazy((int) config('crewgrid.export_chunk', 500)) as $row) {
            $writer->writeRow(array_map(fn (Column $column) => $column->exportValue($row), $columns));
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'crewgrid-xlsx');
        $writer->save($path);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $this->exportFilename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * "user-actions-grid-2026-08-08.xlsx" - override for a nicer name.
     */
    protected function exportFilename(): string
    {
        return $this->getName().'-'.date('Y-m-d').'.xlsx';
    }

    /**
     * Whether to draw column borders and row lines. Read from the theme as
     * $this->isBordered(), not as view data: Livewire injects public
     * properties into the view after the explicit data, so a "bordered" key
     * would be overwritten by the null default of the property below it.
     */
    public function isBordered(): bool
    {
        return $this->bordered ?? (bool) config('crewgrid.bordered', true);
    }

    protected function applyDateRange(BuilderContract $query, string $field, array $value): void
    {
        if (! empty($value['from'])) {
            $query->where($field, '>=', $value['from'].' 00:00:00');
        }
        if (! empty($value['to'])) {
            $query->where($field, '<=', $value['to'].' 23:59:59');
        }
    }

    public function render()
    {
        $this->rememberCurrentView();

        $per_page = $this->effectivePerPage();

        if ($this->loadMode === 'infinite') {
            $rows = $this->buildQuery()->paginate(max($per_page, $this->infiniteLoaded), ['*'], 'page', 1);
        } else {
            $rows = $this->buildQuery()->paginate($per_page);
        }

        $theme = $this->resolvedTheme();

        // A typo would otherwise surface as "View [crewgrid::themes...] not
        // found", which reads like a broken package rather than a setting.
        if (! view()->exists('crewgrid::themes.'.$theme.'.grid')) {
            throw new InvalidArgumentException(
                'Unknown CrewGrid theme ['.$theme.']. Available: '.implode(', ', self::THEMES).
                '. Set it in config/crewgrid.php or with $theme on the grid.'
            );
        }

        return view('crewgrid::themes.'.$theme.'.grid', [
            'columns' => $this->visibleColumns(),
            'pickerColumns' => $this->columns(),
            'rows' => $rows,
            'perPageOptions' => (array) config('crewgrid.per_page_options', [15, 30, 50, 100]),
            'perPageSelected' => $this->effectivePerPage(),
            'minColumnWidth' => (int) config('crewgrid.min_column_width', 60),
            'toolbarView' => $this->toolbarView(),
        ]);
    }
}
