<div x-data="crewGridResize({{ $minColumnWidth }})" @pointermove.window="move($event)" @pointerup.window="stop()">
    @include('crewgrid::themes.tailwind.assets')
    <div class="mb-2 flex flex-wrap items-center gap-2">
        <div class="w-full sm:w-64">
            @if(collect($columns)->contains(fn ($c) => $c->searchable))
                <input type="text" class="{{ $this->uiClass('input') }}" placeholder="Search ..." wire:model.live.debounce.400ms="search">
            @endif
        </div>
        <div class="ml-auto flex items-center gap-2">
            <span wire:loading.delay class="text-sm text-gray-500">Loading {!! $this->icon('loading') !!}</span>
            @if($this->isExportable())
                <button type="button" class="{{ $this->uiClass('button') }}" wire:click="export" wire:loading.attr="disabled" title="Export the current view to Excel - all pages, filters applied">{!! $this->icon('export') !!} Excel</button>
            @endif
            @if($this->hasDraggedWidths())
                <button type="button" class="{{ $this->uiClass('button') }}" wire:click="resetColumnWidths" title="Reset column widths">{!! $this->icon('resize') !!} Reset Widths</button>
            @endif
            <div x-data="{ open: false }" @click.outside="open = false" class="crewgrid-th-filter">
                <button type="button" class="{{ $this->uiClass('button') }}" @click="open = !open" title="Show or hide columns">
                    {!! $this->icon('columns') !!} Columns
                    @php $hiddenCount = count($pickerColumns) - count($columns); @endphp
                    @if($hiddenCount > 0)
                        <span class="{{ $this->uiClass('badge') }}">{{ $hiddenCount }}</span>
                    @endif
                </button>
                <div x-show="open" x-cloak class="crewgrid-popover crewgrid-popover-right">
                    @foreach($pickerColumns as $pickerColumn)
                        <label class="flex items-center gap-2 whitespace-nowrap py-0.5 text-sm text-gray-700" wire:key="crewgrid-pick-{{ $pickerColumn->key() }}">
                            <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" wire:click="toggleColumn('{{ $pickerColumn->key() }}')" @checked(!$this->isColumnHidden($pickerColumn->key()))>
                            {{ $pickerColumn->label }}
                            {{-- A filter on a hidden column still applies - say so, or it
                                 looks like the grid is dropping rows for no reason. --}}
                            @if($this->hasActiveFilter($pickerColumn))
                                <span title="Filtered" class="text-indigo-600">{!! $this->icon('filter') !!}</span>
                            @endif
                        </label>
                    @endforeach
                    @if($hiddenCount > 0)
                        <div class="crewgrid-popover-footer">
                            <a href="#" class="{{ $this->uiClass('link') }}" wire:click.prevent="showAllColumns">{!! $this->icon('show_all') !!} Show All</a>
                        </div>
                    @endif
                </div>
            </div>
            @if(!empty($filters) || $search !== '')
                <button type="button" class="{{ $this->uiClass('button') }}" wire:click="clearFilters">{!! $this->icon('clear') !!} Clear Filters</button>
            @endif
            <select class="{{ $this->uiClass('select') }}" wire:model.live="perPage" title="Rows per page">
                @foreach($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($loadMode !== 'infinite' && $this->showsPagerAt('top'))
        @include('crewgrid::themes.tailwind.pager')
    @endif
    <div class="overflow-x-auto" @pointerdown="start($event)">
        <table class="w-full text-left text-sm crewgrid-table crewgrid-plain {{ $this->isBordered() ? 'crewgrid-bordered' : '' }} {{ $this->hasFixedLayout() ? 'crewgrid-fixed' : '' }}">
            {{-- Server-rendered, so widths survive a morph and the column set can
                 change when columns are hidden. --}}
            <colgroup>
                @foreach($columns as $column)
                    @php $renderedWidth = $this->columnWidth($column); @endphp
                    <col data-crewgrid-col="{{ $column->key() }}" @if(!is_null($renderedWidth)) style="width: {{ $renderedWidth }};" @endif>
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th wire:key="crewgrid-head-{{ $column->key() }}" class="font-semibold text-gray-700 {{ $column->cssClass() }}">
                            {{-- Only the label truncates. The filter sits outside this
                                 wrapper so its popover is never clipped by the ellipsis. --}}
                            <span class="crewgrid-th-label">
                                @if(!is_null($column->icon)){!! $column->icon !!} @endif
                                @if($column->sortable)
                                    <a href="#" class="hover:underline" wire:click.prevent="sortBy('{{ $column->key() }}')">
                                        {{ $column->label }}
                                        @if($sortField === $column->key())
                                            {!! $this->icon($sortDirection === 'asc' ? 'sort_asc' : 'sort_desc') !!}
                                        @else
                                            {!! $this->icon('sort') !!}
                                        @endif
                                    </a>
                                @else
                                    {{ $column->label }}
                                @endif
                            </span>
                            @if(!is_null($column->filterType))
                                @php
                                    $filterActive = $this->hasActiveFilter($column);
                                    $filterOptions = $column->filterType === 'multiselect' ? $column->resolveFilterOptions() : [];
                                @endphp
                                <div x-data="{ open: false, q: '' }" @click.outside="open = false" class="crewgrid-th-filter">
                                    <a href="#" @click.prevent="open = !open" title="Filter" class="{{ $filterActive ? 'text-indigo-600' : 'text-gray-400' }}">
                                        {!! $this->icon('filter') !!}
                                    </a>
                                    <div x-show="open" x-cloak class="crewgrid-popover">
                                        @if($column->filterType === 'text')
                                            <input type="text" class="{{ $this->uiClass('input') }}" placeholder="Filter {{ $column->label }} ..." wire:model.live.debounce.400ms="filters.{{ $column->key() }}" x-ref="input" x-effect="if (open) $nextTick(() => $refs.input.focus())">
                                        @elseif($column->filterType === 'multiselect')
                                            @if(count($filterOptions) > 8)
                                                <input type="text" class="{{ $this->uiClass('input') }} mb-2" placeholder="Find ..." x-model="q">
                                            @endif
                                            <div class="crewgrid-options">
                                                @foreach($filterOptions as $optionValue => $optionLabel)
                                                    <label class="flex items-center gap-2 whitespace-nowrap py-0.5 font-normal text-gray-700" data-label="{{ Str::lower($optionLabel) }}" x-show="q === '' || $el.dataset.label.includes(q.toLowerCase())">
                                                        <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" wire:model.live="filters.{{ $column->key() }}.{{ $optionValue }}">
                                                        {{ $optionLabel }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif($column->filterType === 'date_range')
                                            <input type="date" class="{{ $this->uiClass('input') }} mb-1" wire:model.live="filters.{{ $column->key() }}.from" title="From">
                                            <input type="date" class="{{ $this->uiClass('input') }}" wire:model.live="filters.{{ $column->key() }}.to" title="To">
                                            @include('crewgrid::date-presets')
                                        @endif
                                        @if($filterActive)
                                            <div class="crewgrid-popover-footer">
                                                <a href="#" class="{{ $this->uiClass('link') }}" wire:click.prevent="$set('filters.{{ $column->key() }}', {{ $column->filterType === 'text' ? "''" : '[]' }})">{!! $this->icon('clear') !!} Clear</a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            @if($column->resizable && !$loop->last)
                                <span class="crewgrid-resizer" data-crewgrid-col="{{ $column->key() }}" title="Drag to resize"></span>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody @if($this->isGrouped()) x-data="{ crewgridOpen: {}, crewgridAllState: null }" @endif>
                @include('crewgrid::rows', ['emptyCellClass' => 'py-6 text-center text-gray-500'])
            </tbody>
        </table>
    </div>

    @if($loadMode === 'infinite')
        <div class="mb-4 mt-2 text-center">
            <span class="text-sm text-gray-500">Showing {{ $rows->count() }} of {{ number_format($rows->total()) }}</span><br>
            @if($rows->count() < $rows->total())
                <button type="button" class="{{ $this->uiClass('button') }} mt-1" wire:click="loadMore" wire:loading.attr="disabled">
                    {!! $this->icon('load_more') !!} Load More
                </button>
            @endif
        </div>
    @elseif($this->showsPagerAt('bottom'))
        @include('crewgrid::themes.tailwind.pager')
    @endif
</div>
