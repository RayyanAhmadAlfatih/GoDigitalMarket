<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| Template SEO QUALITY ASSISTANT
|--------------------------------------------------------------------------
| Lightweight, local-only SEO checker for product and article content.
| No external API, no telemetry, shared-hosting safe.
|--------------------------------------------------------------------------
*/


if (!function_exists('seo_quality_lower')) {
    function seo_quality_lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }
}

if (!function_exists('seo_quality_plain_text')) {
    function seo_quality_plain_text(?string $value): string
    {
        $text = html_entity_decode(strip_tags((string)$value), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';
        return trim($text);
    }
}

if (!function_exists('seo_quality_length')) {
    function seo_quality_length(?string $value): int
    {
        $value = seo_quality_plain_text($value);
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}

if (!function_exists('seo_quality_word_count')) {
    function seo_quality_word_count(?string $value): int
    {
        $text = seo_quality_plain_text($value);
        if ($text === '') {
            return 0;
        }

        $parts = preg_split('/\s+/u', $text) ?: [];
        return count(array_filter($parts, static fn(string $part): bool => trim($part) !== ''));
    }
}

if (!function_exists('seo_quality_contains')) {
    function seo_quality_contains(?string $haystack, ?string $needle): bool
    {
        $haystack = seo_quality_lower(seo_quality_plain_text((string)$haystack));
        $needle = seo_quality_lower(seo_quality_plain_text((string)$needle));

        return $needle !== '' && str_contains($haystack, $needle);
    }
}

if (!function_exists('seo_quality_issue')) {
    function seo_quality_issue(string $severity, string $field, string $title, string $message, string $suggestion = '', int $penalty = 0): array
    {
        $allowed = ['error', 'warning', 'info', 'ok'];
        if (!in_array($severity, $allowed, true)) {
            $severity = 'info';
        }

        return [
            'severity' => $severity,
            'field' => $field,
            'title' => $title,
            'message' => $message,
            'suggestion' => $suggestion,
            'penalty' => max(0, $penalty),
        ];
    }
}

if (!function_exists('seo_quality_issue_rank')) {
    function seo_quality_issue_rank(string $severity): int
    {
        return match ($severity) {
            'error' => 4,
            'warning' => 3,
            'info' => 2,
            default => 1,
        };
    }
}

if (!function_exists('seo_quality_grade')) {
    function seo_quality_grade(int $score): string
    {
        if ($score >= 90) {
            return 'A';
        }
        if ($score >= 78) {
            return 'B';
        }
        if ($score >= 65) {
            return 'C';
        }
        if ($score >= 50) {
            return 'D';
        }
        return 'E';
    }
}

if (!function_exists('seo_quality_status')) {
    function seo_quality_status(array $issues, int $score): string
    {
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'error') {
                return 'error';
            }
        }
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'warning') {
                return 'warning';
            }
        }
        return $score >= 78 ? 'ok' : 'info';
    }
}

if (!function_exists('seo_quality_focus_keyword_product')) {
    function seo_quality_focus_keyword_product(array $product): string
    {
        $parts = array_values(array_filter(array_map('trim', [
            (string)($product['breed'] ?? ''),
            (string)($product['tier'] ?? $product['subcategory'] ?? ''),
            (string)($product['location'] ?? ''),
        ])));

        if ($parts) {
            return implode(' ', $parts);
        }

        return trim((string)($product['title'] ?? ''));
    }
}

if (!function_exists('seo_quality_focus_keyword_article')) {
    function seo_quality_focus_keyword_article(array $article): string
    {
        return trim((string)($article['focus_keyword'] ?? ''))
            ?: trim((string)($article['meta_keywords'] ?? ''))
            ?: trim((string)($article['category'] ?? ''));
    }
}

