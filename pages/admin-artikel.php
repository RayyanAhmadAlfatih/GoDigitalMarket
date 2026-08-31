<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$message = '';
$error = '';
$importLogs = [];
$action = (string)($_GET['action'] ?? 'list');
$editId = (int)($_GET['id'] ?? 0);

if ($action === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/artikel');
}

function admin_article_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_article_clean_content(string $content): string
{
    $content = trim($content);
    $content = preg_replace('#<\s*(script|iframe|object|embed|form|input|button|style|link|meta)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $content);
    $content = preg_replace('#on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', (string)$content);
    $content = preg_replace('#javascript\s*:#i', '', (string)$content);

    $content = strip_tags($content, '<p><br><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><s><del><strike><mark><sub><sup><ul><ol><li><a><blockquote><hr><img><figure><figcaption><table><thead><tbody><tfoot><tr><th><td><span><div><pre><code>');
    $content = preg_replace('#href\s*=\s*(["\'])\s*javascript:[^"\']*\1#i', 'href="#"', (string)$content);
    $content = preg_replace('#src\s*=\s*(["\'])\s*javascript:[^"\']*\1#i', 'src=""', (string)$content);
    $content = preg_replace('#<(a|img)([^>]*)>#i', function ($m) {
        $tag = strtolower($m[1]);
        $attrs = $m[2];
        $allowed = $tag === 'a' ? ['href','title','target','rel'] : ['src','alt','title','loading','width','height'];
        preg_match_all('/([a-zA-Z0-9_-]+)\s*=\s*(["\'])(.*?)\2/', $attrs, $matches, PREG_SET_ORDER);
        $safe = '';
        foreach ($matches as $attr) {
            $name = strtolower($attr[1]);
            $value = htmlspecialchars($attr[3], ENT_QUOTES, 'UTF-8');
            if (in_array($name, $allowed, true)) {
                if (($name === 'href' || $name === 'src') && preg_match('/^javascript:/i', $value)) {
                    continue;
                }
                $safe .= ' ' . $name . '="' . $value . '"';
            }
        }
        if ($tag === 'a' && strpos($safe, ' rel=') === false) {
            $safe .= ' rel="noopener"';
        }
        if ($tag === 'img' && strpos($safe, ' loading=') === false) {
            $safe .= ' loading="lazy"';
        }
        return '<' . $tag . $safe . '>';
    }, (string)$content);

    if ($content === strip_tags($content)) {
        $paragraphs = array_filter(array_map('trim', preg_split('/\R{2,}/', $content) ?: []));
        return implode('', array_map(static fn(string $p): string => '<p>' . esc($p) . '</p>', $paragraphs));
    }

    return $content;
}

function admin_upload_article_image(): ?string
{
    if (empty($_FILES['featured_image']['tmp_name']) || !is_uploaded_file((string)$_FILES['featured_image']['tmp_name'])) {
        return null;
    }

    $baseName = trim((string)($_POST['slug'] ?? ''))
        ?: trim((string)($_POST['title'] ?? ''))
        ?: pathinfo((string)($_FILES['featured_image']['name'] ?? 'artikel'), PATHINFO_FILENAME)
        ?: 'artikel';

    return image_upload_to_webp(
        $_FILES['featured_image'],
        'articles',
        $baseName,
        [
            'prefix' => 'artikel',
            'max_size' => 10 * 1024 * 1024,
            'max_width' => 1600,
            'max_height' => 1200,
            'quality' => 78,
        ]
    );
}

function admin_article_payload(): array
{
    $title = trim((string)($_POST['title'] ?? ''));
    $content = admin_article_clean_content((string)($_POST['content'] ?? ''));
    $image = trim((string)($_POST['image'] ?? ''));

    $uploaded = admin_upload_article_image();
    if ($uploaded) {
        $image = $uploaded;
    }

    $metaTitle = trim((string)($_POST['meta_title'] ?? ''));
    $metaDescription = trim((string)($_POST['meta_description'] ?? ''));
    $metaKeywords = trim((string)($_POST['meta_keywords'] ?? ''));
    $keywords = trim((string)($_POST['keywords'] ?? ''));

    return [
        'title' => $title,
        'slug' => trim((string)($_POST['slug'] ?? '')),
        'category' => trim((string)($_POST['category'] ?? 'Layanan')),
        'excerpt' => trim((string)($_POST['excerpt'] ?? $metaDescription)),
        'image' => $image ?: asset('images/placeholder-product.svg'),
        'image_alt' => trim((string)($_POST['image_alt'] ?? $title)),
        'image_title' => trim((string)($_POST['image_title'] ?? $title)),
        'author' => trim((string)($_POST['author'] ?? SITE_NAME)),
        'published_at' => trim((string)($_POST['published_at'] ?? date('Y-m-d H:i:s'))),
        'reading_time' => '',
        'featured' => isset($_POST['featured']),
        'keywords' => $keywords ?: $metaKeywords,
        'content' => $content,
        'meta_title' => $metaTitle ?: $title,
        'meta_description' => $metaDescription,
        'meta_keywords' => $metaKeywords ?: $keywords,
        'canonical_url' => trim((string)($_POST['canonical_url'] ?? '')),
        'og_title' => trim((string)($_POST['og_title'] ?? $metaTitle ?: $title)),
        'og_description' => trim((string)($_POST['og_description'] ?? $metaDescription)),
        'focus_keyword' => trim((string)($_POST['focus_keyword'] ?? '')),
        'robots' => trim((string)($_POST['robots'] ?? 'index, follow')),
        'breadcrumb_title' => trim((string)($_POST['breadcrumb_title'] ?? $title)),
        'schema_type' => trim((string)($_POST['schema_type'] ?? 'Article')),
        'faq_json' => trim((string)($_POST['faq_json'] ?? '')),
        'whatsapp_label' => trim((string)($_POST['whatsapp_label'] ?? 'Chat WhatsApp')),
        'whatsapp_phone' => preg_replace('/\D+/', '', (string)($_POST['whatsapp_phone'] ?? '')),
        'whatsapp_text' => trim((string)($_POST['whatsapp_text'] ?? '')),
        'source' => trim((string)($_POST['source'] ?? 'admin')) ?: 'admin',
    ];
}

function admin_csv_detect_delimiter(string $path): string
{
    $sample = '';
    $handle = fopen($path, 'rb');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $sample = $line;
                break;
            }
        }
        fclose($handle);
    }

    $candidates = [',', ';', "\t", '|'];
    $bestDelimiter = ',';
    $bestCount = -1;

    foreach ($candidates as $delimiter) {
        $count = substr_count($sample, $delimiter);
        if ($count > $bestCount) {
            $bestCount = $count;
            $bestDelimiter = $delimiter;
        }
    }

    return $bestDelimiter;
}

