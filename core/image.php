<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| IMAGE ENGINE
|--------------------------------------------------------------------------
| SEO image optimization helpers
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| DEFAULT IMAGE
|--------------------------------------------------------------------------
*/

if (!defined('DEFAULT_IMAGE')) {

    define(
        'DEFAULT_IMAGE',
        asset('images/default.jpg')
    );
}

/*
|--------------------------------------------------------------------------
| IMAGE URL
|--------------------------------------------------------------------------
*/

function image_url(
    ?string $path = null
): string {

    if (
        empty($path)
    ) {

        return DEFAULT_IMAGE;
    }

    /*
    |--------------------------------------------------------------------------
    | EXTERNAL URL
    |--------------------------------------------------------------------------
    */

    if (
        filter_var(
            $path,
            FILTER_VALIDATE_URL
        )
    ) {

        return $path;
    }

    return asset(
        ltrim($path, '/')
    );
}

/*
|--------------------------------------------------------------------------
| IMAGE ALT GENERATOR
|--------------------------------------------------------------------------
*/

if (!function_exists('image_alt')) {

    function image_alt(
        string $title,
        ?string $context = null
    ): string {

        $title =
            trim(strip_tags($title));

        $context =
            trim((string)$context);

        if ($context !== '') {

            return
                $title .
                ' - ' .
                $context;
        }

        return $title;
    }
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE IMAGE
|--------------------------------------------------------------------------
*/

function responsive_image(
    string $src,
    string $alt,
    array $options = []
): string {

    $src =
        image_url($src);

    $width =
        (int)($options['width'] ?? 800);

    $height =
        (int)($options['height'] ?? 600);

    $loading =
        $options['loading'] ?? 'lazy';

    $decoding =
        $options['decoding'] ?? 'async';

    $class =
        esc(
            $options['class'] ?? ''
        );

    $sizes =
        esc(
            $options['sizes']
            ?? '(max-width: 768px) 100vw, 800px'
        );

    $fetchpriority =
        esc(
            $options['fetchpriority'] ?? ''
        );

    $srcset =
        build_srcset($src);

    return '

        <img
            src="' . esc($src) . '"
            srcset="' . esc($srcset) . '"
            sizes="' . $sizes . '"
            alt="' . esc($alt) . '"
            width="' . $width . '"
            height="' . $height . '"
            loading="' . esc($loading) . '"
            decoding="' . esc($decoding) . '"
            fetchpriority="' . $fetchpriority . '"
            class="' . $class . '">

    ';
}

/*
|--------------------------------------------------------------------------
| BUILD SRCSET
|--------------------------------------------------------------------------
|
| Future:
| - image resizing
| - CDN
| - thumb generator
|--------------------------------------------------------------------------
*/

function build_srcset(
    string $src
): string {

    return implode(

        ', ',

        [

            $src . ' 400w',
            $src . ' 800w',
            $src . ' 1200w',

        ]
    );
}

/*
|--------------------------------------------------------------------------
| WEBP IMAGE
|--------------------------------------------------------------------------
*/

function webp_image(
    string $path
): string {

    $path =
        preg_replace(

            '/\.(jpg|jpeg|png)$/i',

            '.webp',

            $path
        );

    return image_url(
        $path
    );
}

/*
|--------------------------------------------------------------------------
| PRELOAD IMAGE
|--------------------------------------------------------------------------
*/

function preload_image(
    string $src
): void {

    echo '

        <link
            rel="preload"
            as="image"
            href="' . esc(
                image_url($src)
            ) . '">

    ';
}

/*
|--------------------------------------------------------------------------
| IMAGE DIMENSIONS
|--------------------------------------------------------------------------
*/

function image_dimensions(
    string $path
): array {

    $fullPath =
        PUBLIC_PATH . '/' .
        ltrim($path, '/');

    if (
        !file_exists($fullPath)
    ) {

        return [

            'width' => 800,
            'height' => 600,

        ];
    }

    $size =
        @getimagesize($fullPath);

    if (!$size) {

        return [

            'width' => 800,
            'height' => 600,

        ];
    }

    return [

        'width' =>
            (int)$size[0],

        'height' =>
            (int)$size[1],

    ];
}

