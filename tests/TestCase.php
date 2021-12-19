<?php

namespace Tests;

use Blueprint\BlueprintServiceProvider;
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
}
