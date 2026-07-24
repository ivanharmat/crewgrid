<?php

namespace CrewGrid;

use Illuminate\Support\ServiceProvider;

class CrewGridServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/crewgrid.php', 'crewgrid');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'crewgrid');

        $this->publishes([
            __DIR__.'/../config/crewgrid.php' => config_path('crewgrid.php'),
        ], 'crewgrid-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/crewgrid'),
        ], 'crewgrid-views');
    }
}
