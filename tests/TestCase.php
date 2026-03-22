<?php

namespace EmilioLodigiani\LaravelImages\Tests;

use EmilioLodigiani\LaravelImages\ImageServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ImageServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('images.sources.default', [
            'originals' => __DIR__.'/fixtures/originals',
            'sizes' => __DIR__.'/fixtures/sizes',
            'url' => '/images/sizes/{width}',
        ]);
    }
}
