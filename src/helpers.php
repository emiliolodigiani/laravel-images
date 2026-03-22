<?php

use EmilioLodigiani\LaravelImages\ImageResizer;

if (! function_exists('responsive_images')) {
    /**
     * Transform <img> tags in HTML body content to responsive images with srcset.
     * Detects the source from the image path using configured sources.
     */
    function responsive_images(string $html): string
    {
        return preg_replace_callback('/<img\s+([^>]*?)>/i', function (array $match) {
            $attrs = $match[1];

            if (! preg_match('/src=["\']([^"\']+)["\']/', $attrs, $srcMatch)) {
                return $match[0];
            }

            $src = $srcMatch[1];
            $alt = '';
            if (preg_match('/alt=["\']([^"\']*)["\']/', $attrs, $altMatch)) {
                $alt = $altMatch[1];
            }

            $path = parse_url($src, PHP_URL_PATH) ?? $src;
            $basename = pathinfo($path, PATHINFO_FILENAME);

            // Try to detect source from the URL path
            $source = responsive_images_detect_source($path);

            $widths = ImageResizer::availableWidths($basename, $source);

            if (empty($widths)) {
                return $match[0];
            }

            $srcsetParts = [];
            $largestSized = null;
            foreach ($widths as $w) {
                $url = ImageResizer::url($basename, $w, $source);
                $srcsetParts[] = asset($url)." {$w}w";
                $largestSized = asset($url);
            }

            $srcset = implode(', ', $srcsetParts);

            return '<img src="'.e($largestSized).'" srcset="'.$srcset.'" sizes="(min-width: 768px) 768px, 100vw" alt="'.e($alt).'" loading="lazy">';
        }, $html);
    }
}

if (! function_exists('responsive_images_detect_source')) {
    /**
     * Detect which configured source a URL path belongs to, based on the originals directory.
     */
    function responsive_images_detect_source(string $urlPath): string
    {
        $sources = config('images.sources', []);

        foreach ($sources as $name => $sourceConfig) {
            // Check if the URL path contains a segment that matches this source
            $originalsDir = $sourceConfig['originals'];
            $relativePath = str_replace(public_path(), '', $originalsDir);

            if (str_contains($urlPath, $relativePath)) {
                return $name;
            }
        }

        return 'default';
    }
}
