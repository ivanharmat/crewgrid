@include('crewgrid::assets')

@once
<style>
    /* Tailwind ships no table component, and its preflight strips the browser
       defaults - so cell padding, striping and hover come from here rather
       than from utility classes. That also means the grid still looks like a
       grid if the host has not added this package to its Tailwind `content`
       paths (see the README); only the toolbar loses its styling. */
    .crewgrid-plain > thead > tr > th,
    .crewgrid-plain > tbody > tr > td { padding: 6px 10px; }
    .crewgrid-plain > tbody > tr:nth-of-type(odd) > td { background-color: #f9fafb; }
    .crewgrid-plain > tbody > tr:hover > td { background-color: #f3f4f6; }
    .crewgrid-plain > tbody > tr > td { border-top: 1px solid #f1f2f4; }
</style>
@endonce
