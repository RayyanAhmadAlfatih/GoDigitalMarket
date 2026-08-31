<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| ROUTER ENGINE
|--------------------------------------------------------------------------
| Production-grade routing helper
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| GET CURRENT URI
|--------------------------------------------------------------------------
*/

if (!function_exists('current_uri')) {

    function current_uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $uri = parse_url(
            $uri,
            PHP_URL_PATH
        );

        $basePath = parse_url(
            SITE_URL,
            PHP_URL_PATH
        );

        if (
            !empty($basePath) &&
            $basePath !== '/'
        ) {

            $uri = str_replace(
                $basePath,
                '',
                $uri
            );
        }

        return '/' . trim((string) $uri, '/');
    }
}

/*
|--------------------------------------------------------------------------
| GET CURRENT PATH
|--------------------------------------------------------------------------
*/

if (!function_exists('current_path')) {

    function current_path(): string
    {
        return trim(
            current_uri(),
            '/'
        );
    }
}

/*
|--------------------------------------------------------------------------
| HOME CHECK
|--------------------------------------------------------------------------
*/

if (!function_exists('is_home')) {

    function is_home(): bool
    {
        return current_uri() === '/';
    }
}

/*
|--------------------------------------------------------------------------
| CURRENT SEGMENTS
|--------------------------------------------------------------------------
*/

if (!function_exists('uri_segments')) {

    function uri_segments(): array
    {
        $path = current_path();

        if (empty($path)) {
            return [];
        }

        return explode('/', $path);
    }
}

/*
|--------------------------------------------------------------------------
| URI SEGMENT
|--------------------------------------------------------------------------
*/

if (!function_exists('segment')) {

    function segment(
        int $index,
        ?string $default = null
    ): ?string {

        $segments = uri_segments();

        return $segments[$index - 1]
            ?? $default;
    }
}

/*
|--------------------------------------------------------------------------
| ROUTE MATCH
|--------------------------------------------------------------------------
*/

if (!function_exists('route_is')) {

    function route_is(
        string $path
    ): bool {

        return trim(
            current_path(),
            '/'
        ) === trim($path, '/');
    }
}

/*
|--------------------------------------------------------------------------
| ROUTE STARTS WITH
|--------------------------------------------------------------------------
*/

if (!function_exists('route_starts_with')) {

    function route_starts_with(
        string $path
    ): bool {

        return str_starts_with(
            current_path(),
            trim($path, '/')
        );
    }
}

/*
|--------------------------------------------------------------------------
| ACTIVE MENU
|--------------------------------------------------------------------------
*/

if (!function_exists('active_menu')) {

    function active_menu(
        string $path,
        string $class = 'active'
    ): string {

        return route_is($path)
            ? $class
            : '';
    }
}

/*
|--------------------------------------------------------------------------
| ACTIVE PARTIAL MENU
|--------------------------------------------------------------------------
*/

if (!function_exists('active_partial')) {

    function active_partial(
        string $path,
        string $class = 'active'
    ): string {

        return route_starts_with($path)
            ? $class
            : '';
    }
}

/*
|--------------------------------------------------------------------------
| URL GENERATOR
|--------------------------------------------------------------------------
*/

if (!function_exists('url')) {

    function url(
        string $path = ''
    ): string {

        return rtrim(
            SITE_URL,
            '/'
        ) . '/' . ltrim($path, '/');
    }
}

/*
|--------------------------------------------------------------------------
| ARTICLE URL
|--------------------------------------------------------------------------
*/

