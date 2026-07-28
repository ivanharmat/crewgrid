<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | The markup theme grids render with: "bootstrap3", "bootstrap5" or
    | "tailwind". Any grid can override this via its $theme property.
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

];