function admin_csv_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = str_replace(["\xef\xbb\xbf", ' ', '-', '.', '/', '(', ')'], ['', '_', '_', '_', '_', '', ''], $value);
    $value = preg_replace('/[^a-z0-9_]/', '', $value) ?: '';
    return trim((string)$value, '_');
}

function admin_csv_has_header(array $firstRow): bool
{
    $known = [
        'title','judul','judul_artikel','content','isi','isi_artikel','body','artikel',
        'image','gambar','link_gambar','featured_image','whatsapp','wa','nomor_wa',
        'meta_title','meta_description','slug','kategori','category'
    ];

    foreach ($firstRow as $cell) {
        if (in_array(admin_csv_key((string)$cell), $known, true)) {
            return true;
        }
    }

    return false;
}

function admin_parse_csv(string $path): array
{
    $delimiter = admin_csv_detect_delimiter($path);
    $handle = fopen($path, 'rb');
    if (!$handle) {
        return [];
    }

    $firstRow = fgetcsv($handle, 0, $delimiter, '"', '\\');
    if (!$firstRow) {
        fclose($handle);
        return [];
    }

    $hasHeader = admin_csv_has_header($firstRow);
    $headers = $hasHeader
        ? array_map(static fn($value): string => admin_csv_key((string)$value), $firstRow)
        : array_map(static fn($index): string => 'column_' . ($index + 1), array_keys($firstRow));

    $rows = [];

    $pushRow = static function (array $data) use (&$rows, $headers): void {
        if (!array_filter($data, static fn($value): bool => trim((string)$value) !== '')) {
            return;
        }

        $row = [];
        foreach ($headers as $index => $key) {
            $row[$key] = isset($data[$index]) ? trim((string)$data[$index]) : '';
        }

        foreach ($data as $index => $value) {
            $row['column_' . ($index + 1)] = trim((string)$value);
        }

        $rows[] = $row;
    };

    if (!$hasHeader) {
        $pushRow($firstRow);
    }

    while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        $pushRow($data);
    }

    fclose($handle);
    return $rows;
}