if (!function_exists('seo_quality_audit_product')) {
    function seo_quality_audit_product(array $product): array
    {
        $issues = [];
        $title = trim((string)($product['title'] ?? ''));
        $slug = trim((string)($product['slug'] ?? ''));
        $excerpt = trim((string)($product['excerpt'] ?? ''));
        $description = trim((string)($product['description'] ?? ''));
        $content = trim((string)($product['content'] ?? ''));
        $image = trim((string)($product['image'] ?? ''));
        $imageAlt = trim((string)($product['image_alt'] ?? ''));
        $metaTitle = trim((string)($product['seo']['title'] ?? $product['meta_title'] ?? ''));
        $metaDescription = trim((string)($product['seo']['description'] ?? $product['meta_description'] ?? ''));
        $keywords = (array)($product['seo']['keywords'] ?? []);
        $focusKeyword = seo_quality_focus_keyword_product($product);
        $bodyText = trim($description . ' ' . $content . ' ' . $excerpt);

        $titleLen = seo_quality_length($title);
        $metaTitleLen = seo_quality_length($metaTitle);
        $metaDescriptionLen = seo_quality_length($metaDescription);
        $bodyWords = seo_quality_word_count($bodyText);

        if ($title === '') {
            $issues[] = seo_quality_issue('error', 'title', 'Nama produk kosong', 'Produk wajib punya nama agar bisa tampil dan di-index dengan benar.', 'Isi nama produk memakai jenis produk/layanan, kelas, dan lokasi.', 22);
        } elseif ($titleLen < 18) {
            $issues[] = seo_quality_issue('warning', 'title', 'Judul produk terlalu pendek', 'Judul produk saat ini hanya ' . $titleLen . ' karakter.', 'Tambahkan jenis produk/layanan, kategori, dan area. Contoh: Paket Produk Premium untuk Area Lokal.', 8);
        }

        if ($slug === '') {
            $issues[] = seo_quality_issue('warning', 'slug', 'Slug kosong', 'Slug kosong akan dibuat otomatis, tapi lebih aman dicek sebelum publish.', 'Gunakan slug pendek berisi jenis produk/layanan, tier, dan lokasi.', 6);
        } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $issues[] = seo_quality_issue('warning', 'slug', 'Format slug kurang bersih', 'Slug sebaiknya huruf kecil, angka, dan tanda strip saja.', 'Contoh: produk fisik-bali-medium-bogor.', 5);
        }

        if ($metaTitle === '') {
            $issues[] = seo_quality_issue('warning', 'meta_title', 'Meta title kosong', 'Google akan menebak title dari halaman jika meta title kosong.', 'Isi 45-65 karakter dengan keyword utama dan lokasi.', 12);
        } elseif ($metaTitleLen < 35) {
            $issues[] = seo_quality_issue('warning', 'meta_title', 'Meta title terlalu pendek', 'Meta title saat ini ' . $metaTitleLen . ' karakter.', 'Idealnya 45-65 karakter agar konteks SEO lebih kuat.', 8);
        } elseif ($metaTitleLen > 70) {
            $issues[] = seo_quality_issue('info', 'meta_title', 'Meta title agak panjang', 'Meta title saat ini ' . $metaTitleLen . ' karakter.', 'Ringkas agar bagian penting tidak terpotong di hasil pencarian.', 3);
        }

        if ($metaDescription === '') {
            $issues[] = seo_quality_issue('warning', 'meta_description', 'Meta description kosong', 'Deskripsi pencarian belum disiapkan.', 'Isi 120-160 karakter berisi produk/layanan, manfaat, lokasi, dan CTA ringan.', 12);
        } elseif ($metaDescriptionLen < 90) {
            $issues[] = seo_quality_issue('warning', 'meta_description', 'Meta description terlalu pendek', 'Meta description saat ini ' . $metaDescriptionLen . ' karakter.', 'Tambahkan detail lokasi, stok/kelas, dan ajakan konsultasi.', 8);
        } elseif ($metaDescriptionLen > 170) {
            $issues[] = seo_quality_issue('info', 'meta_description', 'Meta description agak panjang', 'Meta description saat ini ' . $metaDescriptionLen . ' karakter.', 'Usahakan 120-160 karakter agar ringkas.', 3);
        }

        if ($bodyWords < 35) {
            $issues[] = seo_quality_issue('warning', 'description', 'Deskripsi produk masih tipis', 'Konten produk baru sekitar ' . $bodyWords . ' kata.', 'Tambahkan detail produk atau layanan, spesifikasi/umur, area layanan, layanan produk/layanan, dan proses pemesanan.', 10);
        }

        if ($image === '' || str_contains($image, 'placeholder-product.svg')) {
            $issues[] = seo_quality_issue('warning', 'image', 'Gambar utama belum optimal', 'Produk belum memakai foto utama yang spesifik.', 'Upload foto produk atau pilih gambar lama dari Media Library.', 10);
        }

        if ($imageAlt === '') {
            $issues[] = seo_quality_issue('warning', 'image_alt', 'Alt gambar kosong', 'Alt gambar membantu aksesibilitas dan konteks SEO gambar.', 'Isi alt dengan nama produk + lokasi. Contoh: Produk fisik unggulan di Jakarta.', 9);
        } elseif ($title !== '' && !seo_quality_contains($imageAlt, strtok($title, ' ') ?: $title)) {
            $issues[] = seo_quality_issue('info', 'image_alt', 'Alt gambar bisa diperkuat', 'Alt gambar belum terlihat dekat dengan nama produk.', 'Masukkan nama/jenis produk ke alt gambar secara natural.', 2);
        }

        if ($focusKeyword !== '' && $metaTitle !== '' && !seo_quality_contains($metaTitle, explode(' ', $focusKeyword)[0] ?? $focusKeyword)) {
            $issues[] = seo_quality_issue('info', 'meta_title', 'Keyword utama belum kuat di title', 'Meta title belum jelas memuat keyword utama produk.', 'Masukkan jenis produk/layanan/breed utama di meta title secara natural.', 3);
        }

        if (empty(array_filter($keywords))) {
            $issues[] = seo_quality_issue('info', 'meta_keywords', 'Keyword internal kosong', 'Keyword internal tidak wajib untuk Google, tapi berguna untuk audit dan konsistensi konten.', 'Tambahkan 3-6 keyword internal yang relevan.', 2);
        }

        if (trim((string)($product['location'] ?? '')) === '') {
            $issues[] = seo_quality_issue('warning', 'location', 'Lokasi area layanan kosong', 'Landing lokal dan pencarian produk lebih kuat jika lokasi diisi.', 'Pilih area layanan/layanan yang sesuai.', 7);
        }

        usort($issues, static function (array $a, array $b): int {
            return seo_quality_issue_rank((string)($b['severity'] ?? 'info')) <=> seo_quality_issue_rank((string)($a['severity'] ?? 'info'));
        });

        $penalty = array_sum(array_map(static fn(array $issue): int => (int)($issue['penalty'] ?? 0), $issues));
        $score = max(0, min(100, 100 - $penalty));

        return [
            'type' => 'product',
            'id' => (int)($product['id'] ?? 0),
            'title' => $title ?: 'Produk tanpa judul',
            'slug' => $slug,
            'source' => (string)($product['source'] ?? $product['_source'] ?? 'admin'),
            'score' => $score,
            'grade' => seo_quality_grade($score),
            'status' => seo_quality_status($issues, $score),
            'issues' => $issues,
            'issue_count' => count($issues),
            'edit_url' => url('admin/produk?action=edit&id=' . (int)($product['id'] ?? 0)),
            'view_url' => $slug !== '' && function_exists('product_url') ? product_url($slug) : url('katalog'),
            'meta' => [
                'meta_title_length' => $metaTitleLen,
                'meta_description_length' => $metaDescriptionLen,
                'body_words' => $bodyWords,
                'focus_keyword' => $focusKeyword,
            ],
        ];
    }
}

