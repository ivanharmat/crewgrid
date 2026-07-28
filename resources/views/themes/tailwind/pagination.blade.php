@php
    $base = 'inline-flex min-w-[2rem] items-center justify-center rounded-md border px-2 py-1 text-sm';
    $idle = $base.' border-gray-300 bg-white text-gray-700 hover:bg-gray-50';
    $off = $base.' border-gray-200 bg-gray-50 text-gray-400';
    $on = $base.' border-indigo-600 bg-indigo-600 text-white';
@endphp
@if ($paginator->hasPages())
    <div class="flex flex-wrap items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="{{ $off }}">&laquo;</span>
        @else
            <a href="#" class="{{ $idle }}" wire:click.prevent="previousPage" rel="prev">&laquo;</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="{{ $off }}">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="{{ $on }}">{{ $page }}</span>
                    @else
                        <a href="#" class="{{ $idle }}" wire:click.prevent="gotoPage({{ $page }})">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="#" class="{{ $idle }}" wire:click.prevent="nextPage" rel="next">&raquo;</a>
        @else
            <span class="{{ $off }}">&raquo;</span>
        @endif
    </div>
@endif