function admin_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_import_dir(): string
{
    $dir = STORAGE_PATH . '/imports';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function admin_import_token_path(string $token): string
{
    $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
    return admin_import_dir() . '/' . $token . '.json';
}

function admin_import_save_rows(array $rows): string
{
    $token = bin2hex(random_bytes(16));
    file_put_contents(admin_import_token_path($token), json_encode([
        'created_at' => date('Y-m-d H:i:s'),
        'total' => count($rows),
        'rows' => array_values($rows),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $token;
}

function admin_import_read_rows(string $token): array
{
    $path = admin_import_token_path($token);
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) && is_array($decoded['rows'] ?? null) ? $decoded['rows'] : [];
}

function admin_import_cleanup(string $token): void
{
    $path = admin_import_token_path($token);
    if (is_file($path)) {
        unlink($path);
    }
}


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();

    if (!admin_article_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'artikel']);
            }
            $message = 'Login berhasil. Dashboard artikel SEO siap dipakai.';
        } else {
            $error = 'Password admin salah.';
        }
    } else {
        $postAction = (string)($_POST['form_action'] ?? '');

        if ($postAction === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $message = article_delete($id) ? 'Artikel berhasil dihapus.' : 'Gagal menghapus artikel.';
            $action = 'list';
        }

        if ($postAction === 'import_csv') {
            if (empty($_FILES['csv_file']['tmp_name']) || ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $error = 'File CSV belum dipilih atau gagal diupload.';
            } else {
                $rows = admin_parse_csv((string)$_FILES['csv_file']['tmp_name']);
                if (!$rows) {
                    $error = 'CSV kosong atau header tidak terbaca.';
                } else {
                    $result = article_import_many($rows);
                    $message = 'Import selesai: ' . (int)$result['created'] . ' artikel masuk, ' . (int)$result['skipped'] . ' dilewati.';
                    $importLogs = $result['logs'] ?? [];
                    $action = 'import';
                }
            }
        }

        if ($postAction === 'mass_delete') {
            $deleteMode = (string)($_POST['delete_mode'] ?? 'selected');
            $deleteMedia = isset($_POST['delete_media']);
            $beforeArticles = managed_articles();

            if ($deleteMode === 'all_managed') {
                $result = article_delete_all_managed();
            } else {
                $ids = array_map('intval', (array)($_POST['ids'] ?? []));
                $result = article_delete_many($ids);
            }

            $mediaDeleted = $deleteMedia ? article_delete_media_files($beforeArticles) : 0;
            $message = 'Hapus massal selesai: ' . (int)$result['deleted'] . ' artikel dihapus' . ($mediaDeleted > 0 ? ', ' . $mediaDeleted . ' file gambar upload ikut dihapus.' : '.');
            $action = 'mass-delete';
        }

        if ($postAction === 'convert_seed') {
            $result = article_convert_seed_to_storage();
            $message = 'Import contoh artikel selesai: ' . (int)$result['created'] . ' dibuat, ' . (int)$result['skipped'] . ' dilewati.';
            $action = 'list';
        }

        if ($postAction === 'save') {
            try {
                $payload = admin_article_payload();

                if ($payload['title'] === '' || $payload['excerpt'] === '' || trim(strip_tags($payload['content'])) === '') {
                    $error = 'Judul, ringkasan/meta description, dan isi artikel wajib diisi.';
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id > 0) {
                        $updated = article_update($id, $payload);
                        if ($updated && function_exists('content_restriction_save_for')) {
                            content_restriction_save_for('article', ['id' => $id, 'slug' => $payload['slug'] ?? ''], content_restriction_rule_from_post($_POST));
                        }
                        $message = $updated ? 'Artikel berhasil diperbarui.' : 'Gagal memperbarui artikel.';
                    } else {
                        $newId = article_create($payload);
                        if ($newId > 0) {
                            if (function_exists('content_restriction_save_for')) {
                                content_restriction_save_for('article', ['id' => $newId, 'slug' => $payload['slug'] ?? ''], content_restriction_rule_from_post($_POST));
                            }
                            $seoDraftId = trim((string)($_POST['seo_draft_id'] ?? ''));
                            if ($seoDraftId !== '' && function_exists('seo_draft_publisher_link_article')) {
                                seo_draft_publisher_link_article($seoDraftId, $newId);
                                $message = 'Artikel berhasil dibuat dari SEO Draft Publisher dan otomatis masuk sitemap/RSS.';
                            } else {
                                $message = 'Artikel berhasil dibuat dan otomatis masuk sitemap/RSS.';
                            }
                        } else {
                            $message = 'Gagal menyimpan artikel.';
                        }
                    }
                    $action = 'list';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$loggedIn = admin_article_logged_in();
$editingArticle = ($loggedIn && $editId > 0) ? article_admin_find($editId) : null;
$seoDraftPrefill = null;
$seoDraftId = trim((string)($_GET['seo_draft'] ?? ''));
if ($loggedIn && $action === 'create' && $seoDraftId !== '' && function_exists('seo_draft_publisher_article_prefill')) {
    $seoDraftPrefill = seo_draft_publisher_article_prefill($seoDraftId);
}
$articleCategoryOptions = article_categories();
$currentArticleCategory = trim((string)(($editingArticle['category'] ?? '') ?: ($_POST['category'] ?? '')));
if ($currentArticleCategory !== '' && !in_array($currentArticleCategory, $articleCategoryOptions, true)) {
    $articleCategoryOptions[] = $currentArticleCategory;
}
$storageLabel = article_storage_mode() === 'mysql' ? 'MySQL aktif' : 'JSON file aktif';
$managedArticles = $loggedIn ? managed_articles() : [];
$articleSearchQuery = trim((string)($_GET['q'] ?? ''));
$articlePerPage = (int)($_GET['per_page'] ?? 10);
$articlePerPageOptions = [10, 20, 50, 100];
if (!in_array($articlePerPage, $articlePerPageOptions, true)) {
    $articlePerPage = 10;
}
$articleCurrentPage = max(1, (int)($_GET['page'] ?? 1));
$articleFiltered = $managedArticles;

if ($articleSearchQuery !== '') {
    $needle = mb_strtolower($articleSearchQuery);
    $articleFiltered = array_values(array_filter($managedArticles, static function (array $article) use ($needle): bool {
        $haystack = implode(' ', array_map('strval', [
            $article['title'] ?? '',
            $article['slug'] ?? '',
            $article['category'] ?? '',
            $article['excerpt'] ?? '',
            $article['focus_keyword'] ?? '',
            $article['author'] ?? '',
            $article['published_at'] ?? '',
        ]));

        return str_contains(mb_strtolower($haystack), $needle);
    }));
}

$articleTotal = count($articleFiltered);
$articleTotalPages = max(1, (int)ceil($articleTotal / $articlePerPage));
$articleCurrentPage = min($articleCurrentPage, $articleTotalPages);
$articleOffset = ($articleCurrentPage - 1) * $articlePerPage;
$articlePageItems = array_slice($articleFiltered, $articleOffset, $articlePerPage);

function admin_article_page_url(int $page, ?int $perPage = null): string
{
    $query = array_filter([
        'q' => trim((string)($_GET['q'] ?? '')),
        'per_page' => $perPage ?? (int)($_GET['per_page'] ?? 10),
        'page' => $page,
    ], static fn($value): bool => $value !== '' && $value !== null);

    return url('admin/artikel' . ($query ? '?' . http_build_query($query) : ''));
}
$GLOBALS['admin_page'] = true;

if ($loggedIn && str_starts_with($action, 'ajax_')) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        admin_json_response(['ok' => false, 'message' => 'Method tidak valid.'], 405);
    }

    require_csrf();

    if ($action === 'ajax_import_prepare') {
        if (empty($_FILES['csv_file']['tmp_name']) || ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            admin_json_response(['ok' => false, 'message' => 'File CSV belum dipilih atau gagal diupload.'], 422);
        }

        $rows = admin_parse_csv((string)$_FILES['csv_file']['tmp_name']);
        if (!$rows) {
            admin_json_response(['ok' => false, 'message' => 'CSV kosong atau tidak terbaca.'], 422);
        }

        $token = admin_import_save_rows($rows);
        admin_json_response(['ok' => true, 'token' => $token, 'total' => count($rows), 'message' => 'CSV siap diproses.']);
    }

    if ($action === 'ajax_import_batch') {
        $token = (string)($_POST['token'] ?? '');
        $offset = max(0, (int)($_POST['offset'] ?? 0));
        $limit = min(25, max(1, (int)($_POST['limit'] ?? 10)));
        $rows = admin_import_read_rows($token);

        if (!$rows) {
            admin_json_response(['ok' => false, 'message' => 'Sesi import tidak ditemukan. Upload CSV ulang.'], 404);
        }

        $total = count($rows);
        $batch = array_slice($rows, $offset, $limit);
        $result = article_import_many($batch);
        $processed = min($total, $offset + count($batch));
        $done = $processed >= $total;

        if ($done) {
            admin_import_cleanup($token);
        }

        admin_json_response([
            'ok' => true,
            'created' => (int)$result['created'],
            'skipped' => (int)$result['skipped'],
            'processed' => $processed,
            'total' => $total,
            'percent' => $total > 0 ? round(($processed / $total) * 100, 2) : 100,
            'done' => $done,
            'logs' => array_slice($result['logs'] ?? [], 0, 12),
        ]);
    }

    admin_json_response(['ok' => false, 'message' => 'Aksi AJAX tidak dikenal.'], 404);
}

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Panel Admin Artikel SEO</div>
                <h1>Kelola Artikel Produk & Layanan</h1>
                <p>Tambah, edit, hapus artikel, upload gambar, atur SEO, dan import massal CSV. Mode penyimpanan: <strong><?= esc($storageLabel); ?></strong>.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-login-layout">
                    <div class="admin-login-copy">
                        <span class="admin-badge">Akses terbatas</span>
                        <h2>Masuk untuk mengelola artikel SEO</h2>
                        <p>Dashboard ini dipakai untuk menulis artikel, mengatur tampilan di Google, upload gambar, dan import artikel massal dari CSV.</p>
                        <ul class="admin-checklist">
                            <li>Artikel otomatis masuk daftar halaman website dan feed.</li>
                            <li>Struktur data artikel dan breadcrumb otomatis.</li>
                            <li>Upload gambar dengan alt SEO.</li>
                        </ul>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <div class="admin-form-head">
                            <h2>Login Admin</h2>
                            <p>Gunakan password dari file <code>.env</code>.</p>
                        </div>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                        <p class="admin-help">Belum bisa login? Pastikan <code>ADMIN_PASSWORD</code> sudah diisi.</p>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-toolbar">
                    <div>
                        <span class="admin-badge"><?= esc($storageLabel); ?></span>
                        <h2><?= ($action === 'create') ? 'Tulis Artikel Baru' : (($action === 'edit') ? 'Edit Artikel SEO' : (($action === 'import') ? 'Import Artikel Massal' : (($action === 'mass-delete') ? 'Hapus Artikel Massal' : 'Daftar Artikel'))); ?></h2>
                    </div>
                    <div class="admin-toolbar__actions">
                        <a class="admin-btn <?= ($action === 'list') ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= url('admin/artikel'); ?>">Daftar</a>
                        <a class="admin-btn <?= ($action === 'create') ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= url('admin/artikel?action=create'); ?>">+ Tulis</a>
                        <a class="admin-btn <?= ($action === 'import') ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= url('admin/artikel?action=import'); ?>">Import CSV</a>
                        <a class="admin-btn <?= ($action === 'mass-delete') ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= url('admin/artikel?action=mass-delete'); ?>">Hapus Massal</a>
                        <form method="post" onsubmit="return confirm('Siapkan konten awal artikel agar bisa diedit dari dashboard? Artikel dengan slug yang sama akan dilewati.');" style="display:inline-flex">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_action" value="convert_seed">
                            <button class="admin-btn admin-btn--soft" type="submit">Siapkan Konten Awal</button>
                        </form>
                    </div>
                </div>

                <?php if ($action === 'import'): ?>
                    <div class="admin-card admin-import-card">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <h2>Import Artikel dari CSV</h2>
                                <p>Adaptasi dari plugin CSV importer WordPress: sekarang bisa dipakai langsung di website custom PHP ini.</p>
                            </div>
                        </div>
                        <form method="post" enctype="multipart/form-data" class="admin-import-form" id="ajax-import-form" data-prepare-url="<?= url('admin/artikel?action=ajax_import_prepare'); ?>" data-batch-url="<?= url('admin/artikel?action=ajax_import_batch'); ?>">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_action" value="import_csv">
                            <label for="csv_file">Upload file CSV</label>
                            <input id="csv_file" type="file" name="csv_file" accept=".csv,text/csv" required>
                            <button type="submit" class="admin-btn admin-btn--primary">Import dengan Progress Bar</button>
                            <p class="admin-help">Browser jangan ditutup sampai proses selesai. Import akan diproses bertahap per batch agar tidak timeout.</p>
                        </form>
                        <div class="admin-progress" id="import-progress" hidden>
                            <div class="admin-progress__head">
                                <strong id="import-progress-title">Menyiapkan import...</strong>
                                <span id="import-progress-percent">0%</span>
                            </div>
                            <div class="admin-progress__bar"><span id="import-progress-bar" style="width:0%"></span></div>
                            <div class="admin-progress__meta" id="import-progress-meta">0/0 artikel diproses.</div>
                            <div class="admin-log-box" id="import-progress-log"></div>
                        </div>
                        <div class="admin-help-box">
                            <strong>Header CSV yang didukung:</strong>
                            <code>title, slug, category, excerpt, content, image, image_alt, author, published_at, meta_title, meta_description, meta_keywords, canonical_url, og_title, og_description, focus_keyword, robots, breadcrumb_title, schema_type, faq_json</code>
                            <p>Header lama plugin seperti <code>column_6</code>, <code>column_8</code>, <code>column_12</code> juga tetap dibaca untuk kompatibilitas.</p>
                        </div>
                        <?php if ($importLogs): ?>
                            <div class="admin-log-box">
                                <?php foreach (array_slice($importLogs, 0, 40) as $log): ?>
                                    <div><?= esc((string)$log); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($action === 'mass-delete'): ?>
                    <div class="admin-card admin-import-card">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <h2>Hapus Artikel Massal</h2>
                                <p>Fitur hapus massal dibuat aman untuk membersihkan artikel yang dibuat atau diimpor dari dashboard. Artikel contoh bawaan tetap aman.</p>
                            </div>
                        </div>
                        <form method="post" class="admin-mass-delete-form" onsubmit="return confirm('Yakin mau hapus artikel yang dipilih? Aksi ini tidak bisa di-undo.');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_action" value="mass_delete">
                            <div class="admin-help-box admin-help-box--danger">
                                <strong>PERINGATAN:</strong> gunakan fitur ini untuk membersihkan artikel hasil import massal yang salah. Backup file/database dulu sebelum eksekusi di hosting live.
                            </div>
                            <div class="admin-form-grid">
                                <div class="admin-field">
                                    <label for="delete_mode">Mode hapus</label>
                                    <select id="delete_mode" name="delete_mode">
                                        <option value="selected">Hapus artikel yang dicentang</option>
                                        <option value="all_managed">Hapus semua artikel admin/import</option>
                                    </select>
                                </div>
                                <label class="admin-check-field">
                                    <input type="checkbox" name="delete_media" value="1">
                                    Ikut hapus file gambar upload lokal di <code>assets/uploads/articles</code>
                                </label>
                            </div>
                            <div class="admin-delete-toolbar">
                                <div class="admin-delete-search">
                                    <label for="delete-search">Cari artikel</label>
                                    <input id="delete-search" type="search" placeholder="Cari judul, slug, atau tanggal...">
                                </div>
                                <div class="admin-delete-summary">
                                    <strong id="delete-selected-count">0</strong> dipilih dari <strong id="delete-total-count"><?= count($managedArticles); ?></strong> artikel
                                </div>
                            </div>
                            <div class="admin-select-actions admin-select-actions--sticky">
                                <button type="button" class="admin-btn admin-btn--soft" id="select-all-delete">Centang Semua</button>
                                <button type="button" class="admin-btn admin-btn--soft" id="clear-all-delete">Batal Centang</button>
                                <button type="submit" class="admin-btn admin-btn--danger">Hapus Artikel Terpilih</button>
                            </div>
                            <div class="admin-delete-list" id="admin-delete-list">
                                <?php if (!$managedArticles): ?>
                                    <p class="admin-empty-text">Belum ada artikel aktif untuk dihapus.</p>
                                <?php endif; ?>
                                <?php foreach ($managedArticles as $article): ?>
                                    <?php
                                        $deleteTitle = (string)$article['title'];
                                        $deleteSlug = (string)$article['slug'];
                                        $deleteDate = (string)$article['published_at'];
                                        $deleteCategory = (string)($article['category'] ?? 'Artikel');
                                    ?>
                                    <div class="admin-delete-item" data-search="<?= esc(strtolower($deleteTitle . ' ' . $deleteSlug . ' ' . $deleteDate . ' ' . $deleteCategory)); ?>">
                                        <div class="admin-delete-check">
                                            <input id="delete-article-<?= (int)$article['id']; ?>" type="checkbox" name="ids[]" value="<?= (int)$article['id']; ?>" aria-label="Pilih artikel <?= esc($deleteTitle); ?>">
                                        </div>
                                        <label class="admin-delete-content" for="delete-article-<?= (int)$article['id']; ?>">
                                            <span class="admin-delete-topline">
                                                <strong><?= esc($deleteTitle); ?></strong>
                                                <em><?= esc($deleteCategory); ?></em>
                                            </span>
                                            <span class="admin-delete-meta">
                                                <span>/artikel/<?= esc($deleteSlug); ?></span>
                                                <span><?= esc($deleteDate); ?></span>
                                            </span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </div>
                <?php elseif ($action === 'create' || $action === 'edit'): ?>
                    <?php
                    $article = $editingArticle ?: ($seoDraftPrefill ?: []);
                    $selectedMediaImage = trim((string)($_GET['media_image'] ?? ''));
                    if ($selectedMediaImage !== '' && function_exists('media_library_is_allowed_image_url') && media_library_is_allowed_image_url($selectedMediaImage)) {
                        $article['image'] = $selectedMediaImage;
                    }
                    ?>
                    <form method="post" enctype="multipart/form-data" class="admin-card admin-editor">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="save">
                        <input type="hidden" name="id" value="<?= (int)($article['id'] ?? 0); ?>">
                        <?php if (!empty($article['_seo_draft_id'])): ?>
                            <input type="hidden" name="seo_draft_id" value="<?= esc((string)$article['_seo_draft_id']); ?>">
                            <input type="hidden" name="seo_task_id" value="<?= esc((string)($article['_seo_task_id'] ?? '')); ?>">
                            <input type="hidden" name="source" value="seo-draft">
                        <?php endif; ?>

                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <h2><?= $editingArticle ? 'Edit Artikel SEO' : 'Artikel Baru SEO'; ?></h2>
                                <p>Isi konten, meta SEO, gambar, dan schema untuk memperkuat indexability halaman artikel.</p>
                            </div>
                            <button type="submit" class="admin-btn admin-btn--primary">Simpan Artikel</button>
                        </div>
                        <?php if (!empty($article['_seo_draft_id'])): ?>
                            <div class="admin-alert admin-alert--info">Draft ini dibuat dari SEO Draft Publisher. Review isi, data bisnis, gambar, internal link, dan CTA sebelum disimpan sebagai artikel.</div>
                        <?php endif; ?>

                        <div class="admin-editor-layout">
                            <div class="admin-editor-main">
                                <div class="admin-panel">
                                    <h3>Konten Utama</h3>
                                    <div class="admin-form-grid">
                                        <div class="admin-field admin-field--wide">
                                            <label for="title">Judul Artikel <span>*</span></label>
                                            <input id="title" type="text" name="title" value="<?= esc((string)($article['title'] ?? '')); ?>" placeholder="Contoh: Panduan Memilih Produk atau Layanan yang Tepat" required>
                                        </div>
                                        <div class="admin-field">
                                            <label for="slug">Slug URL</label>
                                            <input id="slug" type="text" name="slug" value="<?= esc((string)($article['slug'] ?? '')); ?>" placeholder="otomatis-jika-kosong">
                                        </div>
                                        <div class="admin-field">
                                            <label for="category">Kategori</label>
                                            <select id="category" name="category">
                                                <?php foreach ($articleCategoryOptions as $category): ?>
                                                    <option value="<?= esc($category); ?>" <?= (($article['category'] ?? 'Layanan') === $category) ? 'selected' : ''; ?>><?= esc($category); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="admin-field admin-field--wide">
                                            <label for="excerpt">Ringkasan / Meta Description Dasar <span>*</span></label>
                                            <textarea id="excerpt" name="excerpt" rows="3" maxlength="255" required><?= esc((string)($article['excerpt'] ?? '')); ?></textarea>
                                        </div>
                                        <div class="admin-field admin-field--wide">
                                            <label for="content">Isi Artikel <span>*</span></label>
                                            <textarea id="content" name="content" rows="22" class="ckeditor-content" required><?= esc((string)($article['content'] ?? '')); ?></textarea>
                                            <small>Editor menggunakan Jodit WYSIWYG yang lebih lengkap: heading, bold, italic, underline, coret/del, align, list, quote, link, gambar URL, tabel, undo/redo, dan mode HTML/source.</small>
                                            <noscript><small>JavaScript mati, editor akan berubah menjadi textarea HTML biasa.</small></noscript>
                                        </div>
                                    </div>
                                </div>

                                <div class="admin-panel">
                                    <h3>SEO Lengkap</h3>
                                    <div class="admin-form-grid">
                                        <div class="admin-field">
                                            <label for="meta_title">Meta Title</label>
                                            <input id="meta_title" name="meta_title" maxlength="180" value="<?= esc((string)($article['meta_title'] ?? '')); ?>" placeholder="Judul SEO halaman">
                                        </div>
                                        <div class="admin-field">
                                            <label for="focus_keyword">Focus Keyword</label>
                                            <input id="focus_keyword" name="focus_keyword" value="<?= esc((string)($article['focus_keyword'] ?? '')); ?>" placeholder="contoh: produk fisik bogor">
                                        </div>
                                        <div class="admin-field admin-field--wide">
                                            <label for="meta_description">Meta Description</label>
                                            <textarea id="meta_description" name="meta_description" maxlength="255" rows="3"><?= esc((string)($article['meta_description'] ?? '')); ?></textarea>
                                        </div>
                                        <div class="admin-field admin-field--wide">
                                            <label for="meta_keywords">Meta Keywords</label>
                                            <input id="meta_keywords" name="meta_keywords" value="<?= esc((string)($article['meta_keywords'] ?? implode(', ', (array)($article['keywords'] ?? [])))); ?>" placeholder="produk fisik bogor, layanan paket, area layanan bekasi">
                                        </div>
                                        <div class="admin-field">
                                            <label for="canonical_url">Canonical URL</label>
                                            <input id="canonical_url" type="url" name="canonical_url" value="<?= esc((string)($article['canonical_url'] ?? '')); ?>" placeholder="kosongkan untuk otomatis">
                                        </div>
                                        <div class="admin-field">
                                            <label for="robots">Robots</label>
                                            <select id="robots" name="robots">
                                                <?php foreach (['index, follow','noindex, follow','index, nofollow','noindex, nofollow'] as $robots): ?>
                                                    <option value="<?= esc($robots); ?>" <?= (($article['robots'] ?? 'index, follow') === $robots) ? 'selected' : ''; ?>><?= esc($robots); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="admin-field">
                                            <label for="og_title">OG Title</label>
                                            <input id="og_title" name="og_title" value="<?= esc((string)($article['og_title'] ?? '')); ?>">
                                        </div>
                                        <div class="admin-field">
                                            <label for="og_description">OG Description</label>
                                            <input id="og_description" name="og_description" value="<?= esc((string)($article['og_description'] ?? '')); ?>">
                                        </div>
                                        <div class="admin-field admin-field--wide">
                                            <label for="keywords">Keyword Internal / Tags SEO</label>
                                            <input id="keywords" name="keywords" value="<?= esc(implode(', ', (array)($article['keywords'] ?? []))); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="admin-panel">
                                    <h3>Schema Markup</h3>
                                    <div class="admin-form-grid">
                                        <div class="admin-field">
                                            <label for="schema_type">Schema Artikel</label>
                                            <select id="schema_type" name="schema_type">
                                                <?php foreach (['Article','BlogPosting','NewsArticle'] as $schema): ?>
                                                    <option value="<?= esc($schema); ?>" <?= (($article['schema_type'] ?? 'Article') === $schema) ? 'selected' : ''; ?>><?= esc($schema); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="admin-field">
                                            <label for="breadcrumb_title">Judul Breadcrumb</label>
                                            <input id="breadcrumb_title" name="breadcrumb_title" value="<?= esc((string)($article['breadcrumb_title'] ?? $article['title'] ?? '')); ?>">
                                        </div>
                                        <div class="admin-field admin-field--wide">
                                            <label for="faq_json">FAQ Schema JSON Opsional</label>
                                            <textarea id="faq_json" name="faq_json" rows="5" placeholder='[{"question":"Apa itu layanan?","answer":"Layanan adalah ..."}]'><?= esc((string)($article['faq_json'] ?? '')); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <aside class="admin-editor-side">
                                <div class="admin-panel">
                                    <h3>Gambar Artikel</h3>
                                    <?php if (!empty($article['image'])): ?>
                                        <img class="admin-preview-image" src="<?= esc((string)$article['image']); ?>" alt="Preview">
                                    <?php endif; ?>
                                    <label for="featured_image">Upload Gambar Baru</label>
                                    <input id="featured_image" type="file" name="featured_image" accept="image/jpeg,image/png,image/webp">
                                    <small>Maksimal 2MB. JPG, PNG, WebP.</small>
                                    <label for="image">Atau URL Gambar</label>
                                    <input id="image" type="url" name="image" value="<?= esc((string)($article['image'] ?? '')); ?>" placeholder="https://...">
                                    <a class="admin-btn admin-btn--soft admin-btn--full" href="<?= url('admin/media-library?target=article'); ?>" target="_blank" rel="noopener">Pilih dari Media Library</a>
                                    <label for="image_alt">Alt Gambar SEO</label>
                                    <input id="image_alt" name="image_alt" value="<?= esc((string)($article['image_alt'] ?? $article['title'] ?? '')); ?>">
                                    <label for="image_title">Title Gambar</label>
                                    <input id="image_title" name="image_title" value="<?= esc((string)($article['image_title'] ?? $article['title'] ?? '')); ?>">
                                </div>
                                <?php if (function_exists('seo_quality_render_inline_assistant')) { seo_quality_render_inline_assistant('article', $article); } ?>

                                <div class="admin-panel">
                                    <h3>CTA WhatsApp Artikel</h3>
                                    <label for="whatsapp_label">Label Tombol</label>
                                    <input id="whatsapp_label" name="whatsapp_label" value="<?= esc((string)($article['whatsapp_label'] ?? 'Chat WhatsApp')); ?>" placeholder="Chat WhatsApp">
                                    <label for="whatsapp_phone">Nomor WhatsApp</label>
                                    <input id="whatsapp_phone" name="whatsapp_phone" value="<?= esc((string)($article['whatsapp_phone'] ?? '')); ?>" placeholder="628xxxxxxxxxx">
                                    <label for="whatsapp_text">Auto Teks Chat</label>
                                    <textarea id="whatsapp_text" name="whatsapp_text" rows="4" placeholder="Hallo Kak, saya mau tanya..."><?= esc((string)($article['whatsapp_text'] ?? '')); ?></textarea>
                                </div>
                                <?= function_exists('content_restriction_admin_fields') ? content_restriction_admin_fields('article', $article) : ''; ?>

                                <div class="admin-panel">
                                    <h3>Publikasi</h3>
                                    <label for="author">Penulis</label>
                                    <input id="author" type="text" name="author" value="<?= esc((string)($article['author'] ?? SITE_NAME)); ?>">
                                    <label for="published_at">Tanggal Terbit</label>
                                    <input id="published_at" type="text" name="published_at" value="<?= esc((string)($article['published_at'] ?? date('Y-m-d H:i:s'))); ?>" placeholder="YYYY-MM-DD HH:MM:SS">
                                    <label class="admin-toggle">
                                        <input type="checkbox" name="featured" value="1" <?= !empty($article['featured']) ? 'checked' : ''; ?>>
                                        <span>Jadikan artikel unggulan</span>
                                    </label>
                                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Simpan Artikel</button>
                                    <a class="admin-btn admin-btn--soft admin-btn--full" href="<?= url('admin/artikel'); ?>">Batal</a>
                                </div>
                            </aside>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="admin-list-tools admin-list-tools--standalone">
                        <form method="get" action="<?= url('admin/artikel'); ?>" class="admin-list-search">
                            <input type="search" name="q" value="<?= esc($articleSearchQuery); ?>" placeholder="Cari judul, slug, kategori, keyword, penulis...">
                            <select name="per_page" aria-label="Jumlah artikel per halaman">
                                <?php foreach ($articlePerPageOptions as $option): ?>
                                    <option value="<?= (int)$option; ?>" <?= $articlePerPage === $option ? 'selected' : ''; ?>><?= (int)$option; ?> / halaman</option>
                                <?php endforeach; ?>
                            </select>
                            <button class="admin-btn admin-btn--primary" type="submit">Cari</button>
                            <?php if ($articleSearchQuery !== ''): ?>
                                <a class="admin-btn admin-btn--soft" href="<?= url('admin/artikel'); ?>">Reset</a>
                            <?php endif; ?>
                        </form>
                        <div class="admin-list-summary">
                            Menampilkan <?= $articleTotal > 0 ? (int)($articleOffset + 1) : 0; ?>-<?= (int)min($articleOffset + $articlePerPage, $articleTotal); ?> dari <?= (int)$articleTotal; ?> artikel<?= $articleSearchQuery !== '' ? ' untuk pencarian “' . esc($articleSearchQuery) . '”' : ''; ?>.
                        </div>
                    </div>

                    <div class="admin-list">
                        <?php if (!$managedArticles): ?>
                            <div class="admin-empty">
                                <h2>Belum ada artikel custom</h2>
                                <p>Klik tombol “Tulis Artikel” atau import CSV untuk membuat konten pertama.</p>
                                <a class="admin-btn admin-btn--primary" href="<?= url('admin/artikel?action=create'); ?>">+ Tulis Artikel</a>
                            </div>
                        <?php elseif (!$articlePageItems): ?>
                            <div class="admin-empty admin-empty--compact">
                                <h2>Artikel tidak ditemukan</h2>
                                <p>Coba kata kunci lain atau reset pencarian.</p>
                                <a class="admin-btn admin-btn--soft" href="<?= url('admin/artikel'); ?>">Reset Filter</a>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($articlePageItems as $article): ?>
                            <article class="admin-article-row">
                                <div class="admin-article-row__main">
                                    <span class="admin-category"><?= esc((string)$article['category']); ?></span>
                                    <h3><?= esc((string)$article['title']); ?> <span class="admin-source-badge"><?= esc(article_source_label($article)); ?></span></h3>
                                    <p><?= esc((string)$article['excerpt']); ?></p>
                                    <div class="admin-meta">
                                        <span><?= esc((string)$article['published_at']); ?></span>
                                        <span>/artikel/<?= esc((string)$article['slug']); ?></span>
                                        <?php if (!empty($article['focus_keyword'])): ?><span>Keyword: <?= esc((string)$article['focus_keyword']); ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="admin-row-actions">
                                    <a class="admin-btn admin-btn--soft" href="<?= article_url((string)$article['slug']); ?>" target="_blank" rel="noopener">Lihat</a>
                                    <a class="admin-btn admin-btn--primary" href="<?= url('admin/artikel?action=edit&id=' . (int)$article['id']); ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Hapus artikel ini?')">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$article['id']; ?>">
                                        <button type="submit" class="admin-btn admin-btn--danger">Hapus</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($articleTotalPages > 1): ?>
                        <nav class="admin-pagination" aria-label="Pagination artikel admin">
                            <a class="admin-page-link <?= $articleCurrentPage <= 1 ? 'is-disabled' : ''; ?>" href="<?= $articleCurrentPage <= 1 ? '#' : admin_article_page_url($articleCurrentPage - 1); ?>">‹ Prev</a>
                            <?php for ($i = max(1, $articleCurrentPage - 2); $i <= min($articleTotalPages, $articleCurrentPage + 2); $i++): ?>
                                <a class="admin-page-link <?= $i === $articleCurrentPage ? 'is-active' : ''; ?>" href="<?= admin_article_page_url($i); ?>"><?= (int)$i; ?></a>
                            <?php endfor; ?>
                            <a class="admin-page-link <?= $articleCurrentPage >= $articleTotalPages ? 'is-disabled' : ''; ?>" href="<?= $articleCurrentPage >= $articleTotalPages ? '#' : admin_article_page_url($articleCurrentPage + 1); ?>">Next ›</a>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>


<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jodit@4.2.47/es2021/jodit.min.css">
<script src="https://cdn.jsdelivr.net/npm/jodit@4.2.47/es2021/jodit.min.js"></script>
<script>
(function () {
    const form = document.getElementById('ajax-import-form');
    if (form) {
        const progressWrap = document.getElementById('import-progress');
        const bar = document.getElementById('import-progress-bar');
        const percentText = document.getElementById('import-progress-percent');
        const title = document.getElementById('import-progress-title');
        const meta = document.getElementById('import-progress-meta');
        const logBox = document.getElementById('import-progress-log');
        const button = form.querySelector('button[type="submit"]');
        const csrfInput = form.querySelector('input[type="hidden"][name]');

        const appendLog = (items) => {
            (items || []).forEach((item) => {
                const div = document.createElement('div');
                div.textContent = item;
                logBox.prepend(div);
            });
        };

        const setProgress = (percent, processed, total) => {
            const safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
            bar.style.width = safePercent + '%';
            percentText.textContent = safePercent.toFixed(safePercent % 1 ? 2 : 0) + '%';
            meta.textContent = processed + '/' + total + ' artikel diproses.';
        };

        async function postForm(url, data) {
            const response = await fetch(url, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const json = await response.json().catch(() => null);
            if (!response.ok || !json || !json.ok) {
                throw new Error((json && json.message) ? json.message : 'Request gagal diproses server.');
            }
            return json;
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            progressWrap.hidden = false;
            logBox.innerHTML = '';
            setProgress(0, 0, 0);
            title.textContent = 'Upload CSV dan menyiapkan data...';
            button.disabled = true;
            button.textContent = 'Import sedang berjalan...';

            try {
                const prepareData = new FormData(form);
                prepareData.delete('form_action');
                const prepared = await postForm(form.dataset.prepareUrl, prepareData);
                let offset = 0;
                const total = Number(prepared.total || 0);
                const token = prepared.token;
                const limit = 10;

                title.textContent = 'Import artikel sedang berjalan...';
                setProgress(0, 0, total);

                while (offset < total) {
                    const batchData = new FormData();
                    batchData.append(csrfInput.name, csrfInput.value);
                    batchData.append('token', token);
                    batchData.append('offset', String(offset));
                    batchData.append('limit', String(limit));

                    const result = await postForm(form.dataset.batchUrl, batchData);
                    offset = Number(result.processed || (offset + limit));
                    setProgress(result.percent, result.processed, result.total);
                    appendLog(result.logs || []);

                    if (result.done) {
                        break;
                    }
                }

                title.textContent = 'Import selesai.';
                setProgress(100, total, total);
            } catch (error) {
                title.textContent = 'Import gagal.';
                appendLog([error.message || 'Terjadi kesalahan saat import.']);
            } finally {
                button.disabled = false;
                button.textContent = 'Import dengan Progress Bar';
            }
        });
    }



    const joditUploadUrl = <?= json_encode(url('admin/jodit-upload')); ?>;
    const joditCsrfName = <?= json_encode(CSRF_TOKEN_NAME); ?>;
    const joditCsrfToken = <?= json_encode(csrf_token()); ?>;

    const contentTextarea = document.getElementById('content');
    if (contentTextarea && typeof Jodit !== 'undefined') {
        const editor = Jodit.make(contentTextarea, {
            height: 560,
            minHeight: 420,
            language: 'id',
            toolbarAdaptive: false,
            toolbarSticky: true,
            askBeforePasteHTML: false,
            askBeforePasteFromWord: false,
            defaultActionOnPaste: 'insert_clear_html',
            cleanHTML: {
                removeEmptyElements: false,
                fillEmptyParagraph: false
            },
            uploader: {
                url: joditUploadUrl,
                method: 'POST',
                format: 'json',
                insertImageAsBase64URI: false,
                filesVariableName: () => 'files',
                prepareData: function (formData) {
                    formData.append(joditCsrfName, joditCsrfToken);
                    formData.append('editor_context', 'article');
                    return formData;
                },
                isSuccess: function (response) {
                    return !!(response && response.success === true);
                },
                getMessage: function (response) {
                    return response && response.message ? response.message : 'Upload gambar gagal.';
                },
                process: function (response) {
                    return response || {};
                },
                defaultHandlerSuccess: function (response) {
                    const files = Array.isArray(response.files) ? response.files : [];
                    files.forEach((file) => {
                        if (file && file.url) {
                            this.selection.insertImage(file.url, file.title || null, 900);
                        }
                    });
                }
            },
            image: {
                editSrc: true,
                useImageEditor: false,
                openOnDblClick: true
            },
            link: {
                followOnDblClick: false,
                processVideoLink: true,
                processPastedLink: true
            },
            buttons: [
                'source', '|',
                'paragraph', 'font', 'fontsize', '|',
                'bold', 'italic', 'underline', 'strikethrough', 'eraser', '|',
                'superscript', 'subscript', '|',
                'ul', 'ol', 'outdent', 'indent', '|',
                'left', 'center', 'right', 'justify', '|',
                'link', 'image', 'table', 'hr', 'quote', '|',
                'copyformat', 'brush', '|',
                'undo', 'redo', '|',
                'find', 'selectall', 'preview', 'fullsize'
            ],
            controls: {
                paragraph: {
                    list: {
                        p: 'Paragraf',
                        h2: 'Heading 2',
                        h3: 'Heading 3',
                        h4: 'Heading 4',
                        h5: 'Heading 5',
                        blockquote: 'Quote',
                        pre: 'Code Block'
                    }
                }
            },
            placeholder: 'Tulis artikel di sini...'
        });
        window.articleRichEditor = editor;
        const editorForm = contentTextarea.closest('form');
        editorForm?.addEventListener('submit', () => {
            contentTextarea.value = editor.value;
        });
    } else if (contentTextarea) {
        contentTextarea.classList.add('ckeditor-fallback');
    }

    const selectAll = document.getElementById('select-all-delete');
    const clearAll = document.getElementById('clear-all-delete');
    const deleteSearch = document.getElementById('delete-search');
    const selectedCount = document.getElementById('delete-selected-count');
    const totalCount = document.getElementById('delete-total-count');
    const deleteItems = Array.from(document.querySelectorAll('.admin-delete-item'));

    const updateDeleteCount = () => {
        deleteItems.forEach((item) => {
            const checked = item.querySelector('input[type="checkbox"]')?.checked || false;
            item.classList.toggle('is-selected', checked);
        });
        const selected = deleteItems.filter((item) => item.querySelector('input[type="checkbox"]')?.checked).length;
        const visible = deleteItems.filter((item) => !item.hidden).length;
        if (selectedCount) selectedCount.textContent = String(selected);
        if (totalCount) totalCount.textContent = String(visible);
    };

    deleteItems.forEach((item) => {
        item.querySelector('input[type="checkbox"]')?.addEventListener('change', updateDeleteCount);
    });

    if (selectAll) {
        selectAll.addEventListener('click', () => {
            deleteItems.forEach((item) => {
                if (!item.hidden) {
                    const input = item.querySelector('input[type="checkbox"]');
                    if (input) input.checked = true;
                }
            });
            updateDeleteCount();
        });
    }
    if (clearAll) {
        clearAll.addEventListener('click', () => {
            deleteItems.forEach((item) => {
                const input = item.querySelector('input[type="checkbox"]');
                if (input) input.checked = false;
            });
            updateDeleteCount();
        });
    }
    if (deleteSearch) {
        deleteSearch.addEventListener('input', () => {
            const term = deleteSearch.value.trim().toLowerCase();
            deleteItems.forEach((item) => {
                item.hidden = term !== '' && !(item.dataset.search || '').includes(term);
            });
            updateDeleteCount();
        });
    }
    updateDeleteCount();
})();
</script>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
