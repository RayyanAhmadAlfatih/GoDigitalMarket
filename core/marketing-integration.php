<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| MAILKETING + FONNTE INTEGRATION FOUNDATION - Template
|--------------------------------------------------------------------------
| Integrasi marketing untuk website UMKM
| custom PHP SEO marketplace. Integrasi ini bersifat opt-in, consent-aware,
| dan tidak mengirim data pribadi ke GA/GTM/platform ads.
|--------------------------------------------------------------------------
*/

if (!function_exists('marketing_integration_settings_file')) {
    function marketing_integration_settings_file(): string
    {
        return STORAGE_PATH . '/marketing-integrations.json';
    }
}

if (!function_exists('marketing_integration_log_file')) {
    function marketing_integration_log_file(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();
        return LOGS_PATH . '/marketing-integrations-' . date('Y-m', $timestamp) . '.jsonl';
    }
}

if (!function_exists('marketing_integration_log_files')) {
    function marketing_integration_log_files(int $days = 3650): array
    {
        $files = glob(LOGS_PATH . '/marketing-integrations-*.jsonl') ?: [];
        $cutoff = time() - max(1, $days) * 86400;
        $files = array_values(array_filter($files, static function (string $file) use ($cutoff): bool {
            return is_file($file) && (int)@filemtime($file) >= $cutoff;
        }));
        rsort($files, SORT_STRING);
        return $files;
    }
}

if (!function_exists('marketing_integration_clean')) {
    function marketing_integration_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('marketing_integration_multiline_clean')) {
    function marketing_integration_multiline_clean(string $value, int $max = 1200): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace("/\r\n|\r/", "\n", (string)$value);
        $value = preg_replace('/[ \t]+/', ' ', (string)$value);
        $value = preg_replace('/\n{3,}/', "\n\n", (string)$value);
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('marketing_integration_secret_clean')) {
    function marketing_integration_secret_clean(string $value, int $max = 300): string
    {
        $value = trim($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', (string)$value) ?: '';
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('marketing_integration_mask_secret')) {
    function marketing_integration_mask_secret(string $secret): string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return '';
        }
        $last = function_exists('mb_substr') ? mb_substr($secret, -4) : substr($secret, -4);
        return '••••••••' . $last;
    }
}

if (!function_exists('marketing_integration_mask_email')) {
    function marketing_integration_mask_email(string $email): string
    {
        $email = trim($email);
        if ($email === '' || !str_contains($email, '@')) {
            return '';
        }
        [$name, $domain] = explode('@', $email, 2);
        $prefix = function_exists('mb_substr') ? mb_substr($name, 0, 2) : substr($name, 0, 2);
        return $prefix . '***@' . $domain;
    }
}

if (!function_exists('marketing_integration_mask_phone')) {
    function marketing_integration_mask_phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return '';
        }
        $last = substr($digits, -4);
        return '****' . $last;
    }
}

if (!function_exists('marketing_integration_phone_for_fonnte')) {
    function marketing_integration_phone_for_fonnte(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }
        return $digits;
    }
}

