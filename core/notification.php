<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| EMAIL NOTIFICATION FOUNDATION - Template
|--------------------------------------------------------------------------
| Lightweight notification layer for order/inquiry/invoice events.
| Default mode is intentionally safe: disabled/log transport can be used
| before connecting SMTP. No database, Redis, or worker required.
|--------------------------------------------------------------------------
*/

if (!function_exists('notification_clean')) {
    function notification_clean(string $value, int $max = 180): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('notification_multiline_clean')) {
    function notification_multiline_clean(string $value, int $max = 4000): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace("/\r\n|\r/", "\n", (string)$value);
        $value = preg_replace('/[ \t]+/', ' ', (string)$value);
        $value = preg_replace('/\n{4,}/', "\n\n\n", (string)$value);
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('notification_bool_env')) {
    function notification_bool_env(string $key, bool $default = false): bool
    {
        $raw = strtolower(trim((string)($_ENV[$key] ?? ($default ? 'true' : 'false'))));
        return !in_array($raw, ['0', 'false', 'off', 'no', ''], true);
    }
}

if (!function_exists('notification_enabled')) {
    function notification_enabled(): bool
    {
        return notification_bool_env('ENABLE_EMAIL_NOTIFICATIONS', false);
    }
}

if (!function_exists('notification_transport')) {
    function notification_transport(): string
    {
        $transport = strtolower(notification_clean((string)($_ENV['EMAIL_TRANSPORT'] ?? 'log'), 20));
        return in_array($transport, ['log', 'mail', 'smtp'], true) ? $transport : 'log';
    }
}


/*
|--------------------------------------------------------------------------
| EMAIL NOTIFICATION RULES & TEMPLATE POLISH - Template
|--------------------------------------------------------------------------
| Lightweight rule toggles and polished plain-text templates. These are
| intentionally stored in .env so the website stays file-based and easy to
| deploy on common hosting environments.
|--------------------------------------------------------------------------
*/

if (!function_exists('notification_rule_defaults')) {
    function notification_rule_defaults(): array
    {
        return [
            'admin_inquiry_created' => true,
            'customer_inquiry_confirmation' => true,
            'admin_order_created' => true,
            'customer_order_confirmation' => true,
            'customer_order_status_link' => true,
            'customer_invoice_link' => true,
            'test_email' => true,
        ];
    }
}

if (!function_exists('notification_rule_env_key')) {
    function notification_rule_env_key(string $rule): string
    {
        $rule = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($rule)) ?: 'RULE');
        return 'EMAIL_RULE_' . $rule;
    }
}

if (!function_exists('notification_rule_enabled')) {
    function notification_rule_enabled(string $rule, ?bool $default = null): bool
    {
        $rule = strtolower(trim($rule));
        $defaults = notification_rule_defaults();
        $default = $default ?? (bool)($defaults[$rule] ?? true);
        $key = notification_rule_env_key($rule);
        if (array_key_exists($key, $_ENV)) {
            return notification_bool_env($key, $default);
        }
        if (($rule === 'customer_order_confirmation' || $rule === 'customer_inquiry_confirmation') && array_key_exists('EMAIL_SEND_CUSTOMER_CONFIRMATION', $_ENV)) {
            return notification_bool_env('EMAIL_SEND_CUSTOMER_CONFIRMATION', $default);
        }
        return $default;
    }
}

if (!function_exists('notification_rule_labels')) {
    function notification_rule_labels(): array
    {
        return [
            'admin_inquiry_created' => 'Email admin saat inquiry baru masuk',
            'customer_inquiry_confirmation' => 'Email konfirmasi customer setelah inquiry',
            'admin_order_created' => 'Email admin saat order baru masuk',
            'customer_order_confirmation' => 'Email konfirmasi customer setelah order',
            'customer_order_status_link' => 'Email link status order ke customer',
            'customer_invoice_link' => 'Email link invoice ke customer',
            'test_email' => 'Email test dari dashboard admin',
        ];
    }
}

if (!function_exists('notification_rules_summary')) {
    function notification_rules_summary(): array
    {
        $items = [];
        foreach (notification_rule_labels() as $rule => $label) {
            $items[$rule] = [
                'label' => $label,
                'enabled' => notification_rule_enabled($rule),
                'env_key' => notification_rule_env_key($rule),
            ];
        }
        return $items;
    }
}

if (!function_exists('notification_log_rule_disabled')) {
    function notification_log_rule_disabled(string $rule, string $to, string $subject, string $type, string $targetType = '', string $targetRef = ''): bool
    {
        return notification_store_event([
            'status' => 'disabled',
            'transport' => notification_transport(),
            'to' => $to,
            'subject' => $subject,
            'type' => $type,
            'target_type' => $targetType,
            'target_ref' => $targetRef,
            'error' => 'Rule nonaktif: ' . notification_rule_env_key($rule),
        ]);
    }
}

