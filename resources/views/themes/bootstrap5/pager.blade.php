<div class="row crewgrid-pager">
    <div class="col-sm-4 text-muted small pt-2">
        Showing {{ number_format($rows->firstItem() ?? 0) }}-{{ number_format($rows->lastItem() ?? 0) }} of {{ number_format($rows->total()) }}
    </div>
    <div class="col-sm-8 d-flex justify-content-end">
        {{ $rows->onEachSide(1)->links('crewgrid::themes.bootstrap5.pagination') }}
    </div>
</div>
