{{-- The tbody contents, shared by every theme (the including theme passes
     $emptyCellClass / $emptyCellStyle for its empty-state look). Two shapes:
     the flat row list, or - when the grid defines groupBy() - collapsible
     group bands: a clickable heading row per group with its member rows
     folded beneath it. Fold state is Alpine-local (crewgridOpen on the
     tbody), so it survives Livewire morphs and costs no round trips; the
     Show/Hide All links set crewgridAllState, an override that individual
     clicks then diverge from - no per-group bookkeeping needed. --}}
@if($this->isGrouped())
    @php
        $crewgridGroups = $this->groupedRows($rows);
        $crewgridDefault = $this->groupDefaultOpen() ? 'true' : 'false';
    @endphp
    @if(count($crewgridGroups) > 0)
        <tr class="crewgrid-group-toolbar" wire:key="crewgrid-group-toolbar">
            <td colspan="{{ count($columns) }}">
                <a href="javascript:void(0);" @click="crewgridAllState = true; crewgridOpen = {}">{!! $this->icon('group_open') !!} Show All</a>
                <a href="javascript:void(0);" @click="crewgridAllState = false; crewgridOpen = {}">{!! $this->icon('group_closed') !!} Hide All</a>
            </td>
        </tr>
    @endif
    @forelse($crewgridGroups as $crewgridGroup)
        @php $crewgridShown = "crewgridOpen['".$crewgridGroup['hash']."'] ?? crewgridAllState ?? ".$crewgridDefault; @endphp
        <tr wire:key="crewgrid-group-{{ $crewgridGroup['hash'] }}" class="crewgrid-group-row">
            <td colspan="{{ count($columns) }}" @click="crewgridOpen['{{ $crewgridGroup['hash'] }}'] = !({{ $crewgridShown }})">
                <span class="crewgrid-group-chevron">
                    <span x-show="!({{ $crewgridShown }})">{!! $this->icon('group_closed') !!}</span>
                    <span x-show="{{ $crewgridShown }}" x-cloak>{!! $this->icon('group_open') !!}</span>
                </span>
                {!! $crewgridGroup['heading'] !!}
            </td>
        </tr>
        @foreach($crewgridGroup['rows'] as $row)
            <tr x-show="{{ $crewgridShown }}" @if(!$this->groupDefaultOpen()) x-cloak @endif wire:key="crewgrid-row-{{ $row->getKey() }}" @if($this->rowClass($row) !== '') class="{{ $this->rowClass($row) }}" @endif>
                @foreach($columns as $column)
                    <td class="{{ $column->cssClass() }}">@include('crewgrid::cell')</td>
                @endforeach
            </tr>
        @endforeach
    @empty
        <tr>
            <td colspan="{{ count($columns) }}" class="{{ $emptyCellClass }}" @isset($emptyCellStyle) style="{{ $emptyCellStyle }}" @endisset>
                No records found{{ !empty($filters) || $search !== '' ? ' - try adjusting the filters.' : '.' }}
            </td>
        </tr>
    @endforelse
@else
    @forelse($rows as $row)
        <tr wire:key="crewgrid-row-{{ $row->getKey() }}" @if($this->rowClass($row) !== '') class="{{ $this->rowClass($row) }}" @endif>
            @foreach($columns as $column)
                <td class="{{ $column->cssClass() }}">@include('crewgrid::cell')</td>
            @endforeach
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($columns) }}" class="{{ $emptyCellClass }}" @isset($emptyCellStyle) style="{{ $emptyCellStyle }}" @endisset>
                No records found{{ !empty($filters) || $search !== '' ? ' - try adjusting the filters.' : '.' }}
            </td>
        </tr>
    @endforelse
@endif