if (!function_exists('seo_quality_audit_article')) {
    function seo_quality_audit_article(array $article): array
    {
        $issues = [];
        $title = trim((string)($article['title'] ?? ''));
        $slug = trim((string)($article['slug'] ?? ''));
        $excerpt = trim((string)($article['excerpt'] ?? ''));
        $content = trim((string)($article['content'] ?? ''));
        $image = trim((string)($article['image'] ?? ''));
        $imageAlt = trim((string)($article['image_alt'] ?? ''));
        $metaTitle = trim((string)($article['meta_title'] ?? ''));
        $metaDescription = trim((string)($article['meta_description'] ?? $excerpt));
        $focusKeyword = seo_quality_focus_keyword_article($article);
        $bodyWords = seo_quality_word_count($content);
        $titleLen = seo_quality_length($title);
        $metaTitleLen = seo_quality_length($metaTitle);
        $metaDescriptionLen = seo_quality_length($metaDescription);

        if ($title === '') {
            $issues[] = seo_quality_issue('error', 'title', 'Judul artikel kosong', 'Artikel tidak boleh dipublish tanpa judul.', 'Isi judul yang menjelaskan topik, layanan, dan lokasi bila relevan.', 22);
        } elseif ($titleLen < 30) {
            $issues[] = seo_quality_issue('warning', 'title', 'Judul artikel kurang SEO', 'Judul artikel saat ini ' . $titleLen . ' karakter.', 'Tambahkan topik, manfaat, dan area. Contoh: Panduan Memilih Layanan yang Tepat untuk Keluarga.', 9);
        } elseif ($titleLen > 85) {
            $issues[] = seo_quality_issue('info', 'title', 'Judul artikel agak panjang', 'Judul artikel saat ini ' . $titleLen . ' karakter.', 'Ringkas supaya mudah dibaca di SERP dan share preview.', 3);
        }

        if ($slug === '') {
            $issues[] = seo_quality_issue('warning', 'slug', 'Slug kosong', 'Slug akan dibuat otomatis, tapi sebaiknya tetap dicek.', 'Gunakan slug pendek dengan keyword utama.', 6);
        }

        if ($focusKeyword === '') {
            $issues[] = seo_quality_issue('warning', 'focus_keyword', 'Focus keyword kosong', 'Admin sulit mengecek arah konten jika focus keyword kosong.', 'Isi keyword utama seperti “produk fisik bogor” atau “paket layanan paket”.', 8);
        } else {
            if ($title !== '' && !seo_quality_contains($title, $focusKeyword)) {
                $issues[] = seo_quality_issue('info', 'title', 'Focus keyword belum muncul di judul', 'Judul belum memuat focus keyword secara utuh.', 'Masukkan focus keyword secara natural, tidak perlu dipaksa.', 3);
            }
            if ($metaDescription !== '' && !seo_quality_contains($metaDescription, $focusKeyword)) {
                $issues[] = seo_quality_issue('info', 'meta_description', 'Focus keyword belum muncul di meta description', 'Meta description belum memuat focus keyword.', 'Tambahkan focus keyword sekali secara natural.', 3);
            }
        }

        if ($metaTitle === '') {
            $issues[] = seo_quality_issue('warning', 'meta_title', 'Meta title kosong', 'Artikel belum punya title SEO khusus.', 'Isi 45-65 karakter dengan focus keyword dan manfaat.', 12);
        } elseif ($metaTitleLen < 35) {
            $issues[] = seo_quality_issue('warning', 'meta_title', 'Meta title terlalu pendek', 'Meta title saat ini ' . $metaTitleLen . ' karakter.', 'Tambahkan konteks layanan/lokasi agar title lebih kuat.', 8);
        } elseif ($metaTitleLen > 70) {
            $issues[] = seo_quality_issue('info', 'meta_title', 'Meta title agak panjang', 'Meta title saat ini ' . $metaTitleLen . ' karakter.', 'Ringkas ke 45-65 karakter.', 3);
        }

        if ($metaDescription === '') {
            $issues[] = seo_quality_issue('warning', 'meta_description', 'Meta description kosong', 'Snippet artikel belum disiapkan.', 'Isi 120-160 karakter dengan ringkasan dan CTA ringan.', 12);
        } elseif ($metaDescriptionLen < 90) {
            $issues[] = seo_quality_issue('warning', 'meta_description', 'Meta description terlalu pendek', 'Meta description saat ini ' . $metaDescriptionLen . ' karakter.', 'Tambahkan manfaat, lokasi, dan layanan yang dibahas.', 8);
        } elseif ($metaDescriptionLen > 170) {
            $issues[] = seo_quality_issue('info', 'meta_description', 'Meta description agak panjang', 'Meta description saat ini ' . $metaDescriptionLen . ' karakter.', 'Ringkas ke 120-160 karakter.', 3);
        }

        if ($bodyWords < 250) {
            $issues[] = seo_quality_issue('warning', 'content', 'Konten artikel masih tipis', 'Isi artikel baru sekitar ' . $bodyWords . ' kata.', 'Tambahkan penjelasan, FAQ, lokasi layanan, internal link, dan CTA konsultasi.', 12);
        }

        if (!preg_match('/<h2\b|<h3\b/i', $content)) {
            $issues[] = seo_quality_issue('info', 'content', 'Heading belum terlihat', 'Artikel belum memakai H2/H3 yang jelas.', 'Tambahkan subjudul H2/H3 untuk struktur konten.', 4);
        }

        if (!preg_match('/href\s*=\s*["\']/i', $content)) {
            $issues[] = seo_quality_issue('info', 'content', 'Internal link belum ada', 'Artikel belum terlihat punya link internal.', 'Tambahkan link ke katalog, produk, lokasi, atau artikel terkait.', 4);
        }

        if ($image === '' || str_contains($image, 'placeholder-product.svg')) {
            $issues[] = seo_quality_issue('warning', 'image', 'Gambar artikel belum optimal', 'Artikel belum memakai gambar utama yang spesifik.', 'Upload gambar atau pilih gambar lama dari Media Library.', 10);
        }

        if ($imageAlt === '') {
            $issues[] = seo_quality_issue('warning', 'image_alt', 'Alt gambar kosong', 'Alt gambar membantu aksesibilitas dan SEO gambar.', 'Isi alt dengan topik artikel dan konteks layanan/lokasi.', 9);
        }

        if (trim((string)($article['faq_json'] ?? '')) === '') {
            $issues[] = seo_quality_issue('info', 'faq_json', 'FAQ schema belum diisi', 'Tidak wajib, tapi FAQ membantu konten long-tail.', 'Tambahkan 2-4 FAQ jika artikel membahas pertanyaan calon pembeli.', 2);
        }

        usort($issues, static function (array $a, array $b): int {
            return seo_quality_issue_rank((string)($b['severity'] ?? 'info')) <=> seo_quality_issue_rank((string)($a['severity'] ?? 'info'));
        });

        $penalty = array_sum(array_map(static fn(array $issue): int => (int)($issue['penalty'] ?? 0), $issues));
        $score = max(0, min(100, 100 - $penalty));

        return [
            'type' => 'article',
            'id' => (int)($article['id'] ?? 0),
            'title' => $title ?: 'Artikel tanpa judul',
            'slug' => $slug,
            'source' => (string)($article['source'] ?? $article['_source'] ?? 'admin'),
            'score' => $score,
            'grade' => seo_quality_grade($score),
            'status' => seo_quality_status($issues, $score),
            'issues' => $issues,
            'issue_count' => count($issues),
            'edit_url' => url('admin/artikel?action=edit&id=' . (int)($article['id'] ?? 0)),
            'view_url' => $slug !== '' && function_exists('article_url') ? article_url($slug) : url('artikel'),
            'meta' => [
                'meta_title_length' => $metaTitleLen,
                'meta_description_length' => $metaDescriptionLen,
                'body_words' => $bodyWords,
                'focus_keyword' => $focusKeyword,
            ],
        ];
    }
}