if (!function_exists('notification_brand_footer')) {
    function notification_brand_footer(): string
    {
        $footer = notification_multiline_clean((string)($_ENV['EMAIL_TEMPLATE_FOOTER'] ?? ''), 800);
        if ($footer !== '') {
            return $footer;
        }
        return "Email ini dikirim otomatis oleh " . SITE_NAME . ".\nJika ada pertanyaan, silakan balas email ini atau hubungi admin melalui WhatsApp.";
    }
}

if (!function_exists('notification_plain_line')) {
    function notification_plain_line(string $label, string $value): string
    {
        $label = notification_clean($label, 80);
        $value = notification_multiline_clean($value, 700);
        if ($value === '') { $value = '-'; }
        return $label . ': ' . $value;
    }
}

if (!function_exists('notification_render_template')) {
    function notification_render_template(string $title, string $intro, array $fields = [], string $ctaLabel = '', string $ctaUrl = '', array $notes = []): string
    {
        $title = notification_clean($title, 160);
        $intro = notification_multiline_clean($intro, 1200);
        $lines = [];
        $lines[] = $title;
        $lines[] = str_repeat('=', max(16, min(60, strlen($title) ?: 16)));
        $lines[] = '';
        if ($intro !== '') { $lines[] = $intro; $lines[] = ''; }
        foreach ($fields as $label => $value) { $lines[] = notification_plain_line((string)$label, (string)$value); }
        if (!empty($fields)) { $lines[] = ''; }
        $ctaLabel = notification_clean($ctaLabel, 100);
        $ctaUrl = trim((string)$ctaUrl);
        if ($ctaUrl !== '') {
            $lines[] = ($ctaLabel !== '' ? $ctaLabel : 'Buka link') . ':';
            $lines[] = $ctaUrl;
            $lines[] = '';
        }
        foreach ($notes as $note) {
            $note = notification_multiline_clean((string)$note, 900);
            if ($note !== '') { $lines[] = $note; $lines[] = ''; }
        }
        $lines[] = 'Salam,';
        $lines[] = SITE_NAME;
        $lines[] = '';
        $lines[] = '---';
        $lines[] = notification_brand_footer();
        return trim(implode("\n", $lines));
    }
}

if (!function_exists('notification_sample_order')) {
    function notification_sample_order(): array
    {
        return [
            'id' => 'order_demo_' . date('Ymd'),
            'ref' => 'ORD-' . date('Ym') . '-DEMO01',
            'public_token' => 'demo-token-' . date('Ymd'),
            'name' => 'Customer Demo',
            'phone' => '628123456789',
            'email' => notification_admin_email() ?: 'customer@example.com',
            'product_title' => 'Produk Fisik Premium Demo',
            'need' => 'Cek stok dan jadwal produk',
            'location' => 'Area Lokal',
            'payment_method' => 'Konsultasi Dulu',
            'payment_status' => 'Belum Ditagih',
            'status' => 'Baru',
            'quantity' => 1,
            'invoice_number' => 'INV-' . date('Ymd') . '-DEMO01',
            'invoice_total' => '0',
            'invoice_due_date' => date('Y-m-d', strtotime('+3 days')),
            'invoice_payment_channel' => 'Transfer/QRIS manual',
            'invoice_payment_instruction' => 'Admin akan mengirim instruksi pembayaran setelah stok, jadwal, dan nominal dikonfirmasi.',
        ];
    }
}

if (!function_exists('notification_sample_inquiry')) {
    function notification_sample_inquiry(): array
    {
        return [
            'id' => 'inquiry_demo_' . date('Ymd'),
            'name' => 'Customer Demo',
            'phone' => '628123456789',
            'email' => notification_admin_email() ?: 'customer@example.com',
            'need' => 'Konsultasi kebutuhan layanan',
            'location' => 'Bekasi',
            'message' => 'Butuh rekomendasi paket yang cocok untuk acara keluarga.',
        ];
    }
}

