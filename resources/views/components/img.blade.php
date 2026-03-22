<img
    src="{{ $fallback }}"
    @if($srcset) srcset="{{ $srcset }}" @endif
    @if($srcset) sizes="{{ $sizes }}" @endif
    alt="{{ $alt }}"
    @if($width) width="{{ $width }}" @endif
    @if($height) height="{{ $height }}" @endif
    {{ $attributes }}
>