if (!function_exists('marketing_integration_default_settings')) {
    function marketing_integration_default_settings(): array
    {
        return [
            'schema' => 'marketing-integration',
            'updated_at' => '',
            'enabled' => false,
            'require_contact_consent' => true,
            'mailketing' => [
                'enabled' => false,
                'api_token' => '',
                'default_list_id' => '',
                'inquiry_list_id' => '',
                'order_list_id' => '',
                'buyer_list_id' => '',
                'sync_inquiry' => true,
                'sync_order' => true,
                'sync_buyer' => true,
            ],
            'fonnte' => [
                'enabled' => false,
                'token' => '',
                'country_code' => '62',
                'send_inquiry_message' => true,
                'send_order_message' => true,
                'inquiry_template' => 'Halo {name}, terima kasih sudah menghubungi {site_name}. Inquiry Anda sudah kami terima. Tim kami akan segera follow-up melalui WhatsApp ini.',
                'order_template' => 'Halo {name}, terima kasih. Order {order_ref} untuk {product_title} sudah kami terima. Admin akan mengonfirmasi stok, jadwal, dan pembayaran melalui WhatsApp ini.',
            ],
            'form_messages' => [
                'whatsapp_admin_template' => "Form baru masuk dari {form_name}\n\nNama: {nama}\nWhatsApp: {whatsapp}\nEmail: {email}\nKebutuhan/Pesan: {kebutuhan}\n\nRingkasan data lengkap:\n{summary}\n\nSumber: {source_url}\nID: {submission_id}",
                'whatsapp_customer_template' => "Halo {nama}, terima kasih sudah mengisi {form_name} di {site_name}.\n\nData Anda sudah kami terima. Admin kami akan menghubungi Anda untuk langkah berikutnya.\n\nSalam,\n{site_name}",
                'email_admin_subject' => 'Lead baru dari {form_name}',
                'email_customer_subject' => 'Terima kasih, data Anda sudah kami terima',
                'email_admin_template' => "Ada data form baru masuk dari {form_name}.\n\nNama: {nama}\nWhatsApp: {whatsapp}\nEmail: {email}\nKebutuhan/Pesan: {kebutuhan}\n\nRingkasan data lengkap:\n{summary}\n\nSumber: {source_url}\nID: {submission_id}",
                'email_customer_template' => "Halo {nama},\n\nTerima kasih sudah mengisi {form_name}. Data Anda sudah kami terima. Tim {site_name} akan menghubungi Anda untuk langkah berikutnya.\n\nSalam,\n{site_name}",
            ],
        ];
    }
}

if (!function_exists('marketing_integration_normalize_settings')) {
    function marketing_integration_normalize_settings(array $settings): array
    {
        $default = marketing_integration_default_settings();
        $settings = array_replace_recursive($default, $settings);

        $settings['schema'] = 'marketing-integration';
        $settings['enabled'] = !empty($settings['enabled']);
        $settings['require_contact_consent'] = !empty($settings['require_contact_consent']);

        $settings['mailketing'] = [
            'enabled' => !empty($settings['mailketing']['enabled']),
            'api_token' => marketing_integration_secret_clean((string)($settings['mailketing']['api_token'] ?? '')),
            'default_list_id' => marketing_integration_clean((string)($settings['mailketing']['default_list_id'] ?? ''), 80),
            'inquiry_list_id' => marketing_integration_clean((string)($settings['mailketing']['inquiry_list_id'] ?? ''), 80),
            'order_list_id' => marketing_integration_clean((string)($settings['mailketing']['order_list_id'] ?? ''), 80),
            'buyer_list_id' => marketing_integration_clean((string)($settings['mailketing']['buyer_list_id'] ?? ''), 80),
            'sync_inquiry' => !empty($settings['mailketing']['sync_inquiry']),
            'sync_order' => !empty($settings['mailketing']['sync_order']),
            'sync_buyer' => !empty($settings['mailketing']['sync_buyer']),
        ];

        $settings['fonnte'] = [
            'enabled' => !empty($settings['fonnte']['enabled']),
            'token' => marketing_integration_secret_clean((string)($settings['fonnte']['token'] ?? '')),
            'country_code' => marketing_integration_clean((string)($settings['fonnte']['country_code'] ?? '62'), 8) ?: '62',
            'send_inquiry_message' => !empty($settings['fonnte']['send_inquiry_message']),
            'send_order_message' => !empty($settings['fonnte']['send_order_message']),
            'inquiry_template' => marketing_integration_multiline_clean((string)($settings['fonnte']['inquiry_template'] ?? $default['fonnte']['inquiry_template']), 900),
            'order_template' => marketing_integration_multiline_clean((string)($settings['fonnte']['order_template'] ?? $default['fonnte']['order_template']), 900),
        ];

        $settings['form_messages'] = [
            'whatsapp_admin_template' => marketing_integration_multiline_clean((string)($settings['form_messages']['whatsapp_admin_template'] ?? $default['form_messages']['whatsapp_admin_template']), 1400),
            'whatsapp_customer_template' => marketing_integration_multiline_clean((string)($settings['form_messages']['whatsapp_customer_template'] ?? $default['form_messages']['whatsapp_customer_template']), 1400),
            'email_admin_subject' => marketing_integration_clean((string)($settings['form_messages']['email_admin_subject'] ?? $default['form_messages']['email_admin_subject']), 160),
            'email_customer_subject' => marketing_integration_clean((string)($settings['form_messages']['email_customer_subject'] ?? $default['form_messages']['email_customer_subject']), 160),
            'email_admin_template' => marketing_integration_multiline_clean((string)($settings['form_messages']['email_admin_template'] ?? $default['form_messages']['email_admin_template']), 2600),
            'email_customer_template' => marketing_integration_multiline_clean((string)($settings['form_messages']['email_customer_template'] ?? $default['form_messages']['email_customer_template']), 2600),
        ];
        foreach ($settings['form_messages'] as $key => $value) {
            if ((string)$value === '') {
                $settings['form_messages'][$key] = (string)$default['form_messages'][$key];
            }
        }

        if ($settings['fonnte']['inquiry_template'] === '') {
            $settings['fonnte']['inquiry_template'] = $default['fonnte']['inquiry_template'];
        }
        if ($settings['fonnte']['order_template'] === '') {
            $settings['fonnte']['order_template'] = $default['fonnte']['order_template'];
        }

        return $settings;
    }
}