if (!function_exists('notification_template_demo')) {
    function notification_template_demo(string $template): array
    {
        $template = notification_clean($template, 80) ?: 'system_test';
        $order = notification_sample_order();
        $inquiry = notification_sample_inquiry();
        if ($template === 'order_customer') { return [notification_order_subject($order), notification_order_customer_body($order), 'test_order_customer']; }
        if ($template === 'order_admin') { return [notification_order_subject($order), notification_order_admin_body($order), 'test_order_admin']; }
        if ($template === 'inquiry_customer') { return [notification_inquiry_subject($inquiry), notification_inquiry_customer_body($inquiry), 'test_inquiry_customer']; }
        if ($template === 'inquiry_admin') { return [notification_inquiry_subject($inquiry), notification_inquiry_admin_body($inquiry), 'test_inquiry_admin']; }
        if ($template === 'order_status_link') { return [notification_order_status_link_subject($order), notification_order_status_link_body($order), 'test_order_status_link']; }
        if ($template === 'invoice_link') { return [notification_invoice_link_subject($order), notification_invoice_link_body($order), 'test_invoice_link']; }
        return [
            'Test Email Notification - ' . SITE_NAME,
            notification_render_template('Test Email Notification', 'Ini adalah test email dari dashboard notification ' . SITE_NAME . '.', ['Transport' => notification_transport(), 'Waktu' => date('d M Y H:i'), 'Status fitur' => notification_enabled() ? 'Aktif' : 'Belum aktif'], 'Buka website', url(), ['Jika EMAIL_TRANSPORT=log, email ini hanya dicatat ke log dan tidak dikirim keluar.']),
            'test_email'
        ];
    }
}

