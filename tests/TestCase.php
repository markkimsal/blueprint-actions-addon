<?php

namespace Tests;

use Blueprint\BlueprintServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Container\BindingResolutionException;
use Markkimsal\BlueprintActionsAddon\Providers\BlueprintActionsAddonProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    //
    protected function getPackageProviders($app)
    {
        return [
            BlueprintActionsAddonProvider::class,
            BlueprintServiceProvider::class,
        ];
    }

    /**
     * Turn off default blueprint generators
     * @param Application $app
     * @return void
     * @throws BindingResolutionException
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('blueprint.generators', ['Markkimsal\BlueprintActionsAddon\LaravelActionsGenerator']);
    }
}