if (!function_exists('seo_quality_items')) {
    function seo_quality_items(string $type = 'all'): array
    {
        $items = [];

        if ($type === 'all' || $type === 'products') {
            foreach (function_exists('all_products') ? all_products() : [] as $product) {
                $items[] = seo_quality_audit_product($product);
            }
        }

        if ($type === 'all' || $type === 'articles') {
            foreach (function_exists('all_articles') ? all_articles() : [] as $article) {
                $items[] = seo_quality_audit_article($article);
            }
        }

        usort($items, static function (array $a, array $b): int {
            $statusRank = seo_quality_issue_rank((string)($b['status'] ?? 'info')) <=> seo_quality_issue_rank((string)($a['status'] ?? 'info'));
            if ($statusRank !== 0) {
                return $statusRank;
            }
            return ((int)($a['score'] ?? 0)) <=> ((int)($b['score'] ?? 0));
        });

        return $items;
    }
}

if (!function_exists('seo_quality_summary')) {
    function seo_quality_summary(string $type = 'all'): array
    {
        $items = seo_quality_items($type);
        $counts = [
            'items' => count($items),
            'ok' => 0,
            'info' => 0,
            'warning' => 0,
            'error' => 0,
            'issues' => 0,
            'products' => 0,
            'articles' => 0,
        ];

        $scoreTotal = 0;
        foreach ($items as $item) {
            $status = (string)($item['status'] ?? 'info');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $counts['issues'] += (int)($item['issue_count'] ?? 0);
            $counts[((string)($item['type'] ?? '') === 'product') ? 'products' : 'articles']++;
            $scoreTotal += (int)($item['score'] ?? 0);
        }

        $average = $items ? (int)round($scoreTotal / count($items)) : 100;

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'type' => $type,
            'score_average' => $average,
            'grade_average' => seo_quality_grade($average),
            'counts' => $counts,
            'items' => $items,
        ];
    }
}

