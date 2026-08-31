<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MEMBER DASHBOARD POLISH
|--------------------------------------------------------------------------
| Lightweight view-model helpers for the buyer/member dashboard. Keeps
| page templates readable and makes member UX checks auditable.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('member_dashboard_record_active')) {
    function member_dashboard_record_active(array $record): bool
    {
        $status = strtolower((string)($record['status'] ?? 'active'));
        $expired = function_exists('member_access_record_is_expired') ? member_access_record_is_expired($record) : false;
        return $status === 'active' && !$expired;
    }
}

if (!function_exists('member_dashboard_summary')) {
    function member_dashboard_summary(array $records): array
    {
        $active = 0;
        $expired = 0;
        $course = 0;
        $license = 0;
        $download = 0;
        $nextExpiryTs = null;

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            if (member_dashboard_record_active($record)) {
                $active++;
            } else {
                $expired++;
            }
            if (!empty($record['course_modules']) && is_array($record['course_modules'])) {
                $course++;
            }
            $licenseData = is_array($record['license'] ?? null) ? $record['license'] : [];
            if (!empty($licenseData['enabled'])) {
                $license++;
            }
            if (!empty($record['digital_file_url']) || !empty($record['digital_delivery_url']) || !empty($record['digital_access_url'])) {
                $download++;
            }
            $expiryRaw = trim((string)($record['expires_at'] ?? ''));
            if ($expiryRaw !== '') {
                $ts = strtotime($expiryRaw);
                if ($ts && $ts > time() && ($nextExpiryTs === null || $ts < $nextExpiryTs)) {
                    $nextExpiryTs = $ts;
                }
            }
        }

        return [
            'total' => count(array_filter($records, 'is_array')),
            'active' => $active,
            'expired' => $expired,
            'course' => $course,
            'license' => $license,
            'download' => $download,
            'next_expiry' => $nextExpiryTs ? date('d M Y', $nextExpiryTs) : 'Tidak dibatasi',
        ];
    }
}

if (!function_exists('member_dashboard_next_action')) {
    function member_dashboard_next_action(array $records, ?array $buyer): array
    {
        if (!$records) {
            return [
                'tone' => 'info',
                'title' => 'Belum ada akses aktif',
                'message' => 'Gunakan link dari invoice/status order atau minta magic link dengan email pembelian.',
                'href' => url('order-status'),
                'label' => 'Cek Status Order',
            ];
        }
        if ($buyer && trim((string)($buyer['password_hash'] ?? '')) === '') {
            return [
                'tone' => 'warning',
                'title' => 'Amankan akun member',
                'message' => 'Buat password agar pembeli bisa login ulang tanpa mencari link invoice.',
                'href' => '#profil-member',
                'label' => 'Buat Password',
            ];
        }
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $expiryRaw = trim((string)($record['expires_at'] ?? ''));
            $ts = $expiryRaw !== '' ? strtotime($expiryRaw) : false;
            if ($ts && $ts > time() && $ts <= strtotime('+14 days')) {
                return [
                    'tone' => 'warning',
                    'title' => 'Akses segera berakhir',
                    'message' => (string)($record['product_title'] ?? 'Produk') . ' aktif sampai ' . date('d M Y', $ts) . '.',
                    'href' => '#riwayat-pembelian',
                    'label' => 'Lihat Riwayat',
                ];
            }
        }
        return [
            'tone' => 'success',
            'title' => 'Akses buyer aktif',
            'message' => 'Semua produk aktif bisa dibuka dari menu Akses Produk. Simpan akun ini agar mudah login ulang.',
            'href' => '#akses-produk',
            'label' => 'Buka Akses Produk',
        ];
    }
}

if (!function_exists('member_dashboard_support_url')) {
    function member_dashboard_support_url(?array $buyer, array $records = []): string
    {
        $name = (string)($buyer['name'] ?? ($records[0]['customer_name'] ?? ''));
        $email = (string)($buyer['email'] ?? ($records[0]['customer_email'] ?? ''));
        $orderRef = (string)($records[0]['order_ref'] ?? '');
        $message = 'Halo Admin, saya butuh bantuan akses member.';
        if ($name !== '') {
            $message .= "\nNama: " . $name;
        }
        if ($email !== '') {
            $message .= "\nEmail: " . $email;
        }
        if ($orderRef !== '') {
            $message .= "\nOrder: " . $orderRef;
        }
        return function_exists('wa_link') ? wa_link($message) : '#';
    }
}
