<?php

namespace EmilioLodigiani\LaravelImages\Commands;

use EmilioLodigiani\LaravelImages\ImageResizer;
use Illuminate\Console\Command;

class ResizeImagesCommand extends Command
{
    protected $signature = 'images:resize {--force : Delete existing variants and regenerate all}';

    protected $description = 'Generate missing resized versions of images for srcset';

    public function handle(): void
    {
        if ($this->option('force')) {
            $deleted = ImageResizer::deleteAllVariants();
            $this->info("Deleted $deleted existing variants.");
        }

        $count = ImageResizer::resizeAll();
        $this->info("Generated $count resized images.");
    }
}
