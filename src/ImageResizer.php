<?php

namespace EmilioLodigiani\LaravelImages;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageResizer
{
    private static ?array $manifest = null;

    /**
     * Return the list of available widths for a given image basename and source.
     *
     * @return array<int>
     */
    public static function availableWidths(string $basename, string $source = 'default'): array
    {
        if (self::$manifest === null) {
            $path = storage_path('framework/image-sizes.php');
            self::$manifest = is_file($path) ? require $path : [];
        }

        return self::$manifest[$source][$basename] ?? [];
    }

    /**
     * Return the public URL for a resized image.
     */
    public static function url(string $basename, int $width, string $source = 'default'): string
    {
        $urlPattern = config("images.sources.{$source}.url", '/images/sizes/{width}');

        return str_replace('{width}', (string) $width, $urlPattern).'/'.$basename.'.webp';
    }

    /**
     * Write a manifest mapping each image basename to its available widths.
     */
    public static function writeManifest(): void
    {
        $manifest = [];
        $widths = config('images.widths', [400, 800, 1200, 1920, 2500]);
        $sources = config('images.sources', []);

        foreach ($sources as $sourceName => $sourceConfig) {
            $manifest[$sourceName] = [];
            $sizesDir = $sourceConfig['sizes'];

            foreach ($widths as $w) {
                $dir = "$sizesDir/$w/";
                if (! is_dir($dir)) {
                    continue;
                }

                foreach (glob("{$dir}*.webp") as $file) {
                    $manifest[$sourceName][pathinfo($file, PATHINFO_FILENAME)][] = $w;
                }
            }
        }

        $path = storage_path('framework/image-sizes.php');

        file_put_contents($path, '<?php return '.var_export($manifest, true).';');

        // Invalidate OPcache for both the symlinked and real paths
        // (zero-downtime deploys use symlinks, OPcache may cache either path)
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
            $realPath = realpath($path);
            if ($realPath && $realPath !== $path) {
                opcache_invalidate($realPath, true);
            }
        }

        self::$manifest = null;
    }

    /**
     * Resize a single image to all configured widths.
     */
    public static function resize(string $absolutePath): void
    {
        [$originalWidth, $originalHeight] = @getimagesize($absolutePath) ?: [0, 0];
        if ($originalWidth === 0) {
            Log::warning('ImageResizer: unable to read image dimensions', ['path' => $absolutePath]);

            return;
        }

        $source = self::detectSource($absolutePath);
        if ($source === null) {
            Log::warning('ImageResizer: no matching source for path', ['path' => $absolutePath]);

            return;
        }

        $manager = new ImageManager(new Driver);
        $basename = pathinfo($absolutePath, PATHINFO_FILENAME);
        $longerSide = max($originalWidth, $originalHeight);
        $widths = config('images.widths', [400, 800, 1200, 1920, 2500]);
        $quality = config('images.quality', 80);

        foreach ($widths as $w) {
            if ($w >= $longerSide) {
                continue;
            }

            $dir = $source['sizes']."/$w";

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $image = $manager->read($absolutePath);

            if ($originalWidth >= $originalHeight) {
                $image->scaleDown(width: $w);
            } else {
                $image->scaleDown(height: $w);
            }

            $image->toWebp($quality)->save("$dir/$basename.webp");
        }

        self::writeManifest();
    }

    /**
     * Resize all images from all configured sources. Returns the number of new files generated.
     */
    public static function resizeAll(): int
    {
        $count = 0;
        $sources = config('images.sources', []);
        $widths = config('images.widths', [400, 800, 1200, 1920, 2500]);
        $quality = config('images.quality', 80);

        $allOriginals = [];

        foreach ($sources as $sourceName => $sourceConfig) {
            $originalsDir = $sourceConfig['originals'];
            $sizesDir = $sourceConfig['sizes'];

            if (! is_dir($originalsDir)) {
                continue;
            }

            foreach (glob("$originalsDir/*.{jpg,jpeg,png,webp}", GLOB_BRACE) as $file) {
                $basename = pathinfo($file, PATHINFO_FILENAME);
                $allOriginals[$sourceName][$basename] = true;

                [$originalWidth, $originalHeight] = @getimagesize($file) ?: [0, 0];
                if ($originalWidth === 0) {
                    Log::warning('ImageResizer: unable to read image dimensions', ['path' => $file]);

                    continue;
                }
                $longerSide = max($originalWidth, $originalHeight);

                foreach ($widths as $w) {
                    if ($w >= $longerSide) {
                        continue;
                    }

                    $outDir = "$sizesDir/$w";

                    if (! is_dir($outDir)) {
                        mkdir($outDir, 0755, true);
                    }

                    $outPath = "$outDir/$basename.webp";
                    if (file_exists($outPath)) {
                        continue;
                    }

                    $image = (new ImageManager(new Driver))->read($file);

                    if ($originalWidth >= $originalHeight) {
                        $image->scaleDown(width: $w);
                    } else {
                        $image->scaleDown(height: $w);
                    }

                    $image->toWebp($quality)->save($outPath);
                    $count++;
                }
            }

            // Remove orphaned resized files whose originals no longer exist
            foreach ($widths as $w) {
                $sizeDir = "$sizesDir/$w/";
                if (! is_dir($sizeDir)) {
                    continue;
                }
                foreach (glob("{$sizeDir}*.webp") as $resized) {
                    $name = pathinfo($resized, PATHINFO_FILENAME);
                    if (! isset($allOriginals[$sourceName][$name])) {
                        unlink($resized);
                    }
                }
            }
        }

        self::writeManifest();

        return $count;
    }

    /**
     * Delete an image and all its resized versions from a source.
     */
    public static function delete(string $filename, string $source = 'default'): bool
    {
        $sourceConfig = config("images.sources.{$source}");
        if (! $sourceConfig) {
            return false;
        }

        $originalPath = $sourceConfig['originals'].'/'.$filename;

        if (! file_exists($originalPath)) {
            return false;
        }

        unlink($originalPath);

        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $widths = config('images.widths', [400, 800, 1200, 1920, 2500]);

        foreach ($widths as $w) {
            $resized = $sourceConfig['sizes']."/$w/$basename.webp";
            if (file_exists($resized)) {
                unlink($resized);
            }
        }

        self::writeManifest();

        return true;
    }

    /**
     * Detect which configured source an absolute path belongs to.
     *
     * @return array{originals: string, sizes: string, url: string}|null
     */
    private static function detectSource(string $absolutePath): ?array
    {
        $sources = config('images.sources', []);

        foreach ($sources as $sourceConfig) {
            if (str_starts_with($absolutePath, $sourceConfig['originals'])) {
                return $sourceConfig;
            }
        }

        return null;
    }
}