if (!function_exists('seo_quality_issue_label')) {
    function seo_quality_issue_label(string $severity): string
    {
        return match ($severity) {
            'error' => 'Error',
            'warning' => 'Warning',
            'ok' => 'OK',
            default => 'Info',
        };
    }
}

if (!function_exists('seo_quality_issue_class')) {
    function seo_quality_issue_class(string $severity): string
    {
        return 'admin-status-pill admin-status-pill--' . (in_array($severity, ['ok','warning','error','info'], true) ? $severity : 'info');
    }
}

if (!function_exists('seo_quality_render_inline_assistant')) {
    function seo_quality_render_inline_assistant(string $type, array $item): void
    {
        $audit = $type === 'article' ? seo_quality_audit_article($item) : seo_quality_audit_product($item);
        $issues = array_slice((array)($audit['issues'] ?? []), 0, 6);
        ?>
        <div class="admin-panel admin-seo-assistant-card" data-seo-quality-assistant>
            <div class="admin-seo-assistant-head">
                <div>
                    <h3>Cek SEO</h3>
                    <p>Cek otomatis field penting sebelum disimpan.</p>
                </div>
                <strong class="admin-seo-score admin-seo-score--<?= esc((string)($audit['status'] ?? 'info')); ?>"><?= (int)($audit['score'] ?? 0); ?><span>/100</span></strong>
            </div>
            <?php if (!$issues): ?>
                <div class="admin-alert admin-alert--success">Mantap, tidak ada warning besar di data saat ini.</div>
            <?php else: ?>
                <div class="admin-seo-issue-list">
                    <?php foreach ($issues as $issue): ?>
                        <div class="admin-seo-issue admin-seo-issue--<?= esc((string)$issue['severity']); ?>">
                            <span class="<?= esc(seo_quality_issue_class((string)$issue['severity'])); ?>"><?= esc(seo_quality_issue_label((string)$issue['severity'])); ?></span>
                            <strong><?= esc((string)$issue['title']); ?></strong>
                            <p><?= esc((string)$issue['message']); ?></p>
                            <?php if (!empty($issue['suggestion'])): ?><em><?= esc((string)$issue['suggestion']); ?></em><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="admin-seo-live-hints" data-seo-live-hints aria-live="polite"></div>
            <a class="admin-btn admin-btn--soft admin-btn--full" href="<?= url('admin/seo-quality'); ?>">Buka Dashboard SEO Quality</a>
        </div>
        <?php
    }
}