if (!function_exists('marketing_integration_read_settings')) {
    function marketing_integration_read_settings(): array
    {
        $file = marketing_integration_settings_file();
        if (!is_file($file)) {
            return marketing_integration_normalize_settings(marketing_integration_default_settings());
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return marketing_integration_normalize_settings(is_array($data) ? $data : []);
    }
}

if (!function_exists('marketing_integration_write_settings')) {
    function marketing_integration_write_settings(array $settings): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        $settings = marketing_integration_normalize_settings($settings);
        $settings['updated_at'] = date('c');
        $ok = @file_put_contents(
            marketing_integration_settings_file(),
            json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            LOCK_EX
        ) !== false;

        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('update', 'marketing_integration', null, 'Mailketing/Fonnte settings diperbarui.', [
                'enabled' => $settings['enabled'],
                'mailketing_enabled' => $settings['mailketing']['enabled'],
                'fonnte_enabled' => $settings['fonnte']['enabled'],
                'has_mailketing_token' => $settings['mailketing']['api_token'] !== '',
                'has_fonnte_token' => $settings['fonnte']['token'] !== '',
            ]);
        }

        return $ok;
    }
}

if (!function_exists('marketing_integration_settings_from_post')) {
    function marketing_integration_settings_from_post(array $post, array $current = []): array
    {
        $current = marketing_integration_normalize_settings($current ?: marketing_integration_read_settings());

        $mailketingToken = marketing_integration_secret_clean((string)($post['mailketing_api_token'] ?? ''));
        $fonnteToken = marketing_integration_secret_clean((string)($post['fonnte_token'] ?? ''));

        $settings = [
            'enabled' => !empty($post['enabled']),
            'require_contact_consent' => !empty($post['require_contact_consent']),
            'mailketing' => [
                'enabled' => !empty($post['mailketing_enabled']),
                'api_token' => $mailketingToken !== '' ? $mailketingToken : (string)($current['mailketing']['api_token'] ?? ''),
                'default_list_id' => marketing_integration_clean((string)($post['mailketing_default_list_id'] ?? ''), 80),
                'inquiry_list_id' => marketing_integration_clean((string)($post['mailketing_inquiry_list_id'] ?? ''), 80),
                'order_list_id' => marketing_integration_clean((string)($post['mailketing_order_list_id'] ?? ''), 80),
                'buyer_list_id' => marketing_integration_clean((string)($post['mailketing_buyer_list_id'] ?? ''), 80),
                'sync_inquiry' => !empty($post['mailketing_sync_inquiry']),
                'sync_order' => !empty($post['mailketing_sync_order']),
                'sync_buyer' => !empty($post['mailketing_sync_buyer']),
            ],
            'fonnte' => [
                'enabled' => !empty($post['fonnte_enabled']),
                'token' => $fonnteToken !== '' ? $fonnteToken : (string)($current['fonnte']['token'] ?? ''),
                'country_code' => marketing_integration_clean((string)($post['fonnte_country_code'] ?? '62'), 8),
                'send_inquiry_message' => !empty($post['fonnte_send_inquiry_message']),
                'send_order_message' => !empty($post['fonnte_send_order_message']),
                'inquiry_template' => marketing_integration_multiline_clean((string)($post['fonnte_inquiry_template'] ?? ''), 900),
                'order_template' => marketing_integration_multiline_clean((string)($post['fonnte_order_template'] ?? ''), 900),
            ],
            'form_messages' => [
                'whatsapp_admin_template' => marketing_integration_multiline_clean((string)($post['form_whatsapp_admin_template'] ?? ($current['form_messages']['whatsapp_admin_template'] ?? '')), 1400),
                'whatsapp_customer_template' => marketing_integration_multiline_clean((string)($post['form_whatsapp_customer_template'] ?? ($current['form_messages']['whatsapp_customer_template'] ?? '')), 1400),
                'email_admin_subject' => marketing_integration_clean((string)($post['form_email_admin_subject'] ?? ($current['form_messages']['email_admin_subject'] ?? '')), 160),
                'email_customer_subject' => marketing_integration_clean((string)($post['form_email_customer_subject'] ?? ($current['form_messages']['email_customer_subject'] ?? '')), 160),
                'email_admin_template' => marketing_integration_multiline_clean((string)($post['form_email_admin_template'] ?? ($current['form_messages']['email_admin_template'] ?? '')), 2600),
                'email_customer_template' => marketing_integration_multiline_clean((string)($post['form_email_customer_template'] ?? ($current['form_messages']['email_customer_template'] ?? '')), 2600),
            ],
        ];

        if (!empty($post['clear_mailketing_api_token'])) {
            $settings['mailketing']['api_token'] = '';
        }
        if (!empty($post['clear_fonnte_token'])) {
            $settings['fonnte']['token'] = '';
        }

        return marketing_integration_normalize_settings($settings);
    }
}

