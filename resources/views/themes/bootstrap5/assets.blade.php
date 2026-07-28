@include('crewgrid::assets')

@once
<style>
    /* Bootstrap 5's .form-check already reserves room for the input, so the
       popover only needs the rows tightened up and kept on one line. */
    .crewgrid-popover .form-check { margin-bottom: 0; padding-top: 1px; padding-bottom: 1px; }
    .crewgrid-popover .form-check-label { white-space: nowrap; }

    /* Bootstrap 5 paints striping and hover with a box-shadow overlay on the
       cell, which sits above the column borders and would erase them. */
    .crewgrid-bordered > tbody > tr > td { background-clip: padding-box; }
</style>
@endonce