if (!function_exists('notification_admin_email')) {
    function notification_admin_email(): string
    {
        $email = trim((string)($_ENV['EMAIL_ADMIN_TO'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return filter_var(SITE_EMAIL, FILTER_VALIDATE_EMAIL) ? SITE_EMAIL : '';
    }
}

if (!function_exists('notification_from_email')) {
    function notification_from_email(): string
    {
        $email = trim((string)($_ENV['EMAIL_FROM'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return notification_admin_email() ?: 'no-reply@localhost';
    }
}

if (!function_exists('notification_from_name')) {
    function notification_from_name(): string
    {
        return notification_clean((string)($_ENV['EMAIL_FROM_NAME'] ?? SITE_NAME), 100) ?: SITE_NAME;
    }
}

if (!function_exists('notification_reply_to')) {
    function notification_reply_to(): string
    {
        $email = trim((string)($_ENV['EMAIL_REPLY_TO'] ?? ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : notification_from_email();
    }
}

if (!function_exists('notification_log_file')) {
    function notification_log_file(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();
        return LOGS_PATH . '/email-events-' . date('Y-m', $timestamp) . '.jsonl';
    }
}

if (!function_exists('notification_store_event')) {
    function notification_store_event(array $event): bool
    {
        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }
        $event = array_merge([
            'id' => 'mail_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
            'time' => date('c'),
            'transport' => notification_transport(),
            'status' => 'logged',
            'to' => '',
            'subject' => '',
            'type' => '',
            'target_ref' => '',
            'target_type' => '',
            'message' => '',
            'error' => '',
        ], $event);
        $mysqlOk = false;
        $mysqlActive = function_exists('storage_mysql_enabled') && storage_mysql_enabled('email_logs');
        if ($mysqlActive && function_exists('storage_adapter_mysql_append_email_log')) {
            $mysqlOk = storage_adapter_mysql_append_email_log($event);
        }
        $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $fileOk = @file_put_contents(notification_log_file(), $line, FILE_APPEND | LOCK_EX) !== false;
        if ($mysqlActive) {
            return $mysqlOk || (function_exists('storage_adapter_safe_fallback_enabled') && storage_adapter_safe_fallback_enabled() && $fileOk);
        }
        return $fileOk;
    }
}

if (!function_exists('notification_headers')) {
    function notification_headers(): string
    {
        $fromName = str_replace(['"', "\r", "\n"], '', notification_from_name());
        $fromEmail = notification_from_email();
        $replyTo = notification_reply_to();
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $headers[] = 'X-Mailer: ' . SITE_NAME . ' Notification Foundation';
        return implode("\r\n", $headers);
    }
}

if (!function_exists('notification_smtp_read')) {
    function notification_smtp_read($socket): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }
}

if (!function_exists('notification_smtp_expect')) {
    function notification_smtp_expect($socket, array $codes, string $command = ''): array
    {
        if ($command !== '') {
            fwrite($socket, $command . "\r\n");
        }
        $response = notification_smtp_read($socket);
        $code = (int)substr($response, 0, 3);
        return [in_array($code, $codes, true), $response, $code];
    }
}

if (!function_exists('notification_smtp_send')) {
    function notification_smtp_send(string $to, string $subject, string $body): array
    {
        $host = notification_clean((string)($_ENV['EMAIL_SMTP_HOST'] ?? ''), 120);
        $port = (int)($_ENV['EMAIL_SMTP_PORT'] ?? 587);
        $username = (string)($_ENV['EMAIL_SMTP_USERNAME'] ?? '');
        $password = (string)($_ENV['EMAIL_SMTP_PASSWORD'] ?? '');
        $encryption = strtolower(notification_clean((string)($_ENV['EMAIL_SMTP_ENCRYPTION'] ?? 'tls'), 10));
        if ($host === '') {
            return [false, 'EMAIL_SMTP_HOST belum diisi.'];
        }

        $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
        $socket = @stream_socket_client($target . ':' . $port, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            return [false, 'SMTP connect gagal: ' . $errstr . ' (' . $errno . ')'];
        }
        stream_set_timeout($socket, 15);

        [$ok, $response] = notification_smtp_expect($socket, [220]);
        if (!$ok) { fclose($socket); return [false, 'SMTP greeting gagal: ' . trim($response)]; }

        $hostName = app_host();
        [$ok, $response] = notification_smtp_expect($socket, [250], 'EHLO ' . $hostName);
        if (!$ok) { fclose($socket); return [false, 'SMTP EHLO gagal: ' . trim($response)]; }

        if ($encryption === 'tls') {
            [$ok, $response] = notification_smtp_expect($socket, [220], 'STARTTLS');
            if (!$ok) { fclose($socket); return [false, 'SMTP STARTTLS gagal: ' . trim($response)]; }
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return [false, 'SMTP TLS handshake gagal.'];
            }
            [$ok, $response] = notification_smtp_expect($socket, [250], 'EHLO ' . $hostName);
            if (!$ok) { fclose($socket); return [false, 'SMTP EHLO after TLS gagal: ' . trim($response)]; }
        }

        if ($username !== '' || $password !== '') {
            [$ok, $response] = notification_smtp_expect($socket, [334], 'AUTH LOGIN');
            if (!$ok) { fclose($socket); return [false, 'SMTP AUTH gagal: ' . trim($response)]; }
            [$ok, $response] = notification_smtp_expect($socket, [334], base64_encode($username));
            if (!$ok) { fclose($socket); return [false, 'SMTP username gagal: ' . trim($response)]; }
            [$ok, $response] = notification_smtp_expect($socket, [235], base64_encode($password));
            if (!$ok) { fclose($socket); return [false, 'SMTP password gagal: ' . trim($response)]; }
        }

        $from = notification_from_email();
        [$ok, $response] = notification_smtp_expect($socket, [250], 'MAIL FROM:<' . $from . '>');
        if (!$ok) { fclose($socket); return [false, 'SMTP MAIL FROM gagal: ' . trim($response)]; }
        [$ok, $response] = notification_smtp_expect($socket, [250, 251], 'RCPT TO:<' . $to . '>');
        if (!$ok) { fclose($socket); return [false, 'SMTP RCPT TO gagal: ' . trim($response)]; }
        [$ok, $response] = notification_smtp_expect($socket, [354], 'DATA');
        if (!$ok) { fclose($socket); return [false, 'SMTP DATA gagal: ' . trim($response)]; }

        $headers = notification_headers();
        $safeBody = preg_replace('/^\./m', '..', $body);
        $encodedSubject = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($subject, 'UTF-8') : $subject;
        $message = 'Subject: ' . $encodedSubject . "\r\n" . $headers . "\r\n\r\n" . $safeBody . "\r\n.";
        fwrite($socket, $message . "\r\n");
        [$ok, $response] = notification_smtp_expect($socket, [250]);
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return [$ok, $ok ? 'SMTP terkirim.' : 'SMTP send gagal: ' . trim($response)];
    }
}

if (!function_exists('notification_send_email')) {
    function notification_send_email(string $to, string $subject, string $body, array $meta = []): bool
    {
        $to = notification_clean($to, 180);
        $subject = notification_clean($subject, 180);
        $body = notification_multiline_clean($body, 7000);
        $event = array_merge($meta, [
            'transport' => notification_transport(),
            'to' => $to,
            'subject' => $subject,
            'message' => $body,
        ]);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $event['status'] = 'failed';
            $event['error'] = 'Alamat email tidak valid.';
            notification_store_event($event);
            return false;
        }

        if (!notification_enabled()) {
            $event['status'] = 'disabled';
            $event['error'] = 'ENABLE_EMAIL_NOTIFICATIONS=false';
            notification_store_event($event);
            return false;
        }

        $transport = notification_transport();
        if ($transport === 'log') {
            $event['status'] = 'logged';
            $event['error'] = 'EMAIL_TRANSPORT=log, email tidak dikirim keluar.';
            notification_store_event($event);
            return true;
        }

        if ($transport === 'mail') {
            $ok = @mail($to, $subject, $body, notification_headers());
            $event['status'] = $ok ? 'sent' : 'failed';
            $event['error'] = $ok ? '' : 'PHP mail() gagal atau tidak tersedia.';
            notification_store_event($event);
            return $ok;
        }

        [$ok, $info] = notification_smtp_send($to, $subject, $body);
        $event['status'] = $ok ? 'sent' : 'failed';
        $event['error'] = $info;
        notification_store_event($event);
        return $ok;
    }
}

if (!function_exists('notification_order_subject')) {
    function notification_order_subject(array $order): string
    {
        return 'Order diterima: ' . (function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? 'ORDER'));
    }
}

if (!function_exists('notification_order_customer_body')) {
    function notification_order_customer_body(array $order): string
    {
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? 'ORDER');
        if (!empty($order['checkout_customer_message'])) {
            return notification_render_template(
                'Order Anda Sudah Kami Terima',
                (string)$order['checkout_customer_message'],
                [],
                'Buka status order',
                function_exists('order_status_url') ? order_status_url($order) : (function_exists('order_success_url') ? order_success_url($order) : ''),
                ['Simpan nomor order ini untuk memudahkan follow-up dengan admin.']
            );
        }
        $links = [];
        if (function_exists('order_success_url')) { $links['Halaman konfirmasi'] = order_success_url($order); }
        if (function_exists('order_status_url')) { $links['Link status order'] = order_status_url($order); }
        $fields = ['No. Order' => $ref, 'Produk/Layanan' => (string)($order['product_title'] ?? 'Pesanan'), 'Kebutuhan' => (string)($order['need'] ?? '-'), 'Lokasi' => (string)($order['location'] ?? '-'), 'Pengiriman' => (string)($order['shipping_method'] ?? '-'), 'Preferensi Pembayaran' => (string)($order['payment_method'] ?? 'Belum Memilih')];
        return notification_render_template(
            'Order Anda Sudah Kami Terima',
            "Halo " . (string)($order['name'] ?? 'Kak') . ",\n\nTerima kasih. Data pemesanan Anda sudah kami terima. Admin akan menindaklanjuti untuk konfirmasi stok, jadwal, alamat/pengiriman, dan langkah berikutnya.",
            array_merge($fields, $links),
            'Buka status order',
            function_exists('order_status_url') ? order_status_url($order) : (function_exists('order_success_url') ? order_success_url($order) : ''),
            ['Simpan nomor order ini untuk memudahkan follow-up dengan admin.']
        );
    }
}

if (!function_exists('notification_order_admin_body')) {
    function notification_order_admin_body(array $order): string
    {
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? 'ORDER');
        if (!empty($order['checkout_admin_message'])) {
            return notification_render_template(
                'Order Baru Masuk',
                (string)$order['checkout_admin_message'],
                [],
                'Buka Admin Order',
                url('admin/orders?range=all&search=' . rawurlencode($ref)),
                ['Prioritaskan follow-up untuk order baru, terutama jika kebutuhan dan nomor WhatsApp sudah lengkap.']
            );
        }
        $shippingAddress = function_exists('checkout_shipping_address') ? checkout_shipping_address($order) : '';
        $fields = [
            'No. Order' => $ref,
            'Nama' => (string)($order['name'] ?? '-'),
            'WhatsApp' => (string)($order['phone'] ?? '-'),
            'Email' => (string)($order['email'] ?? '-'),
            'Produk/Layanan' => (string)($order['product_title'] ?? '-'),
            'Kebutuhan' => (string)($order['need'] ?? '-'),
            'Lokasi' => (string)($order['location'] ?? '-'),
            'Pengiriman' => (string)($order['shipping_method'] ?? '-'),
            'Alamat' => $shippingAddress !== '-' ? $shippingAddress : '-',
            'Preferensi Pembayaran' => (string)($order['payment_method'] ?? '-'),
            'Catatan' => (string)($order['message'] ?? '-'),
        ];
        return notification_render_template(
            'Order Baru Masuk',
            'Ada order baru yang perlu ditindaklanjuti admin.',
            $fields,
            'Buka Admin Order',
            url('admin/orders?range=all&search=' . rawurlencode($ref)),
            ['Prioritaskan follow-up untuk order baru, terutama jika kebutuhan dan nomor WhatsApp sudah lengkap.']
        );
    }
}

if (!function_exists('notification_inquiry_subject')) {
    function notification_inquiry_subject(array $inquiry): string
    {
        return 'Inquiry baru: ' . notification_clean((string)($inquiry['need'] ?? 'Konsultasi'), 80);
    }
}

if (!function_exists('notification_inquiry_customer_body')) {
    function notification_inquiry_customer_body(array $inquiry): string
    {
        return notification_render_template(
            'Konsultasi Anda Sudah Kami Terima',
            "Halo " . (string)($inquiry['name'] ?? 'Kak') . ",\n\nTerima kasih. Inquiry/kebutuhan Anda sudah kami terima. Admin akan menindaklanjuti melalui kontak yang Anda isi.",
            ['Kebutuhan' => (string)($inquiry['need'] ?? '-'), 'Lokasi' => (string)($inquiry['location'] ?? '-'), 'Catatan' => (string)($inquiry['message'] ?? '-')],
            'Buka website',
            url(),
            ['Jika ada detail tambahan, Anda bisa membalas email ini atau menghubungi admin melalui WhatsApp.']
        );
    }
}

if (!function_exists('notification_inquiry_admin_body')) {
    function notification_inquiry_admin_body(array $inquiry): string
    {
        return notification_render_template(
            'Lead/Form Baru Masuk',
            'Ada lead/form baru yang perlu ditindaklanjuti admin.',
            ['Nama' => (string)($inquiry['name'] ?? '-'), 'WhatsApp' => (string)($inquiry['phone'] ?? '-'), 'Email' => (string)($inquiry['email'] ?? '-'), 'Kebutuhan' => (string)($inquiry['need'] ?? '-'), 'Lokasi' => (string)($inquiry['location'] ?? '-'), 'Catatan' => (string)($inquiry['message'] ?? '-')],
            'Buka Inbox Lead / Form',
            url('admin/inquiries?range=all&search=' . rawurlencode((string)($inquiry['name'] ?? ''))),
            ['Lead dari form biasanya lebih hangat daripada klik WhatsApp biasa karena customer sudah mengisi kebutuhan.']
        );
    }
}

if (!function_exists('notification_send_order_created')) {
    function notification_send_order_created(array $order): void
    {
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? '');
        $admin = notification_admin_email();
        if ($admin !== '') {
            if (notification_rule_enabled('admin_order_created', true)) {
                notification_send_email($admin, notification_order_subject($order), notification_order_admin_body($order), ['type' => 'order_admin', 'target_type' => 'order', 'target_ref' => $ref]);
            } else { notification_log_rule_disabled('admin_order_created', $admin, notification_order_subject($order), 'order_admin', 'order', $ref); }
        }
        $customerEmail = trim((string)($order['email'] ?? ''));
        if (filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            if (notification_rule_enabled('customer_order_confirmation', true)) {
                notification_send_email($customerEmail, notification_order_subject($order), notification_order_customer_body($order), ['type' => 'order_customer', 'target_type' => 'order', 'target_ref' => $ref]);
            } else { notification_log_rule_disabled('customer_order_confirmation', $customerEmail, notification_order_subject($order), 'order_customer', 'order', $ref); }
        }
    }
}