if (!function_exists('marketing_integration_log')) {
    function marketing_integration_log(array $entry): bool
    {
        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }
        $entry = array_merge([
            'time' => date('c'),
            'provider' => '',
            'type' => '',
            'status' => 'info',
            'message' => '',
            'meta' => [],
        ], $entry);
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return @file_put_contents(marketing_integration_log_file(), $line, FILE_APPEND | LOCK_EX) !== false;
    }
}

if (!function_exists('marketing_integration_recent_logs')) {
    function marketing_integration_recent_logs(int $limit = 20): array
    {
        $files = marketing_integration_log_files(3650);
        $rows = [];
        foreach ($files as $file) {
            $lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
            $lines = array_reverse((array)$lines);
            foreach ($lines as $line) {
                $row = json_decode((string)$line, true);
                if (is_array($row)) {
                    $rows[] = $row;
                }
                if (count($rows) >= $limit) {
                    return $rows;
                }
            }
        }
        return $rows;
    }
}

if (!function_exists('marketing_integration_http_post_form')) {
    function marketing_integration_http_post_form(string $url, array $body, array $headers = [], int $timeout = 12): array
    {
        $headers = array_merge(['Content-Type' => 'application/x-www-form-urlencoded'], $headers);
        $payload = http_build_query($body);

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            $headerLines = [];
            foreach ($headers as $key => $value) {
                $headerLines[] = $key . ': ' . $value;
            }
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(6, $timeout),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $responseBody = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            return [
                'ok' => $error === '' && $status >= 200 && $status < 300,
                'status' => $status,
                'body' => is_string($responseBody) ? $responseBody : '',
                'error' => $error,
            ];
        }

        $headerText = '';
        foreach ($headers as $key => $value) {
            $headerText .= $key . ': ' . $value . "\r\n";
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerText,
                'content' => $payload,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        $status = 0;
        if (!empty($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$line, $matches)) {
                    $status = (int)$matches[1];
                    break;
                }
            }
        }
        return [
            'ok' => is_string($responseBody) && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_string($responseBody) ? $responseBody : '',
            'error' => is_string($responseBody) ? '' : 'HTTP request failed.',
        ];
    }
}

if (!function_exists('marketing_integration_render_template')) {
    function marketing_integration_render_template(string $template, array $data): string
    {
        $map = [
            '{site_name}' => SITE_NAME,
            '{name}' => (string)($data['name'] ?? 'Customer'),
            '{phone}' => marketing_integration_mask_phone((string)($data['phone'] ?? '')),
            '{email}' => marketing_integration_mask_email((string)($data['email'] ?? '')),
            '{need}' => (string)($data['need'] ?? ''),
            '{location}' => (string)($data['location'] ?? ''),
            '{product_title}' => (string)($data['product_title'] ?? $data['item_title'] ?? ''),
            '{order_ref}' => function_exists('order_public_reference') ? order_public_reference($data) : (string)($data['ref'] ?? $data['id'] ?? ''),
        ];
        $message = strtr($template, $map);
        return marketing_integration_multiline_clean($message, 1200);
    }
}


