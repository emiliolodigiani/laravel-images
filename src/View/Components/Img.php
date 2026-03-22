<?php

namespace EmilioLodigiani\LaravelImages\View\Components;

use EmilioLodigiani\LaravelImages\ImageResizer;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Img extends Component
{
    public string $fallback;

    public string $srcset;

    public function __construct(
        public string $src,
        public string $alt = '',
        public string $sizes = '100vw',
        public string $source = 'default',
        public ?int $width = null,
        public ?int $height = null,
    ) {
        $basename = pathinfo($this->src, PATHINFO_FILENAME);

        $availableWidths = ImageResizer::availableWidths($basename, $this->source);

        $srcsetParts = [];
        $largestSized = null;
        foreach ($availableWidths as $w) {
            $url = ImageResizer::url($basename, $w, $this->source);
            $srcsetParts[] = asset($url)." {$w}w";
            $largestSized = asset($url);
        }

        $this->srcset = implode(', ', $srcsetParts);

        $originalsUrl = config("images.sources.{$this->source}.originals_url", '');
        $this->fallback = $largestSized ?? asset(ltrim("$originalsUrl/{$this->src}", '/'));
    }

    public function render(): View
    {
        return view('laravel-images::components.img');
    }
}