if (!function_exists('notification_send_inquiry_created')) {
    function notification_send_inquiry_created(array $inquiry): void
    {
        $ref = (string)($inquiry['id'] ?? '');
        $admin = notification_admin_email();
        if ($admin !== '') {
            if (notification_rule_enabled('admin_inquiry_created', true)) {
                notification_send_email($admin, notification_inquiry_subject($inquiry), notification_inquiry_admin_body($inquiry), ['type' => 'inquiry_admin', 'target_type' => 'inquiry', 'target_ref' => $ref]);
            } else { notification_log_rule_disabled('admin_inquiry_created', $admin, notification_inquiry_subject($inquiry), 'inquiry_admin', 'inquiry', $ref); }
        }
        $customerEmail = trim((string)($inquiry['email'] ?? ''));
        if (filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            if (notification_rule_enabled('customer_inquiry_confirmation', true)) {
                notification_send_email($customerEmail, notification_inquiry_subject($inquiry), notification_inquiry_customer_body($inquiry), ['type' => 'inquiry_customer', 'target_type' => 'inquiry', 'target_ref' => $ref]);
            } else { notification_log_rule_disabled('customer_inquiry_confirmation', $customerEmail, notification_inquiry_subject($inquiry), 'inquiry_customer', 'inquiry', $ref); }
        }
    }
}

