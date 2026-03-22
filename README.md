# Laravel Images

Responsive image resizing with srcset generation for Laravel.

Automatically resizes images to multiple WebP variants and generates `<img>` tags with `srcset` for optimal loading. Configurable image sources allow each project to define its own directories and URL structure.

## Installation

```bash
composer require emiliolodigiani/laravel-images
```

Publish the config file:

```bash
php artisan vendor:publish --tag=images-config
```

## Configuration

Define your image sources in `config/images.php`:

```php
return [
    'quality' => 80,
    'widths' => [400, 800, 1200, 1920, 2500],
    'sources' => [
        'default' => [
            'originals'     => public_path('images'),
            'originals_url' => '/images',
            'sizes'         => public_path('images/sizes'),
            'url'           => '/images/sizes/{width}',
        ],
        // Add more sources as needed:
        'blog' => [
            'originals'     => storage_path('app/public/blog'),
            'originals_url' => '/storage/blog',
            'sizes'         => storage_path('app/public/blog-sizes'),
            'url'           => '/storage/blog-sizes/{width}',
        ],
    ],
];
```

Each source defines:
- **originals** — absolute path to the directory with original images
- **originals_url** — public URL prefix for originals (used as fallback when no resized versions exist)
- **sizes** — absolute path where resized versions are stored (subdirectories per width)
- **url** — public URL pattern for srcset (`{width}` is replaced with the actual value)

## Usage

### Blade component

```blade
<x-img src="photo.webp" alt="A photo" />
<x-img src="photo.webp" alt="A photo" sizes="(min-width: 768px) 50vw, 100vw" />
<x-img src="photo.webp" alt="A photo" source="guides" />
```

The component outputs an `<img>` tag with `srcset` and `sizes` attributes. If no resized versions exist, it falls back to the original.

### HTML body helper

For rich text content with embedded `<img>` tags:

```php
{!! responsive_images($model->body) !!}
```

This transforms all `<img>` tags in the HTML to responsive versions with `srcset`, `sizes`, and `loading="lazy"`.

### Resize on upload

Call `ImageResizer::resize()` after saving an uploaded file:

```php
use EmilioLodigiani\LaravelImages\ImageResizer;

$path = $request->file('image')->store('images', 'public');
ImageResizer::resize(storage_path("app/public/$path"));
```

### Batch resize

Generate missing resized versions for all configured sources:

```bash
php artisan images:resize
```

### Programmatic access

```php
use EmilioLodigiani\LaravelImages\ImageResizer;

// Get available widths for an image
$widths = ImageResizer::availableWidths('photo', 'default'); // [400, 800, 1200, 1920]

// Get the public URL for a specific size
$url = ImageResizer::url('photo', 800, 'default'); // /images/sizes/800/photo.webp
```

## How it works

1. When an image is resized, WebP versions are generated for each configured width that is smaller than the original
2. Images are scaled by their **longer side** (portrait images scale by height, not width)
3. A PHP manifest file at `storage/framework/image-sizes.php` tracks which sizes exist for fast lookup (no database queries)
4. The manifest is automatically updated after each resize operation and OPcache-invalidated for zero-downtime deploys

## Customizing the view

Publish the Blade view to customize the `<img>` output:

```bash
php artisan vendor:publish --tag=images-views
```

The view will be at `resources/views/vendor/laravel-images/components/img.blade.php`.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Intervention Image 3 (GD driver)

## License

MIT
