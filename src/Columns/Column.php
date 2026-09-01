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

    public bool $wrap = false;

    public bool $resizable = true;

    /** Markup rendered before the header label - author markup, not escaped. */
    public ?string $icon = null;

    public bool $exportable = true;

    public ?Closure $exportCallback = null;

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
     * Comparison filter for numeric columns: an operator dropdown (larger
     * than, lower than, not equal to, ...) plus a number input. Without a
     * callback the comparison applies to the column's field; with one it is
     * called as fn ($query, string $operator, float $value) - the place to
     * compare an aggregate via HAVING.
     */
    public function filterNumber(?Closure $callback = null): static
    {
        $this->filterType = 'number';
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

    /**
     * Let this column's cells wrap onto several lines. Cells are kept on one
     * line by default so rows stay scannable and the table scrolls sideways;
     * a column of free text (a note, a long description) reads better wrapped.
     */
    public function wrap(): static
    {
        $this->wrap = true;

        return $this;
    }

    public function notResizable(): static
    {
        $this->resizable = false;

        return $this;
    }

    /**
     * An icon before the header label. Takes markup rather than a class name
     * so any icon set works, including inline SVG:
     *
     *     ->icon('<i class="fa fa-calendar"></i>')
     */
    public function icon(string $markup): static
    {
        $this->icon = $markup;

        return $this;
    }

    /**
     * Leave this column out of Excel exports - an action column of buttons
     * has nothing to say in a spreadsheet.
     */
    public function notExportable(): static
    {
        $this->exportable = false;

        return $this;
    }

    /**
     * The value this column exports: fn ($value, $row) => ... Without it,
     * plain columns export their format() result and html()/view() columns
     * fall back to the raw field value, since their rendered output is markup.
     */
    public function exportAs(Closure $callback): static
    {
        $this->exportCallback = $callback;

        return $this;
    }

    /**
     * What lands in the spreadsheet cell for $row, following the contract
     * described on exportAs().
     */
    public function exportValue($row)
    {
        $value = data_get($row, $this->field);

        if (! is_null($this->exportCallback)) {
            return call_user_func($this->exportCallback, $value, $row);
        }

        if (! is_null($this->formatCallback) && ! $this->html && is_null($this->view)) {
            return call_user_func($this->formatCallback, $value, $row);
        }

        return $value;
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
        if ($this->wrap) {
            $classes[] = 'crewgrid-wrap';
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
