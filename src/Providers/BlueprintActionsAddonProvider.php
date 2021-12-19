<?php

namespace Markkimsal\BlueprintActionsAddon\Providers;

use Blueprint\Blueprint;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Markkimsal\BlueprintActionsAddon\LaravelActionsGenerator;

class BlueprintActionsAddonProvider
extends ServiceProvider
implements DeferrableProvider
{
    public function boot()
    {
        if (!defined('CUSTOM_STUBS_PATH')) {
            define('CUSTOM_STUBS_PATH', './stubs/blueprint');
        }
    }

    public function register()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }
        //$this->app->singleton()
        $this->app->extend(Blueprint::class, function (Blueprint $blueprint, $app) {

            $blueprint->swapGenerator('Blueprint\Generators\ControllerGenerator', $app->make(LaravelActionsGenerator::class));
            // $blueprint->registerGenerator($app[LaravelActionsGenerator::class]);

            return $blueprint;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() {
        return [LaravelActionsGenerator::class];
    }
}