if (!function_exists('marketing_integration_form_message_defaults')) {
    function marketing_integration_form_message_defaults(array $settings = []): array
    {
        $settings = marketing_integration_normalize_settings($settings ?: marketing_integration_read_settings());
        return (array)($settings['form_messages'] ?? marketing_integration_default_settings()['form_messages']);
    }
}

if (!function_exists('marketing_integration_test_send')) {
    function marketing_integration_test_send(array $post): array
    {
        $targetPhone = marketing_integration_clean((string)($post['test_phone'] ?? ''), 40);
        $targetEmail = marketing_integration_clean((string)($post['test_email'] ?? ''), 180);
        $sendWa = !empty($post['test_send_whatsapp']);
        $sendEmail = !empty($post['test_send_email']);
        $results = [];

        if ($sendWa) {
            $message = 'Tes WhatsApp otomatis dari ' . SITE_NAME . '. Jika pesan ini masuk, Fonnte sudah tersambung.';
            $ok = function_exists('marketing_integration_send_fonnte') && marketing_integration_send_fonnte([
                'id' => 'test-' . date('YmdHis'),
                'name' => 'Admin Test',
                'phone' => $targetPhone,
                'email' => $targetEmail,
                'need' => 'Tes integrasi',
                'source' => 'admin-test',
            ], $message, 'test_whatsapp');
            $results[] = $ok ? 'Tes WhatsApp berhasil diproses.' : 'Tes WhatsApp belum terkirim. Cek token Fonnte, nomor tujuan, dan log terbaru.';
        }

        if ($sendEmail) {
            $subject = 'Tes email otomatis - ' . SITE_NAME;
            $body = "Halo,

Ini adalah tes email otomatis dari " . SITE_NAME . ". Jika email ini diterima, pengaturan email website sudah aktif.

Waktu tes: " . date('d M Y H:i') . "
";
            $ok = function_exists('notification_send_email') && notification_send_email($targetEmail, $subject, $body, ['type' => 'test_marketing_email', 'target_type' => 'integration', 'target_ref' => 'manual-test']);
            if (function_exists('marketing_integration_log')) {
                marketing_integration_log([
                    'provider' => 'email',
                    'type' => 'test_email',
                    'status' => $ok ? 'success' : 'failed',
                    'message' => $ok ? 'Tes email berhasil diproses.' : 'Tes email belum terkirim. Cek EMAIL_TRANSPORT/SMTP dan alamat tujuan.',
                    'meta' => ['email_mask' => marketing_integration_mask_email($targetEmail)],
                ]);
            }
            $results[] = $ok ? 'Tes email berhasil diproses.' : 'Tes email belum terkirim. Cek pengaturan email dan log terbaru.';
        }

        if (!$sendWa && !$sendEmail) {
            $results[] = 'Pilih minimal satu channel untuk dites.';
        }

        return $results;
    }
}

