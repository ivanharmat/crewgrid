<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | The markup theme grids render with: "bootstrap3", "bootstrap5" or
    | "tailwind". Any grid can override this via its $theme property. The
    | tailwind theme needs this package added to your tailwind.config.js
    | content paths - see the README.
    |
    */

    'theme' => env('CREWGRID_THEME', 'bootstrap3'),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    'per_page' => 30,

    'per_page_options' => [15, 30, 50, 100],

    /*
    |--------------------------------------------------------------------------
    | Appearance
    |--------------------------------------------------------------------------
    |
    | "bordered" draws column borders and row lines; any grid can override it
    | via its $bordered property. "min_column_width" is the floor a column can
    | be dragged down to, in pixels.
    |
    */

    'bordered' => true,

    'min_column_width' => 60,

    /*
    |--------------------------------------------------------------------------
    | Control classes
    |--------------------------------------------------------------------------
    |
    | Restyle the grid's buttons, inputs, selects, links and badges without
    | publishing a view. Keys named here override the theme's defaults (see
    | CrewGrid\Grid::DEFAULT_CLASSES); anything left out keeps its default, so
    | there is no need to restate a whole theme. A single grid can override
    | further with a public array $classes.
    |
    |     'classes' => [
    |         'bootstrap5' => ['button' => 'btn btn-primary btn-sm'],
    |     ],
    |
    */

    'classes' => [],

    /*
    |--------------------------------------------------------------------------
    | Icons
    |--------------------------------------------------------------------------
    |
    | The grid ships Font Awesome 4 markup (that is what the themes were built
    | against). Override the keys you need for a different set - values are
    | markup, not class names, so inline SVG works too. Map a key to '' and
    | that spot renders nothing. Keys: filter, columns, clear, show_all,
    | resize, load_more, loading, sort, sort_asc, sort_desc - see
    | CrewGrid\Grid::DEFAULT_ICONS.
    |
    |     'icons' => [
    |         'filter' => '<i class="bi bi-funnel"></i>',
    |         'sort_asc' => '<svg class="h-3 w-3" ...>...</svg>',
    |     ],
    |
    */

    'icons' => [],

];
