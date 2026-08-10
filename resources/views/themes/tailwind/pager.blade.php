<div class="my-2 flex flex-wrap items-center justify-between gap-2 crewgrid-pager">
    <div class="text-sm text-gray-500">
        Showing {{ number_format($rows->firstItem() ?? 0) }}-{{ number_format($rows->lastItem() ?? 0) }} of {{ number_format($rows->total()) }}
    </div>
    <div>{{ $rows->onEachSide(1)->links('crewgrid::themes.tailwind.pagination') }}</div>
</div>