if (!function_exists('notification_log_files')) {
    function notification_log_files(int $days = 30, array $filters = []): array
    {
        if (!is_dir(LOGS_PATH)) {
            return [];
        }
        $files = glob(LOGS_PATH . '/email-events-*.jsonl') ?: [];
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }
        $startMonth = $startTs > 0 ? strtotime(date('Y-m-01 00:00:00', $startTs)) : null;
        $endMonth = $endTs > 0 ? strtotime(date('Y-m-01 00:00:00', $endTs)) : null;
        $files = array_values(array_filter($files, static function (string $file) use ($startMonth, $endMonth): bool {
            if (!preg_match('/email-events-(\d{4})-(\d{2})\.jsonl$/', $file, $matches)) {
                return false;
            }
            $month = strtotime($matches[1] . '-' . $matches[2] . '-01 00:00:00') ?: 0;
            if ($startMonth !== null && $month < $startMonth) { return false; }
            if ($endMonth !== null && $month > $endMonth) { return false; }
            return true;
        }));
        rsort($files, SORT_STRING);
        return $files;
    }
}

if (!function_exists('notification_event_timestamp')) {
    function notification_event_timestamp(array $event): int
    {
        $time = (string)($event['time'] ?? '');
        $timestamp = $time !== '' ? strtotime($time) : false;
        return $timestamp !== false ? (int)$timestamp : 0;
    }
}

