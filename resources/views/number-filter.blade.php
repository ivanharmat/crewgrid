{{-- Operator dropdown + number input for a filterNumber column. Nothing
     applies until the number is filled; the operator defaults to the first
     option (Larger than). --}}
<select class="{{ $this->uiClass('input') }}" wire:model.live="filters.{{ $column->key() }}.op" style="margin-bottom: 4px;">
    @foreach(\CrewGrid\Grid::NUMBER_OPERATOR_LABELS as $operatorLabel => $operatorValue)
        <option value="{{ $operatorValue }}">{{ $operatorLabel }}</option>
    @endforeach
</select>
<input type="number" step="any" class="{{ $this->uiClass('input') }}" placeholder="Number ..." wire:model.live.debounce.400ms="filters.{{ $column->key() }}.value">
