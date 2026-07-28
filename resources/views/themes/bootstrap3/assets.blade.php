@include('crewgrid::assets')

@once
<style>
    .crewgrid-popover .checkbox { margin: 3px 0; }
    /* Bootstrap 3 absolutely positions the box at margin-left:-20px and relies
       on the label's own left padding to make room for it - set the padding
       here rather than inline, or the box lands outside the popover. */
    .crewgrid-popover .checkbox label {
        display: block;
        padding: 1px 4px 1px 24px;
        font-weight: normal;
        white-space: nowrap;
    }
</style>
@endonce
