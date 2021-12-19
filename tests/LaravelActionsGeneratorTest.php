<?php

namespace Tests;

class LaravelActionsGeneratorTest extends TestCase {

    /**
     * @test
     * @return void 
     */
    public function generatorGetsFiles()
    {
        $this->artisan('blueprint:build', []);
        // $this->app->make('artisan')->call('blueprint:build');
    }
}
