{{-- The tbody contents, shared by every theme (the including theme passes
     $emptyCellClass / $emptyCellStyle for its empty-state look). Two shapes:
     the flat row list, or - when the grid defines groupBy() - collapsible
     group bands: a clickable heading row per group with its member rows
     folded beneath it. Fold state is Alpine-local (crewgridOpen on the
     tbody), so it survives Livewire morphs and costs no round trips. --}}
@if($this->isGrouped())
    @php $crewgridGroups = $this->groupedRows($rows); @endphp
    @forelse($crewgridGroups as $crewgridGroup)
        @php $crewgridDefault = $this->groupDefaultOpen() ? 'true' : 'false'; @endphp
        <tr wire:key="crewgrid-group-{{ $crewgridGroup['hash'] }}" class="crewgrid-group-row">
            <td colspan="{{ count($columns) }}" @click="crewgridOpen['{{ $crewgridGroup['hash'] }}'] = !(crewgridOpen['{{ $crewgridGroup['hash'] }}'] ?? {{ $crewgridDefault }})">
                <span x-show="!(crewgridOpen['{{ $crewgridGroup['hash'] }}'] ?? {{ $crewgridDefault }})">{!! $this->icon('group_closed') !!}</span>
                <span x-show="crewgridOpen['{{ $crewgridGroup['hash'] }}'] ?? {{ $crewgridDefault }}" x-cloak>{!! $this->icon('group_open') !!}</span>
                {!! $crewgridGroup['heading'] !!}
            </td>
        </tr>
        @foreach($crewgridGroup['rows'] as $row)
            <tr x-show="crewgridOpen['{{ $crewgridGroup['hash'] }}'] ?? {{ $crewgridDefault }}" @if(!$this->groupDefaultOpen()) x-cloak @endif wire:key="crewgrid-row-{{ $row->getKey() }}" @if($this->rowClass($row) !== '') class="{{ $this->rowClass($row) }}" @endif>
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
