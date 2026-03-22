<?php

namespace EmilioLodigiani\LaravelImages;

use EmilioLodigiani\LaravelImages\Commands\ResizeImagesCommand;
use EmilioLodigiani\LaravelImages\View\Components\Img;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class ImageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/images.php', 'images');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-images');

        Blade::component('img', Img::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ResizeImagesCommand::class]);

            $this->publishes([
                __DIR__.'/../config/images.php' => config_path('images.php'),
            ], 'images-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-images'),
            ], 'images-views');
        }
    }
}
