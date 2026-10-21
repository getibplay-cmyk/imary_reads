<?php

// Re-encode the original generated artwork for the web without changing its composition.
foreach (glob(__DIR__.'/../resources/artwork/*.png') as $source) {
    $image = imagecreatefrompng($source);
    $target = __DIR__.'/../public/images/'.pathinfo($source, PATHINFO_FILENAME).'.webp';
    if (! $image || ! imagewebp($image, $target, 84)) {
        throw new RuntimeException('Artwork encoding failed: '.basename($source));
    }
    imagedestroy($image);
}
