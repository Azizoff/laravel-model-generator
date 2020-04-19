<?php

namespace Azizoff\ModelGenerator;

use Azizoff\ModelGenerator\commands\ModelGenerateCommand;
use Illuminate\Support\ServiceProvider;

class ModelGeneratorProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $configPath = __DIR__ . '/../config/model-generator.php';
        $this->mergeConfigFrom($configPath, 'model-generator');

        $this->app->singleton(
            'command.azizoff.model.generate',
            function ($app) {
                return new ModelGenerateCommand(
                    $app['files'],
                    $app['config']
                );
            }
        );

        $this->commands(
            [
                'command.azizoff.model.generate',
            ]
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/model-generator.php';
        if (function_exists('config_path')) {
            $publishPath = config_path('model-generator.php');
        } else {
            $publishPath = base_path('config/model-generator.php');
        }
        $this->publishes([$configPath => $publishPath], 'config');
    }
}