if (!function_exists('notification_matches_filters')) {
    function notification_matches_filters(array $event, array $filters = []): bool
    {
        foreach (['status', 'transport', 'type', 'target_type', 'to'] as $key) {
            $filter = strtolower(trim((string)($filters[$key] ?? '')));
            if ($filter === '') { continue; }
            $value = strtolower(trim((string)($event[$key] ?? '')));
            if ($value === '' || !str_contains($value, $filter)) { return false; }
        }
        $search = strtolower(trim((string)($filters['search'] ?? '')));
        if ($search !== '') {
            $haystack = strtolower(implode(' ', array_map('strval', [
                $event['to'] ?? '', $event['subject'] ?? '', $event['type'] ?? '',
                $event['target_ref'] ?? '', $event['status'] ?? '', $event['error'] ?? '',
            ])));
            if (!str_contains($haystack, $search)) { return false; }
        }
        return true;
    }
}

if (!function_exists('notification_read_all')) {
    function notification_read_all(int $days = 30, array $filters = [], int $max = 5000): array
    {
        $max = max(50, min(50000, $max));
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if (!empty($filters['_all_time'])) { $days = 0; }
        if ($days > 0 && $startTs <= 0) { $startTs = time() - (max(1, min(3650, $days)) * 86400); }
        if (function_exists('storage_adapter_mysql_read_email_logs') && function_exists('storage_mysql_enabled') && storage_mysql_enabled('email_logs')) {
            $mysqlEvents = storage_adapter_mysql_read_email_logs($days, $filters, $max);
            if (is_array($mysqlEvents)) {
                return $mysqlEvents;
            }
        }

        $events = [];
        foreach (notification_log_files($days, $filters) as $file) {
            $handle = @fopen($file, 'rb');
            if (!$handle) { continue; }
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') { continue; }
                $event = json_decode($line, true);
                if (!is_array($event)) { continue; }
                $ts = notification_event_timestamp($event);
                if ($ts <= 0) { continue; }
                if ($startTs > 0 && $ts < $startTs) { continue; }
                if ($endTs > 0 && $ts > $endTs) { continue; }
                if (!notification_matches_filters($event, $filters)) { continue; }
                $event['_ts'] = $ts;
                $events[] = $event;
                if (count($events) >= $max) { break 2; }
            }
            fclose($handle);
        }
        usort($events, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));
        return $events;
    }
}

if (!function_exists('notification_count_by')) {
    function notification_count_by(array $events, string $key, int $limit = 8): array
    {
        $counts = [];
        foreach ($events as $event) {
            $value = trim((string)($event[$key] ?? '')) ?: 'Tidak diketahui';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, max(1, $limit), true);
    }
}

if (!function_exists('notification_summary')) {
    function notification_summary(int $days = 30, array $filters = []): array
    {
        $events = notification_read_all($days, $filters, 20000);
        $today = date('Y-m-d');
        $todayCount = 0;
        $sent = 0;
        $failed = 0;
        $logged = 0;
        $disabled = 0;
        foreach ($events as $event) {
            if (date('Y-m-d', (int)($event['_ts'] ?? time())) === $today) { $todayCount++; }
            $status = (string)($event['status'] ?? '');
            if ($status === 'sent') { $sent++; }
            if ($status === 'failed') { $failed++; }
            if ($status === 'logged') { $logged++; }
            if ($status === 'disabled') { $disabled++; }
        }
        return [
            'total' => count($events),
            'today' => $todayCount,
            'sent' => $sent,
            'failed' => $failed,
            'logged' => $logged,
            'disabled' => $disabled,
            'by_status' => notification_count_by($events, 'status', 8),
            'by_type' => notification_count_by($events, 'type', 8),
            'by_transport' => notification_count_by($events, 'transport', 8),
            'recent' => array_slice($events, 0, 80),
        ];
    }
}

