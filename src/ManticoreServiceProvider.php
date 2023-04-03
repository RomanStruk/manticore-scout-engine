<?php

namespace RomanStruk\ManticoreScoutEngine;

use Illuminate\Database\Eloquent\Collection;
use RomanStruk\ManticoreScoutEngine\Console\IndexCommand;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Mysql\ManticoreMysqlEngine;
use RomanStruk\ManticoreScoutEngine\Mysql\ManticoreGrammar;
use RomanStruk\ManticoreScoutEngine\Mysql\ManticoreConnection;
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
            if (config('manticore.engine') === 'http-client'){
                return new ManticoreEngine(config('manticore'));
            }

            return app(ManticoreMysqlEngine::class);
        });

        $this->configureMysqlEngine();

        $this->configureCommands();
    }

    protected function configureMysqlEngine()
    {
        $this->app->bind(Builder::class, function ($app) {
            return new Builder(config('manticore'));
        });

        $this->app->bind(ManticoreMysqlEngine::class, function ($app) {
            return new ManticoreMysqlEngine(config('manticore'));
        });

        $this->app->bind(ManticoreGrammar::class, ManticoreGrammar::class);

        $this->app->singleton(ManticoreConnection::class, function ($app) {
            return new ManticoreConnection(
                $app->make(ManticoreGrammar::class),
                config('manticore.mysql-connection')
            );
        });
        Collection::macro('getFacet', function ($group) {
            return app(EngineManager::class)->driver('manticore')->getFacet($group);
        });
        Collection::macro('getFacets', function () {
            return app(EngineManager::class)->driver('manticore')->getFacets();
        });
    }

    protected function configureCommands()
    {
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
