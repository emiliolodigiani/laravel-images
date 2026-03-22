<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image Quality
    |--------------------------------------------------------------------------
    |
    | WebP output quality (0-100).
    |
    */

    'quality' => 80,

    /*
    |--------------------------------------------------------------------------
    | Resize Widths
    |--------------------------------------------------------------------------
    |
    | The widths (in pixels) to generate for each image. Images are scaled
    | by their longer side, so portrait images scale by height.
    | Sizes larger than the original are skipped.
    |
    */

    'widths' => [400, 800, 1200, 1920, 2500],

    /*
    |--------------------------------------------------------------------------
    | Image Sources
    |--------------------------------------------------------------------------
    |
    | Each source defines where originals are stored, where resized versions
    | go, and the public URL prefix for srcset generation.
    |
    | 'originals' - Absolute path to the directory with original images.
    | 'sizes'     - Absolute path to the directory for resized images.
    |               A subdirectory for each width will be created (e.g. /400/, /800/).
    | 'url'       - Public URL prefix for resized images. {width} is replaced
    |               with the actual width value.
    |
    */

    'sources' => [
        'default' => [
            'originals' => public_path('images'),
            'sizes' => public_path('images/sizes'),
            'url' => '/images/sizes/{width}',
        ],
    ],

];
