<div class="row crewgrid-pager">
    <div class="col-sm-4 text-muted small" style="padding-top: 8px;">
        Showing {{ number_format($rows->firstItem() ?? 0) }}-{{ number_format($rows->lastItem() ?? 0) }} of {{ number_format($rows->total()) }}
    </div>
    <div class="col-sm-8 text-right">
        {{ $rows->onEachSide(1)->links('crewgrid::themes.bootstrap3.pagination') }}
    </div>
</div>
