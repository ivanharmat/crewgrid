<div x-data="crewGridResize('{{ $widthStorageKey }}', {{ $minColumnWidth }})" @pointermove.window="move($event)" @pointerup.window="stop()">
    @include('crewgrid::themes.bootstrap3.assets')
    <div class="row" style="margin-bottom: 8px;">
        <div class="col-sm-4">
            @if(collect($columns)->contains(fn ($c) => $c->searchable))
                <input type="text" class="form-control input-sm" placeholder="Search ..." wire:model.live.debounce.400ms="search">
            @endif
        </div>
        <div class="col-sm-8 text-right">
            <span wire:loading.delay class="text-muted small" style="margin-right: 8px;">Loading <i class="fa fa-spinner fa-spin"></i></span>
            <button type="button" class="btn btn-default btn-sm" x-show="customised" x-cloak @click="resetWidths()" title="Reset column widths"><i class="fa fa-arrows-h"></i> Reset Widths</button>
            @if(!empty($filters) || $search !== '')
                <button type="button" class="btn btn-default btn-sm" wire:click="clearFilters"><i class="fa fa-times"></i> Clear Filters</button>
            @endif
            <select class="form-control input-sm" style="width: auto; display: inline-block;" wire:model.live="perPage" title="Rows per page">
                @foreach($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="table-responsive" @pointerdown="start($event)">
        <table class="table table-striped table-hover table-condensed crewgrid-table {{ $this->isBordered() ? 'crewgrid-bordered' : '' }}">
            {{-- Ignored by the morph so dragged widths survive a sort/filter/page.
                 Safe because columns() is fixed for the life of a grid class. --}}
            <colgroup wire:ignore>
                @foreach($columns as $column)
                    <col data-crewgrid-col="{{ $column->key() }}" data-crewgrid-width="{{ $column->width }}" @if(!is_null($column->width)) style="width: {{ $column->width }};" @endif>
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th wire:key="crewgrid-head-{{ $column->key() }}" class="{{ $column->cssClass() }}">
                            {{-- Only the label truncates. The filter sits outside this
                                 wrapper so its popover is never clipped by the ellipsis. --}}
                            <span class="crewgrid-th-label">
                                @if($column->sortable)
                                    <a href="#" wire:click.prevent="sortBy('{{ $column->key() }}')">
                                        {{ $column->label }}
                                        @if($sortField === $column->key())
                                            <i class="fa fa-sort-{{ $sortDirection === 'asc' ? 'asc' : 'desc' }}"></i>
                                        @else
                                            <i class="fa fa-sort text-muted"></i>
                                        @endif
                                    </a>
                                @else
                                    {{ $column->label }}
                                @endif
                            </span>
                            @if(!is_null($column->filterType))
                                @php
                                    $filterValue = $filters[$column->key()] ?? null;
                                    $filterActive = match ($column->filterType) {
                                        'multiselect' => is_array($filterValue) && count(array_filter($filterValue)) > 0,
                                        'date_range' => is_array($filterValue) && (!empty($filterValue['from']) || !empty($filterValue['to'])),
                                        default => is_string($filterValue) && trim($filterValue) !== '',
                                    };
                                    $filterOptions = $column->filterType === 'multiselect' ? $column->resolveFilterOptions() : [];
                                @endphp
                                <div x-data="{ open: false, q: '' }" @click.outside="open = false" class="crewgrid-th-filter">
                                    <a href="#" @click.prevent="open = !open" title="Filter" class="{{ $filterActive ? 'text-primary' : 'text-muted' }}" style="{{ $filterActive ? '' : 'opacity: .55;' }}">
                                        <i class="fa fa-filter"></i>
                                    </a>
                                    <div x-show="open" x-cloak style="position: absolute; top: 100%; left: -8px; z-index: 1000; background: #fff; border: 1px solid #ccc; border-radius: 3px; box-shadow: 0 4px 12px rgba(0,0,0,.18); padding: 10px; min-width: 230px; margin-top: 4px; font-weight: normal; text-align: left; white-space: normal;">
                                        @if($column->filterType === 'text')
                                            <input type="text" class="form-control input-sm" placeholder="Filter {{ $column->label }} ..." wire:model.live.debounce.400ms="filters.{{ $column->key() }}" x-ref="input" x-effect="if (open) $nextTick(() => $refs.input.focus())">
                                        @elseif($column->filterType === 'multiselect')
                                            @if(count($filterOptions) > 8)
                                                <input type="text" class="form-control input-sm" placeholder="Find ..." x-model="q" style="margin-bottom: 6px;">
                                            @endif
                                            <div style="max-height: 240px; overflow-y: auto;">
                                                @foreach($filterOptions as $optionValue => $optionLabel)
                                                    <div class="checkbox" style="margin: 3px 0;" data-label="{{ Str::lower($optionLabel) }}" x-show="q === '' || $el.dataset.label.includes(q.toLowerCase())">
                                                        <label style="font-weight: normal; white-space: nowrap; display: block; padding: 1px 4px;">
                                                            <input type="checkbox" wire:model.live="filters.{{ $column->key() }}.{{ $optionValue }}"> {{ $optionLabel }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif($column->filterType === 'date_range')
                                            <input type="date" class="form-control input-sm" wire:model.live="filters.{{ $column->key() }}.from" title="From" style="margin-bottom: 4px;">
                                            <input type="date" class="form-control input-sm" wire:model.live="filters.{{ $column->key() }}.to" title="To">
                                        @endif
                                        @if($filterActive)
                                            <div style="border-top: 1px solid #eee; margin-top: 8px; padding-top: 6px;">
                                                <a href="#" wire:click.prevent="$set('filters.{{ $column->key() }}', {{ $column->filterType === 'text' ? "''" : '[]' }})"><i class="fa fa-times"></i> Clear</a>
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
            <tbody>
                @forelse($rows as $row)
                    <tr wire:key="crewgrid-row-{{ $row->getKey() }}">
                        @foreach($columns as $column)
                            <td class="{{ $column->cssClass() }}">@include('crewgrid::themes.bootstrap3.cell')</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="text-center text-muted" style="padding: 25px;">
                            No records found{{ !empty($filters) || $search !== '' ? ' - try adjusting the filters.' : '.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($loadMode === 'infinite')
        <div class="text-center" style="margin-bottom: 15px;">
            <span class="text-muted small">Showing {{ $rows->count() }} of {{ number_format($rows->total()) }}</span><br>
            @if($rows->count() < $rows->total())
                <button type="button" class="btn btn-default btn-sm" wire:click="loadMore" wire:loading.attr="disabled">
                    <i class="fa fa-angle-double-down"></i> Load More
                </button>
            @endif
        </div>
    @else
        <div class="row">
            <div class="col-sm-4 text-muted small" style="padding-top: 8px;">
                Showing {{ number_format($rows->firstItem() ?? 0) }}-{{ number_format($rows->lastItem() ?? 0) }} of {{ number_format($rows->total()) }}
            </div>
            <div class="col-sm-8 text-right">
                {{ $rows->onEachSide(1)->links('crewgrid::themes.bootstrap3.pagination') }}
            </div>
        </div>
    @endif
</div>
