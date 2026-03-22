<?php

namespace EmilioLodigiani\LaravelImages\Commands;

use EmilioLodigiani\LaravelImages\ImageResizer;
use Illuminate\Console\Command;

class ResizeImagesCommand extends Command
{
    protected $signature = 'images:resize';

    protected $description = 'Generate missing resized versions of images for srcset';

    public function handle(): void
    {
        $count = ImageResizer::resizeAll();
        $this->info("Generated $count resized images.");
    }
}