if (!function_exists('article_url')) {

    function article_url(
        string $slug
    ): string {

        return url(
            'artikel/' . slugify($slug)
        );
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCT URL
|--------------------------------------------------------------------------
*/

if (!function_exists('product_url')) {

    function product_url(
        string $slug
    ): string {

        return url(
            'produk/' . slugify($slug)
        );
    }
}

/*
|--------------------------------------------------------------------------
| CATEGORY URL
|--------------------------------------------------------------------------
*/

if (!function_exists('category_url')) {

    function category_url(
        string $slug
    ): string {

        return url(
            'kategori/' . slugify($slug)
        );
    }
}

/*
|--------------------------------------------------------------------------
| PAGINATION URL
|--------------------------------------------------------------------------
*/

if (!function_exists('pagination_url')) {

    function pagination_url(
        int $page
    ): string {

        $query = $_GET;

        if ($page <= 1) {
            unset($query['page']);
        } else {
            $query['page'] = $page;
        }

        $base = strtok(
            current_url(),
            '?'
        );

        $queryString = http_build_query($query);

        return $queryString === '' ? $base : $base . '?' . $queryString;
    }
}

/*
|--------------------------------------------------------------------------
| CURRENT PAGE
|--------------------------------------------------------------------------
*/

if (!function_exists('current_page')) {

    function current_page(): int
    {
        return max(
            1,
            (int) ($_GET['page'] ?? 1)
        );
    }
}

/*
|--------------------------------------------------------------------------
| OFFSET PAGINATION
|--------------------------------------------------------------------------
*/

if (!function_exists('pagination_offset')) {

    function pagination_offset(
        int $perPage
    ): int {

        return (
            current_page() - 1
        ) * $perPage;
    }
}

/*
|--------------------------------------------------------------------------
| TOTAL PAGE
|--------------------------------------------------------------------------
*/

if (!function_exists('total_pages')) {

    function total_pages(
        int $totalItems,
        int $perPage
    ): int {

        return (int) ceil(
            $totalItems / $perPage
        );
    }
}

/*
|--------------------------------------------------------------------------
| PAGINATION REL LINKS
|--------------------------------------------------------------------------
*/

if (!function_exists('pagination_rel_links')) {

    function pagination_rel_links(
        int $totalPages
    ): void {

        $page = current_page();

        if ($page > 1) {

            echo '<link rel="prev" href="' .
                esc(pagination_url($page - 1)) .
                '">' . PHP_EOL;
        }

        if ($page < $totalPages) {

            echo '<link rel="next" href="' .
                esc(pagination_url($page + 1)) .
                '">' . PHP_EOL;
        }
    }
}



/*
|--------------------------------------------------------------------------
| COMPACT PAGINATION HTML
|--------------------------------------------------------------------------
| Render pagination ringkas untuk halaman artikel/katalog agar tidak menampilkan
| semua nomor halaman ketika jumlah halaman sangat banyak.
*/

if (!function_exists('compact_pagination_items')) {

    function compact_pagination_items(int $currentPage, int $totalPages): array
    {
        $items = [1];

        for ($i = $currentPage - 1; $i <= $currentPage + 1; $i++) {
            if ($i > 1 && $i < $totalPages) {
                $items[] = $i;
            }
        }

        if ($totalPages > 1) {
            $items[] = $totalPages;
        }

        $items = array_values(array_unique($items));
        sort($items);

        $result = [];
        $previous = 0;

        foreach ($items as $item) {
            if ($previous > 0 && $item > $previous + 1) {
                $result[] = 'dots';
            }

            $result[] = $item;
            $previous = $item;
        }

        return $result;
    }
}

if (!function_exists('render_compact_pagination')) {

    function render_compact_pagination(int $currentPage, int $totalPages): void
    {
        if ($totalPages <= 1) {
            return;
        }

        echo '<nav class="pagination pagination-compact pagination-clean" aria-label="Navigasi halaman">';

        if ($currentPage > 1) {
            echo '<a class="page-control page-prev" href="' . esc(pagination_url($currentPage - 1)) . '" rel="prev" aria-label="Halaman sebelumnya">&laquo;</a>';
        } else {
            echo '<span class="page-control disabled" aria-disabled="true">&laquo;</span>';
        }

        foreach (compact_pagination_items($currentPage, $totalPages) as $item) {
            if ($item === 'dots') {
                echo '<span class="page-dots" aria-hidden="true">…</span>';
                continue;
            }

            $active = ((int) $item === $currentPage) ? ' active' : '';
            $aria = ((int) $item === $currentPage) ? ' aria-current="page"' : '';
            echo '<a class="page-number' . $active . '" href="' . esc(pagination_url((int) $item)) . '"' . $aria . '>' . (int) $item . '</a>';
        }

        if ($currentPage < $totalPages) {
            echo '<a class="page-control page-next" href="' . esc(pagination_url($currentPage + 1)) . '" rel="next" aria-label="Halaman berikutnya">&raquo;</a>';
            echo '<a class="page-last" href="' . esc(pagination_url($totalPages)) . '" aria-label="Halaman terakhir">Last</a>';
        } else {
            echo '<span class="page-control disabled" aria-disabled="true">&raquo;</span>';
            echo '<span class="page-last disabled" aria-disabled="true">Last</span>';
        }

        echo '</nav>';
    }
}

/*
|--------------------------------------------------------------------------
| SAFE 404
|--------------------------------------------------------------------------
*/

if (!function_exists('abort_404')) {

    function abort_404(): never
    {
        http_response_code(404);

        require PAGES_PATH . '/404.php';

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| SAFE 301 REDIRECT
|--------------------------------------------------------------------------
*/

if (!function_exists('redirect_301')) {

    function redirect_301(
        string $to
    ): never {

        header(
            'HTTP/1.1 301 Moved Permanently'
        );

        header(
            'Location: ' . url($to)
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| SAFE 302 REDIRECT
|--------------------------------------------------------------------------
*/

if (!function_exists('redirect_302')) {

    function redirect_302(
        string $to
    ): never {

        header(
            'Location: ' . url($to)
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| CANONICAL PAGINATION
|--------------------------------------------------------------------------
*/

if (!function_exists('pagination_canonical')) {

    function pagination_canonical(): string
    {
        $page = current_page();

        if ($page <= 1) {
            return canonical_url();
        }

        return strtok(
            current_url(),
            '?'
        ) . '?page=' . $page;
    }
}

/*
|--------------------------------------------------------------------------
| CLEAN QUERY STRING
|--------------------------------------------------------------------------
*/

if (!function_exists('clean_query_url')) {

    function clean_query_url(): string
    {
        return strtok(
            current_url(),
            '?'
        );
    }
}

/*
|--------------------------------------------------------------------------
| BREADCRUMB GENERATOR
|--------------------------------------------------------------------------
*/

if (!function_exists('generate_breadcrumbs')) {

    function generate_breadcrumbs(): array
    {
        $segments = uri_segments();

        $breadcrumbs = [];

        $breadcrumbs[] = [
            'name' => 'Home',
            'url' => SITE_URL,
        ];

        $path = '';

        foreach ($segments as $segment) {

            $path .= '/' . $segment;

            $breadcrumbs[] = [

                'name' => ucwords(
                    str_replace(
                        '-',
                        ' ',
                        $segment
                    )
                ),

                'url' => SITE_URL . $path,
            ];
        }

        return $breadcrumbs;
    }
}

/*
|--------------------------------------------------------------------------
| REQUEST QUERY
|--------------------------------------------------------------------------
*/

if (!function_exists('query')) {

    function query(
        string $key,
        mixed $default = null
    ): mixed {

        return $_GET[$key]
            ?? $default;
    }
}

/*
|--------------------------------------------------------------------------
| REQUEST INPUT
|--------------------------------------------------------------------------
*/

if (!function_exists('input')) {

    function input(
        string $key,
        mixed $default = null
    ): mixed {

        return $_POST[$key]
            ?? $default;
    }
}

