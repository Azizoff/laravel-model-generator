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
        $this->app->singleton(
            'command.azizoff.model.generate',
            function ($app) {
                return new ModelGenerateCommand($app['files']);
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
        //
    }
}
