<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    return;
}

if (function_exists('verify_csrf') && !verify_csrf()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid. Refresh halaman lalu coba upload ulang.']);
    return;
}

if (!function_exists('image_upload_to_webp')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Image engine belum aktif.']);
    return;
}

if (!function_exists('admin_jodit_upload_files')) {
    function admin_jodit_upload_files(array $files): array
    {
        $normalized = [];

        foreach ($files as $field => $file) {
            if (!is_array($file)) {
                continue;
            }

            if (isset($file['tmp_name']) && is_array($file['tmp_name'])) {
                foreach ($file['tmp_name'] as $index => $tmpName) {
                    if (is_array($tmpName)) {
                        continue;
                    }
                    $normalized[] = [
                        'name' => (string)($file['name'][$index] ?? 'gambar-editor'),
                        'type' => (string)($file['type'][$index] ?? ''),
                        'tmp_name' => (string)$tmpName,
                        'error' => (int)($file['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                        'size' => (int)($file['size'][$index] ?? 0),
                    ];
                }
                continue;
            }

            if (isset($file['tmp_name'])) {
                $normalized[] = [
                    'name' => (string)($file['name'] ?? 'gambar-editor'),
                    'type' => (string)($file['type'] ?? ''),
                    'tmp_name' => (string)$file['tmp_name'],
                    'error' => (int)($file['error'] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int)($file['size'] ?? 0),
                ];
            }
        }

        return array_values(array_filter($normalized, static function (array $file): bool {
            return trim((string)($file['tmp_name'] ?? '')) !== '' && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        }));
    }
}

$context = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)($_POST['editor_context'] ?? 'editor')));
$context = trim((string)$context, '-');
if ($context === '') {
    $context = 'editor';
}

$folder = match ($context) {
    'article', 'artikel' => 'articles',
    'product', 'produk' => 'products',
    default => 'editor',
};

$uploads = admin_jodit_upload_files($_FILES);

if (!$uploads) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Tidak ada file gambar yang diterima.']);
    return;
}

$files = [];
$errors = [];

foreach ($uploads as $upload) {
    try {
        $baseName = pathinfo((string)($upload['name'] ?? ''), PATHINFO_FILENAME)
            ?: trim((string)($_POST['title'] ?? ''))
            ?: 'gambar-editor';

        $url = image_upload_to_webp($upload, $folder, $baseName, [
            'prefix' => 'editor',
            'max_size' => 10 * 1024 * 1024,
            'max_width' => 1800,
            'max_height' => 1800,
            'quality' => 80,
        ]);

        if ($url) {
            $files[] = [
                'url' => $url,
                'name' => basename((string)$url),
                'title' => pathinfo((string)($upload['name'] ?? ''), PATHINFO_FILENAME) ?: 'Gambar editor',
            ];
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

if (!$files) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $errors ? implode(' ', array_unique($errors)) : 'Upload gambar gagal.',
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

if (function_exists('activity_log_record')) {
    activity_log_record('upload', 'media', null, 'Upload gambar dari editor Jodit.', [
        'context' => $context,
        'folder' => $folder,
        'count' => count($files),
    ]);
}

echo json_encode([
    'success' => true,
    'message' => 'Upload gambar berhasil.',
    'files' => $files,
    // Jodit-compatible aliases for older/default handlers.
    'data' => [
        'files' => array_map(static fn(array $file): string => (string)$file['url'], $files),
        'baseurl' => '',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
