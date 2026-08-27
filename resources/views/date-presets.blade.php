{{-- Quick ranges under the from/to inputs of a date filter popover. One
     click sets both bounds; the list comes from Grid::dateRangePresets(). --}}
@if(count($this->dateRangePresets()) > 0)
    <div class="crewgrid-date-presets">
        @foreach($this->dateRangePresets() as $presetLabel => $presetRange)
            <a href="#" wire:click.prevent="$set('filters.{{ $column->key() }}', { from: '{{ $presetRange['from'] }}', to: '{{ $presetRange['to'] }}' })">{{ $presetLabel }}</a>
        @endforeach
    </div>
@endif
