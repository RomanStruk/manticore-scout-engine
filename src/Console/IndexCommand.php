<?php

namespace RomanStruk\ManticoreScoutEngine\Console;

use Exception;
use Illuminate\Console\Command;
use Laravel\Scout\EngineManager;

class IndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manticore:index
            {model : Class name of model to bulk create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an index';

    /**
     * Execute the console command.
     *
     * @param EngineManager $manager
     * @return void
     */
    public function handle(EngineManager $manager)
    {
        $engine = $manager->engine();

        try {
            $class = $this->argument('model');

            $model = new $class;

            $options = $model->scoutIndexMigration();
            $name = $model->searchableAs();

            $engine->createIndex($name, $options);

            $this->info('Index ["'.$name.'"] created successfully.');
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
    }
}
