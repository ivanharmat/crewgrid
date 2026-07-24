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

];
