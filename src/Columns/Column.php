<?php

namespace CrewGrid\Columns;

use Closure;

/**
 * Fluent column definition for a CrewGrid grid.
 *
 * Column::make('User', 'user_id')
 *     ->sortable()
 *     ->searchable()
 *     ->filterMultiSelect(fn () => User::pluck('name', 'id'))
 *     ->format(fn ($value, $row) => e($row->user_info?->name))
 */
class Column
{
    public string $label;

    public string $field;

    public bool $sortable = false;

    public bool $searchable = false;

    /** @var 'text'|'multiselect'|'date_range'|null */
    public ?string $filterType = null;

    /** @var Closure|array<int|string, string>|null */
    public $filterOptions = null;

    public ?Closure $formatCallback = null;

    public ?Closure $sortCallback = null;

    public ?Closure $filterCallback = null;

    public bool $html = false;

    public ?string $view = null;

    /** CSS width for the column ("180px", "12%"), or null to size to content. */
    public ?string $width = null;

    /** @var 'left'|'center'|'right' */
    public string $align = 'left';

    public bool $nowrap = false;

    public bool $resizable = true;

    final private function __construct(string $label, string $field)
    {
        $this->label = $label;
        $this->field = $field;
    }

    public static function make(string $label, string $field): static
    {
        return new static($label, $field);
    }

    public function sortable(?Closure $callback = null): static
    {
        $this->sortable = true;
        $this->sortCallback = $callback;

        return $this;
    }

    /**
     * Include this column in the grid-wide quick search.
     */
    public function searchable(): static
    {
        $this->searchable = true;

        return $this;
    }

    public function filterText(?Closure $callback = null): static
    {
        $this->filterType = 'text';
        $this->filterCallback = $callback;

        return $this;
    }

    /**
     * Checkbox-list filter. Options: [value => label] or a Closure returning it.
     *
     * @param  Closure|array<int|string, string>  $options
     */
    public function filterMultiSelect(Closure|array $options, ?Closure $callback = null): static
    {
        $this->filterType = 'multiselect';
        $this->filterOptions = $options;
        $this->filterCallback = $callback;

        return $this;
    }

    public function filterDateRange(?Closure $callback = null): static
    {
        $this->filterType = 'date_range';
        $this->filterCallback = $callback;

        return $this;
    }

    /**
     * Transform the cell value: fn ($value, $row) => ...
     */
    public function format(Closure $callback): static
    {
        $this->formatCallback = $callback;

        return $this;
    }

    /**
     * Render the format() result as raw HTML (caller escapes user data).
     */
    public function html(): static
    {
        $this->html = true;

        return $this;
    }

    /**
     * Render the cell with a Blade view (receives $row and $value).
     */
    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Starting width for the column - an int is treated as pixels. Users can
     * still drag it from here unless the column is notResizable().
     */
    public function width(string|int $width): static
    {
        $this->width = is_int($width) ? $width.'px' : $width;

        return $this;
    }

    /**
     * @param  'left'|'center'|'right'  $align
     */
    public function align(string $align): static
    {
        $this->align = $align;

        return $this;
    }

    /**
     * Keep the cell on one line and ellipsize the overflow. Cells wrap by
     * default so a narrowed column hides nothing.
     */
    public function nowrap(): static
    {
        $this->nowrap = true;

        return $this;
    }

    public function notResizable(): static
    {
        $this->resizable = false;

        return $this;
    }

    /**
     * Cell/header classes for alignment and wrapping.
     */
    public function cssClass(): string
    {
        $classes = [];
        if ($this->align !== 'left') {
            $classes[] = 'crewgrid-align-'.$this->align;
        }
        if ($this->nowrap) {
            $classes[] = 'crewgrid-nowrap';
        }

        return implode(' ', $classes);
    }

    /**
     * @return array<int|string, string>
     */
    public function resolveFilterOptions(): array
    {
        if (is_null($this->filterOptions)) {
            return [];
        }

        return $this->filterOptions instanceof Closure ? (array) call_user_func($this->filterOptions) : $this->filterOptions;
    }

    /**
     * A stable, URL-safe key for filter/sort state.
     */
    public function key(): string
    {
        return str_replace('.', '_', $this->field);
    }
}
