<?php

namespace RomanStruk\ManticoreScoutEngine;

use RomanStruk\ManticoreScoutEngine\Console\IndexCommand;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class ManticoreServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/manticore.php', 'manticore');
    }

    public function boot()
    {
        resolve(EngineManager::class)->extend('manticore', function () {
            return new ManticoreEngine(config('manticore'));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                IndexCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/manticore.php' => $this->app['path.config'].DIRECTORY_SEPARATOR.'manticore.php',
            ]);
        }
    }
}
