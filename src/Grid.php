<?php

namespace CrewGrid;

use CrewGrid\Columns\Column;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
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

    /** "pager" or "infinite" - how additional rows load. */
    public string $loadMode = 'pager';

    /** Column borders and row lines. Null falls back to config. */
    public ?bool $bordered = null;

    /** Rows currently shown in infinite mode. */
    public int $infiniteLoaded = 0;

    /**
     * Resolved viewing preferences, memoised per request. Not a public
     * property: this is server state, and Livewire would round-trip it
     * through the client on every request for nothing.
     *
     * @var array{widths: array<string, int>, hidden: array<int, string>}|null
     */
    private ?array $gridPreferences = null;

    abstract protected function query(): BuilderContract;

    /** @return array<int, Column> */
    abstract protected function columns(): array;

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
     * @return array{widths: array<string, int>, hidden: array<int, string>}
     */
    protected function preferences(): array
    {
        if (is_null($this->gridPreferences)) {
            $stored = $this->loadPreferences();
            $this->gridPreferences = [
                'widths' => is_array($stored['widths'] ?? null) ? $stored['widths'] : [],
                'hidden' => is_array($stored['hidden'] ?? null) ? array_values($stored['hidden']) : [],
            ];
        }

        return $this->gridPreferences;
    }

    /**
     * @param  array{widths?: array<string, int>, hidden?: array<int, string>}  $changes
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
        $per_page = $this->effectivePerPage();

        if ($this->loadMode === 'infinite') {
            $rows = $this->buildQuery()->paginate(max($per_page, $this->infiniteLoaded), ['*'], 'page', 1);
        } else {
            $rows = $this->buildQuery()->paginate($per_page);
        }

        $theme = $this->theme ?? config('crewgrid.theme', 'bootstrap3');

        return view('crewgrid::themes.'.$theme.'.grid', [
            'columns' => $this->visibleColumns(),
            'pickerColumns' => $this->columns(),
            'rows' => $rows,
            'perPageOptions' => (array) config('crewgrid.per_page_options', [15, 30, 50, 100]),
            'minColumnWidth' => (int) config('crewgrid.min_column_width', 60),
        ]);
    }
}
