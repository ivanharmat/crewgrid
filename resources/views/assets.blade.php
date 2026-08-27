{{-- Shared by every theme. Emitted once per page however many grids are on
     it. Self-contained: no build step, no publishing, nothing to include in
     the host app's layout, and no dependency on the CSS framework in use -
     these rules cover the parts a framework has no opinion about (the fixed
     layout widths need, the resize handle, popover positioning). --}}
@once
<style>
    [x-cloak] { display: none !important; }

    .crewgrid-table { margin-bottom: 0; }

    /* Widths only bite under a fixed layout; width:auto + min-width lets a
       widened column push the table past the container so it scrolls instead
       of squeezing its neighbours. The class is added by JS once the starting
       widths are in place, so the grid still lays out naturally without it. */
    .crewgrid-table.crewgrid-fixed { table-layout: fixed; width: auto; min-width: 100%; }

    /* Must not clip: the filter popover is absolutely positioned inside the
       header cell, so overflow:hidden here hides it entirely. Truncation of a
       long header lives on the label wrapper below instead. */
    .crewgrid-table > thead > tr > th {
        position: relative;
        overflow: visible;
        background: #f6f7f9;
        border-bottom: 2px solid #d6dade;
        vertical-align: bottom;
        white-space: nowrap;
    }

    .crewgrid-th-label {
        display: inline-block;
        max-width: calc(100% - 20px);
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: bottom;
    }

    .crewgrid-th-filter {
        position: relative;
        display: inline-block;
        margin-left: 4px;
    }

    /* Fixed, not absolute: the grid sits inside a horizontally scrolling
       wrapper, and a table with two rows is shorter than the menu - either
       one would clip it. Positioned against the trigger by crewGridPopover()
       on open, which also flips it above when the viewport has no room
       below. Hidden until placed so it never flashes at the corner. */
    .crewgrid-popover {
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1050;
        visibility: hidden;
        min-width: 230px;
        max-height: calc(100vh - 16px);
        overflow-y: auto;
        padding: 10px;
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 3px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .18);
        font-weight: normal;
        text-align: left;
        white-space: normal;
    }
    .crewgrid-popover.crewgrid-placed { visibility: visible; }

    .crewgrid-popover .crewgrid-options { max-height: 240px; overflow-y: auto; }
    .crewgrid-popover-footer { border-top: 1px solid #eee; margin-top: 8px; padding-top: 6px; }

    /* Cells wrap by default: narrowing a column must never hide content. */
    .crewgrid-table > tbody > tr > td { word-break: break-word; }
    .crewgrid-table > tbody > tr > td.crewgrid-nowrap {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .crewgrid-bordered > thead > tr > th,
    .crewgrid-bordered > tbody > tr > td { border-right: 1px solid #e3e6e9; }
    .crewgrid-bordered > thead > tr > th:last-child,
    .crewgrid-bordered > tbody > tr > td:last-child { border-right: 0; }
    .crewgrid-bordered > tbody > tr > td { border-top: 1px solid #e8eaed; }

    .crewgrid-align-right { text-align: right; }
    .crewgrid-align-center { text-align: center; }
    /* Used by the shared icon map, which cannot reach for a framework's own
       muted-text class because it serves every theme. */
    .crewgrid-muted { opacity: .45; }

    /* Collapsible group bands (Grid::groupBy) */
    .crewgrid-group-row > td {
        cursor: pointer;
        font-weight: 600;
        background: #eef1f4;
        border-top: 2px solid #d6dade;
        -webkit-user-select: none;
        user-select: none;
    }
    .crewgrid-group-row > td:hover { background: #e4e8ec; }
    .crewgrid-group-count { opacity: .55; font-weight: 400; }
    .crewgrid-group-chevron { float: right; opacity: .6; }

    /* Quick ranges inside a date filter popover */
    .crewgrid-date-presets { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px 10px; }
    .crewgrid-date-presets a { font-size: 12px; white-space: nowrap; text-decoration: none; }
    .crewgrid-group-toolbar > td {
        text-align: right;
        background: #f6f7f9;
        padding: 3px 8px;
        font-size: 12px;
    }
    .crewgrid-group-toolbar a { margin-left: 12px; text-decoration: none; }

    .crewgrid-resizer {
        position: absolute;
        top: 0;
        right: -4px;
        width: 8px;
        height: 100%;
        cursor: col-resize;
        z-index: 5;
    }
    .crewgrid-resizer::after {
        content: '';
        position: absolute;
        top: 4px;
        bottom: 4px;
        left: 3px;
        width: 2px;
        background: transparent;
    }
    .crewgrid-resizer:hover::after { background: #3c8dbc; }
    /* While dragging, the pointer leaves the handle - keep the whole page in
       resize mode so the cursor does not flicker and text cannot be selected. */
    body.crewgrid-resizing { cursor: col-resize !important; }
    body.crewgrid-resizing * { user-select: none !important; }
</style>

<script>
    /* Widths are server state: the drag is applied straight to the DOM so it
       tracks the pointer, then the whole map is sent once on release. The
       server re-renders the colgroup from it, so a width survives sorting,
       filtering, paging and columns being hidden. */
    /* Column picker and per-column filter menus. The panel is fixed to the
       viewport and placed against its trigger, so no ancestor's overflow can
       cut it off; it flips above the trigger when there is no room below and
       is clamped inside the viewport on both axes. */
    window.crewGridPopover = function (align) {
        return {
            open: false,
            placed: false,
            q: '',

            toggle() {
                this.open = !this.open;
                if (!this.open) {
                    this.placed = false;

                    return;
                }
                this.$nextTick(() => this.place());
            },

            close() {
                this.open = false;
                this.placed = false;
            },

            reposition() {
                if (this.open) {
                    this.place();
                }
            },

            place() {
                var panel = this.$refs.panel;
                var trigger = this.$refs.trigger;
                if (!panel || !trigger) {
                    return;
                }
                var rect = trigger.getBoundingClientRect();
                var margin = 4;
                var edge = 8;
                var height = panel.offsetHeight;
                var width = panel.offsetWidth;

                var top = rect.bottom + margin;
                if (top + height > window.innerHeight - edge && rect.top - margin - height > edge) {
                    top = rect.top - margin - height;
                }
                top = Math.max(edge, Math.min(top, window.innerHeight - height - edge));

                var left = align === 'right' ? rect.right - width : rect.left - edge;
                left = Math.max(edge, Math.min(left, window.innerWidth - width - edge));

                panel.style.top = top + 'px';
                panel.style.left = left + 'px';
                this.placed = true;
            },
        };
    };

    window.crewGridResize = function (minWidth) {
        return {
            dragKey: null,
            startX: 0,
            startWidth: 0,

            table() {
                return this.$el.querySelector('table.crewgrid-table');
            },

            cols() {
                return Array.from(this.$el.querySelectorAll('col[data-crewgrid-col]'));
            },

            colFor(key) {
                return this.$el.querySelector('col[data-crewgrid-col="' + key + '"]');
            },

            /* Columns the author never sized are auto-laid-out. Freeze what
               the browser worked out before switching to a fixed layout, or
               they would all snap to equal widths the moment a drag starts. */
            freezeWidths() {
                var table = this.table();
                if (!table) {
                    return;
                }
                var headers = Array.from(table.querySelectorAll('thead th'));
                this.cols().forEach(function (col, index) {
                    if (!parseInt(col.style.width, 10) && headers[index]) {
                        col.style.width = headers[index].offsetWidth + 'px';
                    }
                });
                table.classList.add('crewgrid-fixed');
            },

            start(event) {
                var handle = event.target.closest('.crewgrid-resizer');
                if (!handle) {
                    return;
                }
                var header = handle.closest('th');
                if (!header || !this.colFor(handle.dataset.crewgridCol)) {
                    return;
                }
                event.preventDefault();
                this.freezeWidths();
                this.dragKey = handle.dataset.crewgridCol;
                this.startX = event.clientX;
                this.startWidth = header.offsetWidth;
                document.body.classList.add('crewgrid-resizing');
            },

            move(event) {
                if (this.dragKey === null) {
                    return;
                }
                var col = this.colFor(this.dragKey);
                if (col) {
                    col.style.width = Math.max(minWidth, this.startWidth + (event.clientX - this.startX)) + 'px';
                }
            },

            stop() {
                if (this.dragKey === null) {
                    return;
                }
                this.dragKey = null;
                document.body.classList.remove('crewgrid-resizing');

                var widths = {};
                this.cols().forEach(function (col) {
                    var width = parseInt(col.style.width, 10);
                    if (width > 0) {
                        widths[col.dataset.crewgridCol] = width;
                    }
                });
                this.$wire.call('setColumnWidths', widths);
            },
        };
    };
</script>
@endonce