/*
|--------------------------------------------------------------------------
| IMAGE TAG
|--------------------------------------------------------------------------
*/

function image_tag(
    string $src,
    string $alt,
    array $options = []
): string {

    $dimensions =
        image_dimensions($src);

    $width =
        $options['width']
            ?? $dimensions['width'];

    $height =
        $options['height']
            ?? $dimensions['height'];

    $class =
        esc(
            $options['class'] ?? ''
        );

    $loading =
        esc(
            $options['loading']
            ?? 'lazy'
        );

    $fetchpriority =
        esc(
            $options['fetchpriority']
            ?? ''
        );

    return '

        <img
            src="' . esc(
                image_url($src)
            ) . '"
            alt="' . esc($alt) . '"
            width="' . (int)$width . '"
            height="' . (int)$height . '"
            loading="' . $loading . '"
            fetchpriority="' . $fetchpriority . '"
            class="' . $class . '">

    ';
}

/*
|--------------------------------------------------------------------------
| HERO IMAGE
|--------------------------------------------------------------------------
*/

function hero_image(
    string $src,
    string $alt
): string {

    return image_tag(

        $src,

        $alt,

        [

            'loading' =>
                'eager',

            'fetchpriority' =>
                'high',

            'class' =>
                'hero-main-image',

            'width' =>
                1200,

            'height' =>
                800,

        ]
    );
}

/*
|--------------------------------------------------------------------------
| THUMBNAIL IMAGE
|--------------------------------------------------------------------------
*/

function thumbnail_image(
    string $src,
    string $alt
): string {

    return image_tag(

        $src,

        $alt,

        [

            'loading' =>
                'lazy',

            'class' =>
                'thumbnail-image',

            'width' =>
                400,

            'height' =>
                300,

        ]
    );
}

/*
|--------------------------------------------------------------------------
| ARTICLE IMAGE
|--------------------------------------------------------------------------
*/