if (!function_exists('marketing_integration_send_mailketing')) {
    function marketing_integration_send_mailketing(array $payload, string $listId, string $type = 'inquiry'): bool
    {
        $settings = marketing_integration_read_settings();
        $token = (string)($settings['mailketing']['api_token'] ?? '');
        $email = trim((string)($payload['email'] ?? ''));
        $phone = trim((string)($payload['phone'] ?? ''));
        $name = marketing_integration_clean((string)($payload['name'] ?? ''), 100);

        if ($token === '' || $listId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            marketing_integration_log([
                'provider' => 'mailketing',
                'type' => $type,
                'status' => 'skipped',
                'message' => 'Mailketing dilewati karena token/list/email belum lengkap atau email tidak valid.',
                'meta' => [
                    'has_token' => $token !== '',
                    'has_list_id' => $listId !== '',
                    'has_email' => $email !== '',
                    'email_mask' => marketing_integration_mask_email($email),
                    'ref' => (string)($payload['ref'] ?? $payload['id'] ?? ''),
                ],
            ]);
            return false;
        }

        $response = marketing_integration_http_post_form('https://api.mailketing.co.id/api/v1/addsubtolist', [
            'first_name' => $name,
            'email' => $email,
            'mobile' => $phone,
            'api_token' => $token,
            'list_id' => $listId,
        ], [], 12);

        marketing_integration_log([
            'provider' => 'mailketing',
            'type' => $type,
            'status' => !empty($response['ok']) ? 'success' : 'failed',
            'message' => !empty($response['ok']) ? 'Subscriber berhasil dikirim ke Mailketing.' : 'Pengiriman Mailketing gagal.',
            'meta' => [
                'http_status' => (int)($response['status'] ?? 0),
                'error' => marketing_integration_clean((string)($response['error'] ?? ''), 160),
                'body_snippet' => marketing_integration_clean((string)($response['body'] ?? ''), 220),
                'list_id' => $listId,
                'email_mask' => marketing_integration_mask_email($email),
                'phone_mask' => marketing_integration_mask_phone($phone),
                'ref' => (string)($payload['ref'] ?? $payload['id'] ?? ''),
            ],
        ]);

        return !empty($response['ok']);
    }
}

if (!function_exists('marketing_integration_send_fonnte')) {
    function marketing_integration_send_fonnte(array $payload, string $message, string $type = 'inquiry'): bool
    {
        $settings = marketing_integration_read_settings();
        $token = (string)($settings['fonnte']['token'] ?? '');
        $phone = marketing_integration_phone_for_fonnte((string)($payload['phone'] ?? ''));
        $countryCode = marketing_integration_clean((string)($settings['fonnte']['country_code'] ?? '62'), 8) ?: '62';

        if ($token === '' || $phone === '' || $message === '') {
            marketing_integration_log([
                'provider' => 'fonnte',
                'type' => $type,
                'status' => 'skipped',
                'message' => 'Fonnte dilewati karena token/nomor/pesan belum lengkap.',
                'meta' => [
                    'has_token' => $token !== '',
                    'phone_mask' => marketing_integration_mask_phone($phone),
                    'has_message' => $message !== '',
                    'ref' => (string)($payload['ref'] ?? $payload['id'] ?? ''),
                ],
            ]);
            return false;
        }

        $response = marketing_integration_http_post_form('https://api.fonnte.com/send', [
            'target' => $phone,
            'message' => $message,
            'countryCode' => $countryCode,
        ], ['Authorization' => $token], 12);

        marketing_integration_log([
            'provider' => 'fonnte',
            'type' => $type,
            'status' => !empty($response['ok']) ? 'success' : 'failed',
            'message' => !empty($response['ok']) ? 'Pesan WhatsApp berhasil dikirim via Fonnte.' : 'Pengiriman WhatsApp via Fonnte gagal.',
            'meta' => [
                'http_status' => (int)($response['status'] ?? 0),
                'error' => marketing_integration_clean((string)($response['error'] ?? ''), 160),
                'body_snippet' => marketing_integration_clean((string)($response['body'] ?? ''), 220),
                'phone_mask' => marketing_integration_mask_phone($phone),
                'ref' => (string)($payload['ref'] ?? $payload['id'] ?? ''),
            ],
        ]);

        return !empty($response['ok']);
    }
}

if (!function_exists('marketing_integration_has_consent')) {
    function marketing_integration_has_consent(array $payload): bool
    {
        $settings = marketing_integration_read_settings();
        if (empty($settings['require_contact_consent'])) {
            return true;
        }
        return (string)($payload['consent_contact'] ?? '') === 'yes' || !empty($payload['consent']);
    }
}

