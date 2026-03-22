<?php

use EmilioLodigiani\LaravelImages\ImageResizer;

it('transforms img tags to responsive images', function () {
    $original = __DIR__.'/fixtures/originals/test-photo.jpg';
    ImageResizer::resize($original);

    $html = '<img src="/images/test-photo.jpg" alt="Test photo">';
    $result = responsive_images($html);

    expect($result)->toContain('srcset=');
    expect($result)->toContain('sizes=');
    expect($result)->toContain('loading="lazy"');
    expect($result)->toContain('alt="Test photo"');
    expect($result)->toContain('test-photo.webp');
});

it('leaves img tags without matching sizes unchanged', function () {
    $html = '<img src="/images/unknown-image.jpg" alt="Unknown">';
    $result = responsive_images($html);

    expect($result)->toBe($html);
});

it('handles html without img tags', function () {
    $html = '<p>No images here</p>';
    $result = responsive_images($html);

    expect($result)->toBe($html);
});