/*
|--------------------------------------------------------------------------
| PUBLIC ORDER STATUS / INVOICE EMAIL HELPERS - Template
|--------------------------------------------------------------------------
*/

if (!function_exists('notification_order_status_link_subject')) {
    function notification_order_status_link_subject(array $order): string
    {
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? 'ORDER');
        return 'Link status order: ' . $ref;
    }
}

if (!function_exists('notification_order_status_link_body')) {
    function notification_order_status_link_body(array $order): string
    {
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? 'ORDER');
        return notification_render_template(
            'Link Status Order Anda',
            "Halo " . (string)($order['name'] ?? 'Kak') . ",\n\nBerikut link untuk melihat status order Anda.",
            ['No. Order' => $ref, 'Produk/Layanan' => (string)($order['product_title'] ?? 'Pesanan'), 'Status Order' => function_exists('order_public_status_label') ? order_public_status_label($order) : (string)($order['status'] ?? 'Baru'), 'Status Pembayaran' => function_exists('order_public_payment_status_label') ? order_public_payment_status_label($order) : (string)($order['payment_status'] ?? 'Belum Ditagih')],
            'Buka status order',
            function_exists('order_status_url') ? order_status_url($order) : '',
            ['Link ini bersifat pribadi. Simpan dan gunakan untuk mengecek perkembangan pesanan Anda.']
        );
    }
}

if (!function_exists('notification_invoice_link_subject')) {
    function notification_invoice_link_subject(array $order): string
    {
        return 'Invoice pesanan: ' . (function_exists('order_invoice_number') ? order_invoice_number($order) : 'INV');
    }
}

if (!function_exists('notification_invoice_link_body')) {
    function notification_invoice_link_body(array $order): string
    {
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? 'ORDER');
        $invoice = function_exists('order_invoice_number') ? order_invoice_number($order) : 'INV';
        $total = function_exists('order_invoice_total') ? order_invoice_total($order) : 0;
        $fields = ['No. Invoice' => $invoice, 'No. Order' => $ref, 'Produk/Layanan' => (string)($order['product_title'] ?? 'Pesanan'), 'Status Pembayaran' => function_exists('order_public_payment_status_label') ? order_public_payment_status_label($order) : (string)($order['payment_status'] ?? 'Belum Ditagih'), 'Total Invoice' => $total > 0 ? rupiah($total) : 'Akan dikonfirmasi admin'];
        if (function_exists('order_invoice_payment_instruction')) { $fields['Instruksi'] = order_invoice_payment_instruction($order); }
        return notification_render_template(
            'Invoice / Instruksi Pembayaran',
            "Halo " . (string)($order['name'] ?? 'Kak') . ",\n\nBerikut link invoice/instruksi pembayaran manual untuk pesanan Anda.",
            $fields,
            'Buka invoice',
            function_exists('order_public_invoice_url') ? order_public_invoice_url($order) : '',
            ['Catatan: pembayaran tetap divalidasi manual oleh admin setelah bukti pembayaran diterima.']
        );
    }
}

if (!function_exists('notification_send_order_public_link')) {
    function notification_send_order_public_link(array $order, string $kind = 'status'): bool
    {
        $kind = $kind === 'invoice' ? 'invoice' : 'status';
        $rule = $kind === 'invoice' ? 'customer_invoice_link' : 'customer_order_status_link';
        $type = $kind === 'invoice' ? 'order_invoice_link_customer' : 'order_status_link_customer';
        $subject = $kind === 'invoice' ? notification_invoice_link_subject($order) : notification_order_status_link_subject($order);
        $body = $kind === 'invoice' ? notification_invoice_link_body($order) : notification_order_status_link_body($order);
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? '');
        $customerEmail = trim((string)($order['email'] ?? ''));
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            notification_store_event(['status' => 'failed', 'transport' => notification_transport(), 'to' => $customerEmail, 'subject' => 'Public order link', 'type' => $type, 'target_type' => 'order', 'target_ref' => $ref, 'error' => 'Customer email belum valid atau kosong.']);
            return false;
        }
        if (!notification_rule_enabled($rule, true)) {
            notification_log_rule_disabled($rule, $customerEmail, $subject, $type, 'order', $ref);
            return false;
        }
        return notification_send_email($customerEmail, $subject, $body, ['type' => $type, 'target_type' => 'order', 'target_ref' => $ref]);
    }
}