if (!function_exists('marketing_integration_dispatch_inquiry')) {
    function marketing_integration_dispatch_inquiry(array $inquiry): void
    {
        $settings = marketing_integration_read_settings();
        if (empty($settings['enabled'])) {
            return;
        }
        if (!marketing_integration_has_consent($inquiry)) {
            marketing_integration_log(['provider' => 'system', 'type' => 'inquiry', 'status' => 'skipped', 'message' => 'Inquiry tidak dikirim karena consent contact tidak ada.', 'meta' => ['ref' => (string)($inquiry['id'] ?? '')]]);
            return;
        }

        if (!empty($settings['mailketing']['enabled']) && !empty($settings['mailketing']['sync_inquiry'])) {
            $customListId = marketing_integration_clean((string)($inquiry['mailketing_list_id'] ?? ''), 80);
            $listId = $customListId !== '' ? $customListId : (string)($settings['mailketing']['inquiry_list_id'] ?: $settings['mailketing']['default_list_id']);
            marketing_integration_send_mailketing($inquiry, $listId, $customListId !== '' ? 'landing_page_form' : 'inquiry');
        }

        if (!empty($settings['fonnte']['enabled']) && !empty($settings['fonnte']['send_inquiry_message'])) {
            $message = marketing_integration_render_template((string)$settings['fonnte']['inquiry_template'], $inquiry);
            marketing_integration_send_fonnte($inquiry, $message, 'inquiry');
        }
    }
}

if (!function_exists('marketing_integration_dispatch_order')) {
    function marketing_integration_dispatch_order(array $order): void
    {
        $settings = marketing_integration_read_settings();
        if (empty($settings['enabled'])) {
            return;
        }
        if (!marketing_integration_has_consent($order)) {
            marketing_integration_log(['provider' => 'system', 'type' => 'order', 'status' => 'skipped', 'message' => 'Order tidak dikirim karena consent contact tidak ada.', 'meta' => ['ref' => (string)($order['ref'] ?? $order['id'] ?? '')]]);
            return;
        }

        if (!empty($settings['mailketing']['enabled']) && !empty($settings['mailketing']['sync_order'])) {
            $listId = (string)($settings['mailketing']['order_list_id'] ?: $settings['mailketing']['default_list_id']);
            marketing_integration_send_mailketing($order, $listId, 'order');
        }

        if (!empty($settings['fonnte']['enabled']) && !empty($settings['fonnte']['send_order_message'])) {
            $message = marketing_integration_render_template((string)$settings['fonnte']['order_template'], $order);
            marketing_integration_send_fonnte($order, $message, 'order');
        }
    }
}


if (!function_exists('marketing_integration_paid_statuses')) {
    function marketing_integration_paid_statuses(): array
    {
        return ['DP Masuk', 'Lunas', 'Valid'];
    }
}

if (!function_exists('marketing_integration_is_paid_payload')) {
    function marketing_integration_is_paid_payload(array $payload): bool
    {
        $paymentStatus = (string)($payload['payment_status'] ?? '');
        $proofStatus = (string)($payload['proof_status'] ?? $payload['status'] ?? '');
        return in_array($paymentStatus, marketing_integration_paid_statuses(), true)
            || in_array($proofStatus, marketing_integration_paid_statuses(), true);
    }
}