function article_image(
    string $src,
    string $alt
): string {

    return image_tag(

        $src,

        $alt,

        [

            'loading' =>
                'lazy',

            'class' =>
                'article-image',

            'width' =>
                1200,

            'height' =>
                700,

        ]
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCT IMAGE
|--------------------------------------------------------------------------
*/

function product_image(
    string $src,
    string $alt
): string {

    return image_tag(

        $src,

        $alt,

        [

            'loading' =>
                'lazy',

            'class' =>
                'product-image',

            'width' =>
                700,

            'height' =>
                600,

        ]
    );
}

/*
|--------------------------------------------------------------------------
| IMAGE CDN READY
|--------------------------------------------------------------------------
*/

function cdn_image(
    string $src
): string {

    /*
    |--------------------------------------------------------------------------
    | FUTURE CDN SUPPORT
    |--------------------------------------------------------------------------
    |
    | Cloudflare Images
    | Bunny CDN
    | ImageKit
    | Cloudinary
    |--------------------------------------------------------------------------
    */

    if (
        defined('CDN_URL') &&
        CDN_URL !== ''
    ) {

        return rtrim(
            CDN_URL,
            '/'
        ) . '/' .
        ltrim($src, '/');
    }

    return image_url($src);
}

/*
|--------------------------------------------------------------------------
| IMAGE STRUCTURED DATA
|--------------------------------------------------------------------------
*/

function image_schema(
    string $src
): array {

    return [

        '@type' =>
            'ImageObject',

        'url' =>
            image_url($src),
    ];
}

/*
|--------------------------------------------------------------------------
| UPLOAD IMAGE TO WEBP
|--------------------------------------------------------------------------
| Convert uploaded JPG/PNG/WebP into optimized WebP.
| Requires Imagick or GD with WebP support.
|--------------------------------------------------------------------------
*/

if (!function_exists('image_upload_to_webp')) {

    function image_upload_to_webp(
        array $file,
        string $folder,
        string $baseName = 'image',
        array $options = []
    ): ?string {

        if (
            empty($file['tmp_name']) ||
            !is_uploaded_file((string)$file['tmp_name'])
        ) {
            return null;
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload gambar gagal. Coba ulangi dengan file yang lebih kecil.');
        }

        $tmp = (string)$file['tmp_name'];
        $size = (int)($file['size'] ?? 0);
        $maxSize = (int)($options['max_size'] ?? (10 * 1024 * 1024));

        if ($size <= 0 || $size > $maxSize) {
            throw new RuntimeException('Ukuran gambar terlalu besar. Maksimal ' . round($maxSize / 1024 / 1024) . 'MB.');
        }

        $info = @getimagesize($tmp);
        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

        if (!$info || !in_array((int)$info[2], $allowedTypes, true)) {
            throw new RuntimeException('Format gambar harus JPG, JPEG, PNG, atau WebP.');
        }

        $folder = trim($folder, '/');
        $targetDir = ROOT_PATH . '/assets/uploads/' . $folder;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Folder upload tidak bisa dibuat.');
        }

        $safeBase = function_exists('slugify')
            ? slugify($baseName)
            : strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $baseName));

        $safeBase = trim((string)$safeBase, '-');

        if ($safeBase === '') {
            $safeBase = (string)($options['prefix'] ?? 'image');
        }

        $filename = $safeBase . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.webp';
        $target = $targetDir . '/' . $filename;
        $quality = max(40, min(95, (int)($options['quality'] ?? 78)));
        $maxWidth = max(320, (int)($options['max_width'] ?? 1600));
        $maxHeight = max(320, (int)($options['max_height'] ?? 1600));

        $converted = image_convert_upload_to_webp($tmp, (int)$info[2], $target, $quality, $maxWidth, $maxHeight);

        if (!$converted || !is_file($target) || filesize($target) <= 0) {
            @unlink($target);
            throw new RuntimeException('Gagal mengoptimasi gambar ke WebP. Pastikan extension PHP Imagick atau GD WebP aktif.');
        }

        @chmod($target, 0644);

        return asset('uploads/' . $folder . '/' . $filename);
    }
}

if (!function_exists('image_convert_upload_to_webp')) {

    function image_convert_upload_to_webp(
        string $source,
        int $imageType,
        string $target,
        int $quality,
        int $maxWidth,
        int $maxHeight
    ): bool {

        if (extension_loaded('imagick') && class_exists('Imagick')) {
            try {
                $image = new Imagick($source);

                if (method_exists($image, 'setIteratorIndex')) {
                    $image->setIteratorIndex(0);
                }

                if (method_exists($image, 'autoOrient')) {
                    $image->autoOrient();
                }

                $width = $image->getImageWidth();
                $height = $image->getImageHeight();

                if ($width > $maxWidth || $height > $maxHeight) {
                    $image->thumbnailImage($maxWidth, $maxHeight, true, true);
                }

                $image->stripImage();
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality($quality);
                $ok = $image->writeImage($target);
                $image->clear();
                $image->destroy();

                return (bool)$ok;
            } catch (Throwable $e) {
                error_log('[IMAGE_WEBP_IMAGICK] ' . $e->getMessage());
            }
        }

        if (!function_exists('imagewebp')) {
            return false;
        }

        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($source) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($source) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };

        if (!$sourceImage) {
            return false;
        }

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($sourceImage);
        }

        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $ratio = min($maxWidth / max(1, $width), $maxHeight / max(1, $height), 1);
        $newWidth = max(1, (int)round($width * $ratio));
        $newHeight = max(1, (int)round($height * $ratio));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        if (!$canvas) {
            imagedestroy($sourceImage);
            return false;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($canvas, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $ok = imagewebp($canvas, $target, $quality);

        imagedestroy($canvas);
        imagedestroy($sourceImage);

        return (bool)$ok;
    }
}
