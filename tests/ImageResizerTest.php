<?php

use EmilioLodigiani\LaravelImages\ImageResizer;

beforeEach(function () {
    // Ensure the fixture image exists (upload tests may have moved it)
    $fixture = __DIR__.'/fixtures/originals/test-photo.jpg';
    $master = __DIR__.'/fixtures/test-photo-master.jpg';
    if (! file_exists($fixture) && file_exists($master)) {
        copy($master, $fixture);
    }

    // Clean up any previously generated sizes
    $sizesDir = __DIR__.'/fixtures/sizes';
    if (is_dir($sizesDir)) {
        foreach (glob("$sizesDir/*/*.webp") as $file) {
            unlink($file);
        }
    }
});

afterAll(function () {
    $sizesDir = __DIR__.'/fixtures/sizes';
    if (is_dir($sizesDir)) {
        foreach (glob("$sizesDir/*/*.webp") as $file) {
            unlink($file);
        }
        foreach (glob("$sizesDir/*", GLOB_ONLYDIR) as $dir) {
            @rmdir($dir);
        }
    }

    $manifest = storage_path('framework/image-sizes.php');
    if (file_exists($manifest)) {
        unlink($manifest);
    }
});

it('resizes a single image to configured widths', function () {
    $original = __DIR__.'/fixtures/originals/test-photo.jpg';
    $sizesDir = __DIR__.'/fixtures/sizes';

    ImageResizer::resize($original);

    // Original is 2000x1500, so widths 400, 800, 1200, 1920 should be generated (2500 skipped)
    expect(file_exists("$sizesDir/400/test-photo.webp"))->toBeTrue();
    expect(file_exists("$sizesDir/800/test-photo.webp"))->toBeTrue();
    expect(file_exists("$sizesDir/1200/test-photo.webp"))->toBeTrue();
    expect(file_exists("$sizesDir/1920/test-photo.webp"))->toBeTrue();
    expect(file_exists("$sizesDir/2500/test-photo.webp"))->toBeFalse();
});

it('skips sizes larger than the original', function () {
    $original = __DIR__.'/fixtures/originals/test-photo.jpg';
    $sizesDir = __DIR__.'/fixtures/sizes';

    ImageResizer::resize($original);

    // 2500 >= 2000 (longer side), so it should be skipped
    expect(file_exists("$sizesDir/2500/test-photo.webp"))->toBeFalse();
});

it('writes a manifest with available widths', function () {
    $original = __DIR__.'/fixtures/originals/test-photo.jpg';

    ImageResizer::resize($original);

    $manifest = storage_path('framework/image-sizes.php');
    expect(file_exists($manifest))->toBeTrue();

    $data = require $manifest;
    expect($data)->toHaveKey('default');
    expect($data['default'])->toHaveKey('test-photo');
    expect($data['default']['test-photo'])->toContain(400, 800, 1200, 1920);
    expect($data['default']['test-photo'])->not->toContain(2500);
});

it('returns available widths from manifest', function () {
    $original = __DIR__.'/fixtures/originals/test-photo.jpg';

    ImageResizer::resize($original);

    $widths = ImageResizer::availableWidths('test-photo', 'default');
    expect($widths)->toContain(400, 800, 1200, 1920);
});

it('generates correct URL for a resized image', function () {
    $url = ImageResizer::url('test-photo', 800, 'default');
    expect($url)->toBe('/images/sizes/800/test-photo.webp');
});

it('resizes all images and returns count', function () {
    $count = ImageResizer::resizeAll();
    // 4 sizes for 1 image (400, 800, 1200, 1920)
    expect($count)->toBe(4);
});

it('skips already existing resized files on resizeAll', function () {
    ImageResizer::resizeAll();

    // Second run should generate 0 new files
    $count = ImageResizer::resizeAll();
    expect($count)->toBe(0);
});

it('handles non-existent image gracefully', function () {
    ImageResizer::resize('/nonexistent/image.jpg');

    // Should not throw, just log a warning
    expect(true)->toBeTrue();
});

it('returns empty widths for unknown basename', function () {
    $widths = ImageResizer::availableWidths('nonexistent-image', 'default');
    expect($widths)->toBe([]);
});

it('uploads a file and generates resized versions', function () {
    // Copy fixture so move() doesn't destroy the original
    $tmpPath = __DIR__.'/fixtures/originals/upload-test.jpg';
    copy(__DIR__.'/fixtures/originals/test-photo.jpg', $tmpPath);

    $file = new \Illuminate\Http\UploadedFile($tmpPath, 'uploaded.jpg', 'image/jpeg', null, true);

    $path = ImageResizer::upload($file, 'default', 'uploaded.jpg');

    expect(file_exists($path))->toBeTrue();
    expect(file_exists(__DIR__.'/fixtures/sizes/400/uploaded.webp'))->toBeTrue();
    expect(file_exists(__DIR__.'/fixtures/sizes/800/uploaded.webp'))->toBeTrue();

    // Clean up
    @unlink(__DIR__.'/fixtures/originals/uploaded.jpg');
    foreach (config('images.widths') as $w) {
        @unlink(__DIR__."/fixtures/sizes/$w/uploaded.webp");
    }
    ImageResizer::writeManifest();
});

it('stores raw content and generates resized versions', function () {
    $content = file_get_contents(__DIR__.'/fixtures/originals/test-photo.jpg');

    $path = ImageResizer::store($content, 'default', 'stored.jpg');

    expect(file_exists($path))->toBeTrue();
    expect(file_exists(__DIR__.'/fixtures/sizes/400/stored.webp'))->toBeTrue();

    // Clean up
    @unlink(__DIR__.'/fixtures/originals/stored.jpg');
    foreach (config('images.widths') as $w) {
        @unlink(__DIR__."/fixtures/sizes/$w/stored.webp");
    }
    ImageResizer::writeManifest();
});

it('deletes an image and all its resized versions', function () {
    // First create a file to delete
    $content = file_get_contents(__DIR__.'/fixtures/originals/test-photo.jpg');
    ImageResizer::store($content, 'default', 'to-delete.jpg');

    expect(file_exists(__DIR__.'/fixtures/originals/to-delete.jpg'))->toBeTrue();
    expect(file_exists(__DIR__.'/fixtures/sizes/400/to-delete.webp'))->toBeTrue();

    $result = ImageResizer::delete('to-delete.jpg', 'default');

    expect($result)->toBeTrue();
    expect(file_exists(__DIR__.'/fixtures/originals/to-delete.jpg'))->toBeFalse();
    expect(file_exists(__DIR__.'/fixtures/sizes/400/to-delete.webp'))->toBeFalse();
});

it('returns false when deleting non-existent image', function () {
    $result = ImageResizer::delete('nonexistent.jpg', 'default');
    expect($result)->toBeFalse();
});

it('throws on upload to invalid source', function () {
    $tmpPath = __DIR__.'/fixtures/originals/invalid-source-test.jpg';
    copy(__DIR__.'/fixtures/originals/test-photo.jpg', $tmpPath);

    $file = new \Illuminate\Http\UploadedFile($tmpPath, 'test.jpg', 'image/jpeg', null, true);

    try {
        ImageResizer::upload($file, 'nonexistent');
    } finally {
        @unlink($tmpPath);
    }
})->throws(\InvalidArgumentException::class);