if (!function_exists('marketing_integration_dispatch_buyer')) {
    function marketing_integration_dispatch_buyer(array $payload, array $meta = []): bool
    {
        $settings = marketing_integration_read_settings();
        if (empty($settings['enabled'])) {
            return false;
        }
        if (empty($settings['mailketing']['enabled']) || empty($settings['mailketing']['sync_buyer'])) {
            marketing_integration_log([
                'provider' => 'mailketing',
                'type' => 'buyer',
                'status' => 'skipped',
                'message' => 'Buyer sync dilewati karena Mailketing atau Sync Buyer belum aktif.',
                'meta' => [
                    'ref' => (string)($payload['ref'] ?? $payload['order_ref'] ?? $payload['id'] ?? ''),
                    'mailketing_enabled' => !empty($settings['mailketing']['enabled']),
                    'sync_buyer' => !empty($settings['mailketing']['sync_buyer']),
                ],
            ]);
            return false;
        }
        if (!marketing_integration_is_paid_payload($payload)) {
            marketing_integration_log([
                'provider' => 'mailketing',
                'type' => 'buyer',
                'status' => 'skipped',
                'message' => 'Buyer sync dilewati karena status pembayaran belum DP Masuk/Lunas/Valid.',
                'meta' => [
                    'payment_status' => (string)($payload['payment_status'] ?? ''),
                    'proof_status' => (string)($payload['proof_status'] ?? $payload['status'] ?? ''),
                    'ref' => (string)($payload['ref'] ?? $payload['order_ref'] ?? $payload['id'] ?? ''),
                ],
            ]);
            return false;
        }
        if (!marketing_integration_has_consent($payload)) {
            marketing_integration_log([
                'provider' => 'mailketing',
                'type' => 'buyer',
                'status' => 'skipped',
                'message' => 'Buyer sync dilewati karena consent contact tidak ada.',
                'meta' => ['ref' => (string)($payload['ref'] ?? $payload['order_ref'] ?? $payload['id'] ?? '')],
            ]);
            return false;
        }

        $listId = (string)($settings['mailketing']['buyer_list_id'] ?? '');
        if ($listId === '') {
            marketing_integration_log([
                'provider' => 'mailketing',
                'type' => 'buyer',
                'status' => 'skipped',
                'message' => 'Buyer sync dilewati karena Buyer List ID belum diisi.',
                'meta' => ['ref' => (string)($payload['ref'] ?? $payload['order_ref'] ?? $payload['id'] ?? '')],
            ]);
            return false;
        }

        $buyerPayload = $payload;
        if (empty($buyerPayload['ref']) && !empty($buyerPayload['order_ref'])) {
            $buyerPayload['ref'] = (string)$buyerPayload['order_ref'];
        }
        if (empty($buyerPayload['email']) && !empty($buyerPayload['payer_email'])) {
            $buyerPayload['email'] = (string)$buyerPayload['payer_email'];
        }
        if (empty($buyerPayload['phone']) && !empty($buyerPayload['payer_phone'])) {
            $buyerPayload['phone'] = (string)$buyerPayload['payer_phone'];
        }
        if (empty($buyerPayload['name']) && !empty($buyerPayload['payer_name'])) {
            $buyerPayload['name'] = (string)$buyerPayload['payer_name'];
        }

        $sent = marketing_integration_send_mailketing($buyerPayload, $listId, 'buyer');
        if ($sent) {
            marketing_integration_log([
                'provider' => 'system',
                'type' => 'buyer',
                'status' => 'success',
                'message' => 'Lead berbayar berhasil diarahkan ke Buyer List ID.',
                'meta' => array_merge([
                    'list_id' => $listId,
                    'ref' => (string)($buyerPayload['ref'] ?? $buyerPayload['id'] ?? ''),
                    'email_mask' => marketing_integration_mask_email((string)($buyerPayload['email'] ?? '')),
                ], $meta),
            ]);
        }
        return $sent;
    }
}

if (!function_exists('marketing_integration_summary')) {
    function marketing_integration_summary(): array
    {
        $settings = marketing_integration_read_settings();
        $logs = marketing_integration_recent_logs(100);
        $counts = ['success' => 0, 'failed' => 0, 'skipped' => 0, 'info' => 0];
        foreach ($logs as $log) {
            $status = (string)($log['status'] ?? 'info');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        return [
            'enabled' => !empty($settings['enabled']),
            'require_contact_consent' => !empty($settings['require_contact_consent']),
            'mailketing' => [
                'enabled' => !empty($settings['mailketing']['enabled']),
                'configured' => (string)($settings['mailketing']['api_token'] ?? '') !== '' && ((string)($settings['mailketing']['default_list_id'] ?? '') !== '' || (string)($settings['mailketing']['inquiry_list_id'] ?? '') !== '' || (string)($settings['mailketing']['order_list_id'] ?? '') !== '' || (string)($settings['mailketing']['buyer_list_id'] ?? '') !== ''),
                'default_list_id' => (string)($settings['mailketing']['default_list_id'] ?? ''),
                'inquiry_list_id' => (string)($settings['mailketing']['inquiry_list_id'] ?? ''),
                'order_list_id' => (string)($settings['mailketing']['order_list_id'] ?? ''),
                'buyer_list_id' => (string)($settings['mailketing']['buyer_list_id'] ?? ''),
                'sync_buyer' => !empty($settings['mailketing']['sync_buyer']),
                'buyer_configured' => (string)($settings['mailketing']['api_token'] ?? '') !== '' && (string)($settings['mailketing']['buyer_list_id'] ?? '') !== '',
            ],
            'fonnte' => [
                'enabled' => !empty($settings['fonnte']['enabled']),
                'configured' => (string)($settings['fonnte']['token'] ?? '') !== '',
                'country_code' => (string)($settings['fonnte']['country_code'] ?? '62'),
            ],
            'recent_logs' => count($logs),
            'counts' => $counts,
        ];
    }
}
