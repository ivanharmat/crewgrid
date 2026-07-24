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

    /** Rows currently shown in infinite mode. */
    public int $infiniteLoaded = 0;

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
            'columns' => $this->columns(),
            'rows' => $rows,
            'perPageOptions' => (array) config('crewgrid.per_page_options', [15, 30, 50, 100]),
        ]);
    }
}
