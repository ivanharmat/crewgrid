@php
    $value = data_get($row, $column->field);
    if (! is_null($column->formatCallback)) {
        $value = call_user_func($column->formatCallback, $value, $row);
    }
@endphp
@if(!is_null($column->view))
    @include($column->view, ['row' => $row, 'value' => $value])
@elseif($column->html)
    {!! $value !!}
@else
    {{ $value }}
@endif
