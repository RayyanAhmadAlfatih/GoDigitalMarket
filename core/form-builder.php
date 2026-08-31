<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| CUSTOM FORM BUILDER FOUNDATION
|--------------------------------------------------------------------------
| Lightweight custom form system for UMKM websites. Admin can create forms
| for contact, consultation, booking, lead magnet, request quotation, and
| simple checkout preparation. Submissions are stored in JSONL logs so the
| template stays friendly for shared hosting.
|--------------------------------------------------------------------------
*/

if (!function_exists('custom_form_storage_file')) {
    function custom_form_storage_file(): string
    {
        return STORAGE_PATH . '/custom-forms.json';
    }
}

if (!function_exists('custom_form_submission_file')) {
    function custom_form_submission_file(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();
        return LOGS_PATH . '/custom-form-submissions-' . date('Y-m', $timestamp) . '.jsonl';
    }
}

if (!function_exists('custom_form_upload_dir')) {
    function custom_form_upload_dir(): string
    {
        return STORAGE_PATH . '/form-files';
    }
}

if (!function_exists('custom_form_upload_url')) {
    function custom_form_upload_url(string $filename): string
    {
        $filename = basename(str_replace(['\\', '/'], '', trim($filename)));
        return url('admin/form-file?file=' . rawurlencode($filename));
    }
}

if (!function_exists('custom_form_upload_absolute_path')) {
    function custom_form_upload_absolute_path(string $filename): ?string
    {
        $filename = basename(str_replace(['..', '\\', '/'], '', trim($filename)));
        if ($filename === '' || !preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{10}\.(jpg|jpeg|png|webp|pdf|zip)$/i', $filename)) {
            return null;
        }

        $paths = [
            custom_form_upload_dir() . '/' . $filename,
            ASSETS_PATH . '/uploads/form-files/' . $filename, // legacy fallback for older local uploads
        ];

        foreach ($paths as $candidate) {
            $path = realpath($candidate);
            $base = realpath(dirname($candidate));
            if ($path && $base && str_starts_with($path, $base) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}

if (!function_exists('custom_form_clean_text')) {
    function custom_form_clean_text(mixed $value, int $max = 180): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map(static fn($item): string => (string)$item, $value));
        }

        $value = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');

        if ($value === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('custom_form_clean_multiline')) {
    function custom_form_clean_multiline(mixed $value, int $max = 1600): string
    {
        if (is_array($value)) {
            $value = implode("\n", array_map(static fn($item): string => (string)$item, $value));
        }

        $value = trim(strip_tags((string)$value));
        $value = preg_replace("/\r\n|\r/", "\n", (string)$value);
        $value = preg_replace('/[ \t]+/', ' ', (string)$value);
        $value = preg_replace('/\n{3,}/', "\n\n", (string)$value);

        if ($value === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('custom_form_field_types')) {
    function custom_form_field_types(): array
    {
        return [
            'text' => 'Teks Pendek',
            'textarea' => 'Teks Panjang',
            'email' => 'Email',
            'phone' => 'Nomor WhatsApp/Telepon',
            'number' => 'Angka',
            'select' => 'Pilihan Dropdown',
            'radio' => 'Pilihan Satu Jawaban',
            'checkbox' => 'Checklist',
            'date' => 'Tanggal',
            'file' => 'Upload File',
        ];
    }
}

if (!function_exists('custom_form_type_options')) {
    function custom_form_type_options(): array
    {
        return [
            'contact' => 'Form Kontak',
            'consultation' => 'Konsultasi',
            'lead_magnet' => 'Lead Magnet',
            'quotation' => 'Minta Penawaran',
            'booking' => 'Booking/Jadwal',
            'checkout' => 'Checkout Sederhana',
            'survey' => 'Survey',
            'custom' => 'Custom',
        ];
    }
}

if (!function_exists('custom_form_status_options')) {
    function custom_form_status_options(): array
    {
        return [
            'active' => 'Aktif',
            'draft' => 'Draft',
        ];
    }
}


if (!function_exists('custom_form_default_integrations')) {
    function custom_form_default_integrations(): array
    {
        return [
            'send_to_marketing' => true,
            'mailketing_list_id' => '',
            'webhook_url' => '',
            'webhook_enabled' => false,
            'whatsapp_admin_enabled' => true,
            'whatsapp_customer_enabled' => true,
            'admin_whatsapp' => '',
            'whatsapp_admin_template' => '',
            'whatsapp_customer_template' => '',
            'email_admin_enabled' => true,
            'email_customer_enabled' => true,
            'admin_email' => '',
            'email_admin_subject' => '',
            'email_customer_subject' => '',
            'email_admin_template' => '',
            'email_customer_template' => '',
        ];
    }
}

if (!function_exists('custom_form_clean_url')) {
    function custom_form_clean_url(mixed $value, int $max = 360): string
    {
        $url = custom_form_clean_text($value, $max);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/')) {
            return $url;
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}

if (!function_exists('custom_form_slug')) {
    function custom_form_slug(string $value, string $fallback = 'form'): string
    {
        $slug = function_exists('slugify') ? slugify($value) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value) ?: '');
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : $fallback;
    }
}

if (!function_exists('custom_form_field_key')) {
    function custom_form_field_key(string $value, string $fallback = 'field'): string
    {
        $key = custom_form_slug($value, $fallback);
        $key = str_replace('-', '_', $key);
        return preg_replace('/[^a-z0-9_]/', '', $key) ?: $fallback;
    }
}

if (!function_exists('custom_form_parse_options')) {
    function custom_form_parse_options(mixed $value): array
    {
        if (is_array($value)) {
            $lines = $value;
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string)$value) ?: [];
        }

        $options = [];
        foreach ($lines as $line) {
            $option = custom_form_clean_text($line, 80);
            if ($option !== '' && !in_array($option, $options, true)) {
                $options[] = $option;
            }
        }

        return array_slice($options, 0, 30);
    }
}

if (!function_exists('custom_form_default_fields')) {
    function custom_form_default_fields(string $type = 'consultation'): array
    {
        $base = [
            ['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true, 'placeholder' => 'Nama Anda', 'help' => '', 'options' => []],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'phone', 'required' => true, 'placeholder' => '08xxxxxxxxxx', 'help' => '', 'options' => []],
            ['key' => 'pesan', 'label' => 'Pesan', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Tulis kebutuhan Anda...', 'help' => '', 'options' => []],
        ];

        if ($type === 'lead_magnet') {
            return [
                ['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true, 'placeholder' => 'Nama Anda', 'help' => '', 'options' => []],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'placeholder' => 'nama@email.com', 'help' => 'Link materi bisa dikirim ke email ini.', 'options' => []],
                ['key' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'phone', 'required' => false, 'placeholder' => '08xxxxxxxxxx', 'help' => '', 'options' => []],
            ];
        }

        if ($type === 'booking') {
            return [
                ['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true, 'placeholder' => 'Nama Anda', 'help' => '', 'options' => []],
                ['key' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'phone', 'required' => true, 'placeholder' => '08xxxxxxxxxx', 'help' => '', 'options' => []],
                ['key' => 'tanggal', 'label' => 'Tanggal yang Diinginkan', 'type' => 'date', 'required' => false, 'placeholder' => '', 'help' => '', 'options' => []],
                ['key' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Tulis kebutuhan booking...', 'help' => '', 'options' => []],
            ];
        }

        if ($type === 'checkout') {
            return [
                ['key' => 'nama', 'label' => 'Nama Penerima', 'type' => 'text', 'required' => true, 'placeholder' => 'Nama lengkap', 'help' => '', 'options' => []],
                ['key' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'phone', 'required' => true, 'placeholder' => '08xxxxxxxxxx', 'help' => '', 'options' => []],
                ['key' => 'alamat', 'label' => 'Alamat', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Alamat lengkap pengiriman/layanan', 'help' => '', 'options' => []],
                ['key' => 'catatan', 'label' => 'Catatan Order', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Warna, ukuran, jadwal, atau catatan lain', 'help' => '', 'options' => []],
            ];
        }

        return $base;
    }
}

if (!function_exists('custom_form_default_forms')) {
    function custom_form_default_forms(): array
    {
        return [
            [
                'id' => 'form_konsultasi_umkm',
                'title' => 'Form Konsultasi',
                'slug' => 'konsultasi',
                'type' => 'consultation',
                'status' => 'active',
                'description' => 'Form singkat untuk calon customer yang ingin bertanya atau konsultasi sebelum membeli.',
                'submit_label' => 'Kirim Konsultasi',
                'success_message' => 'Terima kasih, data Anda sudah masuk. Admin akan menghubungi Anda.',
                'redirect_url' => '',
                'consent_text' => 'Saya bersedia dihubungi admin terkait kebutuhan ini.',
                'button_mode' => 'submit',
                'integrations' => custom_form_default_integrations(),
                'fields' => custom_form_default_fields('consultation'),
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ],
            [
                'id' => 'form_lead_magnet_umkm',
                'title' => 'Form Lead Magnet',
                'slug' => 'lead-magnet',
                'type' => 'lead_magnet',
                'status' => 'draft',
                'description' => 'Form untuk mengumpulkan data calon customer sebelum memberikan materi gratis atau bonus digital.',
                'submit_label' => 'Dapatkan Materi',
                'success_message' => 'Terima kasih, data Anda sudah masuk. Admin akan mengirim akses materi sesuai pengaturan.',
                'redirect_url' => '',
                'consent_text' => 'Saya bersedia dihubungi admin untuk informasi lanjutan.',
                'button_mode' => 'submit',
                'integrations' => array_replace(custom_form_default_integrations(), ['send_to_marketing' => true]),
                'fields' => custom_form_default_fields('lead_magnet'),
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ],
            [
                'id' => 'form_checkout_ringan_umkm',
                'title' => 'Form Checkout Sederhana',
                'slug' => 'checkout-sederhana',
                'type' => 'checkout',
                'status' => 'draft',
                'description' => 'Form awal untuk order manual, booking, atau checkout ringan sebelum sistem checkout penuh aktif.',
                'submit_label' => 'Kirim Data Order',
                'success_message' => 'Terima kasih, data order Anda sudah masuk. Admin akan mengecek dan menghubungi Anda.',
                'redirect_url' => '',
                'consent_text' => 'Saya setuju admin menghubungi saya untuk konfirmasi order.',
                'button_mode' => 'submit',
                'integrations' => custom_form_default_integrations(),
                'fields' => custom_form_default_fields('checkout'),
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ],
        ];
    }
}

if (!function_exists('custom_form_read_forms')) {
    function custom_form_read_forms(): array
    {
        $file = custom_form_storage_file();
        if (!is_file($file)) {
            return custom_form_default_forms();
        }

        $data = json_decode((string)@file_get_contents($file), true);
        if (!is_array($data)) {
            return custom_form_default_forms();
        }

        return array_values(array_filter(array_map('custom_form_normalize', $data), static fn($form): bool => is_array($form) && ($form['slug'] ?? '') !== ''));
    }
}

if (!function_exists('custom_form_write_forms')) {
    function custom_form_write_forms(array $forms): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }

        $forms = array_values(array_map('custom_form_normalize', $forms));
        $json = json_encode($forms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $ok = @file_put_contents(custom_form_storage_file(), $json . PHP_EOL, LOCK_EX) !== false;
        if ($ok) {
            @chmod(custom_form_storage_file(), 0644);
        }
        return $ok;
    }
}

if (!function_exists('custom_form_normalize')) {
    function custom_form_normalize(array $form): array
    {
        $types = custom_form_type_options();
        $statuses = custom_form_status_options();

        $title = custom_form_clean_text($form['title'] ?? 'Form Custom', 120);
        $slug = custom_form_slug((string)($form['slug'] ?? $title), 'form-custom');
        $type = (string)($form['type'] ?? 'custom');
        if (!isset($types[$type])) {
            $type = 'custom';
        }
        $status = (string)($form['status'] ?? 'draft');
        if (!isset($statuses[$status])) {
            $status = 'draft';
        }

        $fields = custom_form_sanitize_fields($form['fields'] ?? custom_form_default_fields($type));
        $rawIntegrations = is_array($form['integrations'] ?? null) ? (array)$form['integrations'] : [];
        $defaultIntegrations = custom_form_default_integrations();
        $integrations = array_replace($defaultIntegrations, $rawIntegrations);
        $integrations = [
            'send_to_marketing' => !empty($integrations['send_to_marketing']),
            'mailketing_list_id' => custom_form_clean_text($integrations['mailketing_list_id'] ?? '', 80),
            'webhook_url' => custom_form_clean_url($integrations['webhook_url'] ?? '', 360),
            'webhook_enabled' => !empty($integrations['webhook_enabled']),
            'whatsapp_admin_enabled' => !empty($integrations['whatsapp_admin_enabled']),
            'whatsapp_customer_enabled' => !empty($integrations['whatsapp_customer_enabled']),
            'admin_whatsapp' => preg_replace('/\D+/', '', (string)($integrations['admin_whatsapp'] ?? '')) ?: '',
            'whatsapp_admin_template' => custom_form_clean_multiline($integrations['whatsapp_admin_template'] ?? $defaultIntegrations['whatsapp_admin_template'], 1400),
            'whatsapp_customer_template' => custom_form_clean_multiline($integrations['whatsapp_customer_template'] ?? $defaultIntegrations['whatsapp_customer_template'], 1400),
            'email_admin_enabled' => !empty($integrations['email_admin_enabled']),
            'email_customer_enabled' => !empty($integrations['email_customer_enabled']),
            'admin_email' => filter_var((string)($integrations['admin_email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$integrations['admin_email'] : '',
            'email_admin_subject' => custom_form_clean_text($integrations['email_admin_subject'] ?? $defaultIntegrations['email_admin_subject'], 160),
            'email_customer_subject' => custom_form_clean_text($integrations['email_customer_subject'] ?? $defaultIntegrations['email_customer_subject'], 160),
            'email_admin_template' => custom_form_clean_multiline($integrations['email_admin_template'] ?? $defaultIntegrations['email_admin_template'], 2600),
            'email_customer_template' => custom_form_clean_multiline($integrations['email_customer_template'] ?? $defaultIntegrations['email_customer_template'], 2600),
        ];


        return [
            'id' => custom_form_clean_text($form['id'] ?? ('form_' . substr(hash('sha256', $slug . microtime(true)), 0, 12)), 80),
            'title' => $title !== '' ? $title : 'Form Custom',
            'slug' => $slug,
            'type' => $type,
            'status' => $status,
            'description' => custom_form_clean_multiline($form['description'] ?? '', 360),
            'submit_label' => custom_form_clean_text($form['submit_label'] ?? 'Kirim Form', 50),
            'success_message' => custom_form_clean_multiline($form['success_message'] ?? 'Terima kasih, data Anda sudah masuk.', 360),
            'redirect_url' => custom_form_clean_text($form['redirect_url'] ?? '', 240),
            'consent_text' => custom_form_clean_multiline($form['consent_text'] ?? 'Saya bersedia dihubungi admin terkait data yang saya kirim.', 240),
            'button_mode' => in_array((string)($form['button_mode'] ?? 'submit'), ['submit', 'whatsapp_after_submit'], true) ? (string)($form['button_mode'] ?? 'submit') : 'submit',
            'integrations' => $integrations,
            'fields' => $fields,
            'created_at' => custom_form_clean_text($form['created_at'] ?? date('c'), 60),
            'updated_at' => custom_form_clean_text($form['updated_at'] ?? date('c'), 60),
        ];
    }
}

if (!function_exists('custom_form_sanitize_fields')) {
    function custom_form_sanitize_fields(mixed $fields): array
    {
        $fields = is_array($fields) ? $fields : [];
        $allowedTypes = custom_form_field_types();
        $clean = [];
        $usedKeys = [];

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = custom_form_clean_text($field['label'] ?? '', 90);
            if ($label === '') {
                continue;
            }

            $key = custom_form_field_key((string)($field['key'] ?? $label), 'field_' . ((int)$index + 1));
            $baseKey = $key;
            $counter = 2;
            while (in_array($key, $usedKeys, true)) {
                $key = $baseKey . '_' . $counter;
                $counter++;
            }
            $usedKeys[] = $key;

            $type = (string)($field['type'] ?? 'text');
            if (!isset($allowedTypes[$type])) {
                $type = 'text';
            }

            $options = custom_form_parse_options($field['options'] ?? []);
            if (in_array($type, ['select', 'radio', 'checkbox'], true) && !$options) {
                $options = ['Pilihan 1', 'Pilihan 2'];
            }

            $clean[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => !empty($field['required']),
                'placeholder' => custom_form_clean_text($field['placeholder'] ?? '', 120),
                'help' => custom_form_clean_text($field['help'] ?? '', 180),
                'options' => $options,
            ];
        }

        return array_slice($clean, 0, 30);
    }
}

if (!function_exists('custom_form_find')) {
    function custom_form_find(string $identifier, bool $activeOnly = false): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        foreach (custom_form_read_forms() as $form) {
            $match = ((string)($form['id'] ?? '') === $identifier) || ((string)($form['slug'] ?? '') === custom_form_slug($identifier, $identifier));
            if (!$match) {
                continue;
            }
            if ($activeOnly && (string)($form['status'] ?? 'draft') !== 'active') {
                return null;
            }
            return $form;
        }

        return null;
    }
}

if (!function_exists('custom_form_unique_slug')) {
    function custom_form_unique_slug(string $slug, string $currentId = ''): string
    {
        $slug = custom_form_slug($slug, 'form-custom');
        $base = $slug;
        $counter = 2;
        $forms = custom_form_read_forms();

        while (true) {
            $exists = false;
            foreach ($forms as $form) {
                if ((string)($form['slug'] ?? '') === $slug && (string)($form['id'] ?? '') !== $currentId) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                return $slug;
            }
            $slug = $base . '-' . $counter;
            $counter++;
        }
    }
}

if (!function_exists('custom_form_payload_from_post')) {
    function custom_form_payload_from_post(array $post): array
    {
        $id = custom_form_clean_text($post['id'] ?? '', 80);
        $type = (string)($post['type'] ?? 'custom');
        $title = custom_form_clean_text($post['title'] ?? 'Form Custom', 120);
        $slug = custom_form_unique_slug((string)($post['slug'] ?? $title), $id);

        $labels = (array)($post['field_label'] ?? []);
        $keys = (array)($post['field_key'] ?? []);
        $types = (array)($post['field_type'] ?? []);
        $required = (array)($post['field_required'] ?? []);
        $placeholders = (array)($post['field_placeholder'] ?? []);
        $helps = (array)($post['field_help'] ?? []);
        $options = (array)($post['field_options'] ?? []);
        $fields = [];

        foreach ($labels as $index => $label) {
            $label = custom_form_clean_text($label, 90);
            if ($label === '') {
                continue;
            }

            $fields[] = [
                'label' => $label,
                'key' => custom_form_clean_text($keys[$index] ?? '', 90),
                'type' => custom_form_clean_text($types[$index] ?? 'text', 40),
                'required' => isset($required[$index]) && (string)$required[$index] === '1',
                'placeholder' => custom_form_clean_text($placeholders[$index] ?? '', 120),
                'help' => custom_form_clean_text($helps[$index] ?? '', 180),
                'options' => custom_form_parse_options($options[$index] ?? ''),
            ];
        }

        return custom_form_normalize([
            'id' => $id !== '' ? $id : ('form_' . substr(hash('sha256', $title . microtime(true)), 0, 12)),
            'title' => $title,
            'slug' => $slug,
            'type' => $type,
            'status' => (string)($post['status'] ?? 'draft'),
            'description' => $post['description'] ?? '',
            'submit_label' => $post['submit_label'] ?? 'Kirim Form',
            'success_message' => $post['success_message'] ?? 'Terima kasih, data Anda sudah masuk.',
            'redirect_url' => $post['redirect_url'] ?? '',
            'consent_text' => $post['consent_text'] ?? '',
            'button_mode' => $post['button_mode'] ?? 'submit',
            'integrations' => [
                'send_to_marketing' => !empty($post['integration_send_to_marketing']),
                'mailketing_list_id' => custom_form_clean_text($post['integration_mailketing_list_id'] ?? '', 80),
                'webhook_enabled' => !empty($post['integration_webhook_enabled']),
                'webhook_url' => custom_form_clean_url($post['integration_webhook_url'] ?? '', 360),
                'whatsapp_admin_enabled' => !empty($post['integration_whatsapp_admin_enabled']),
                'whatsapp_customer_enabled' => !empty($post['integration_whatsapp_customer_enabled']),
                'admin_whatsapp' => preg_replace('/\D+/', '', (string)($post['integration_admin_whatsapp'] ?? '')) ?: '',
                'whatsapp_admin_template' => custom_form_clean_multiline($post['integration_whatsapp_admin_template'] ?? '', 1400),
                'whatsapp_customer_template' => custom_form_clean_multiline($post['integration_whatsapp_customer_template'] ?? '', 1400),
                'email_admin_enabled' => !empty($post['integration_email_admin_enabled']),
                'email_customer_enabled' => !empty($post['integration_email_customer_enabled']),
                'admin_email' => custom_form_clean_text($post['integration_admin_email'] ?? '', 160),
                'email_admin_subject' => custom_form_clean_text($post['integration_email_admin_subject'] ?? '', 160),
                'email_customer_subject' => custom_form_clean_text($post['integration_email_customer_subject'] ?? '', 160),
                'email_admin_template' => custom_form_clean_multiline($post['integration_email_admin_template'] ?? '', 2600),
                'email_customer_template' => custom_form_clean_multiline($post['integration_email_customer_template'] ?? '', 2600),
            ],
            'fields' => $fields ?: custom_form_default_fields($type),
            'created_at' => $post['created_at'] ?? date('c'),
            'updated_at' => date('c'),
        ]);
    }
}

if (!function_exists('custom_form_save_from_post')) {
    function custom_form_save_from_post(array $post): array
    {
        $payload = custom_form_payload_from_post($post);
        $forms = custom_form_read_forms();
        $saved = false;

        foreach ($forms as $index => $form) {
            if ((string)($form['id'] ?? '') === (string)$payload['id']) {
                $payload['created_at'] = (string)($form['created_at'] ?? $payload['created_at']);
                $forms[$index] = $payload;
                $saved = true;
                break;
            }
        }

        if (!$saved) {
            $forms[] = $payload;
        }

        if (!custom_form_write_forms($forms)) {
            throw new RuntimeException('Form belum berhasil disimpan. Cek permission folder storage.');
        }

        if (function_exists('activity_log_record')) {
            activity_log_record($saved ? 'update' : 'create', 'custom_form', (string)$payload['id'], $saved ? 'Form diperbarui.' : 'Form dibuat.', ['title' => $payload['title'], 'slug' => $payload['slug']]);
        }

        return $payload;
    }
}

if (!function_exists('custom_form_delete')) {
    function custom_form_delete(string $id): bool
    {
        $id = custom_form_clean_text($id, 80);
        if ($id === '') {
            return false;
        }
        $forms = custom_form_read_forms();
        $before = count($forms);
        $forms = array_values(array_filter($forms, static fn(array $form): bool => (string)($form['id'] ?? '') !== $id));
        if (count($forms) === $before) {
            return false;
        }
        $ok = custom_form_write_forms($forms);
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('delete', 'custom_form', $id, 'Form dihapus.');
        }
        return $ok;
    }
}

if (!function_exists('custom_form_allowed_file_extensions')) {
    function custom_form_allowed_file_extensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'zip'];
    }
}

if (!function_exists('custom_form_allowed_file_mimes')) {
    function custom_form_allowed_file_mimes(): array
    {
        return [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream'],
        ];
    }
}

if (!function_exists('custom_form_detect_upload_mime')) {
    function custom_form_detect_upload_mime(string $tmp): string
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string)finfo_file($finfo, $tmp);
                finfo_close($finfo);
            }
        }
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = (string)mime_content_type($tmp);
        }
        return strtolower(trim($mime));
    }
}

if (!function_exists('custom_form_safe_return_url')) {
    function custom_form_safe_return_url(string $target): string
    {
        $target = trim($target);
        if ($target === '' || preg_match('/[\x00-\x1F\x7F]/', $target)) {
            return '';
        }
        if (str_starts_with($target, '//') || str_starts_with($target, '\\')) {
            return '';
        }

        $siteHost = strtolower((string)(parse_url((string)SITE_URL, PHP_URL_HOST) ?? ''));
        if (str_starts_with($target, '/')) {
            return url(ltrim($target, '/'));
        }

        $parts = parse_url($target);
        if (!is_array($parts)) {
            return '';
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || $siteHost === '' || $host !== $siteHost) {
            return '';
        }
        return $target;
    }
}

if (!function_exists('custom_form_handle_file_upload')) {
    function custom_form_handle_file_upload(string $inputName): array
    {
        if (empty($_FILES[$inputName]) || !is_array($_FILES[$inputName]) || (int)($_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        $file = $_FILES[$inputName];
        if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload file gagal. Coba pilih file lain.');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new RuntimeException('Ukuran file maksimal 5MB.');
        }

        $original = basename((string)($file['name'] ?? 'file'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, custom_form_allowed_file_extensions(), true)) {
            throw new RuntimeException('Format file belum didukung. Gunakan JPG, PNG, WebP, PDF, atau ZIP.');
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('File upload tidak valid.');
        }

        $mime = custom_form_detect_upload_mime($tmp);
        $allowedMimes = custom_form_allowed_file_mimes();
        $validMimes = (array)($allowedMimes[$extension] ?? []);
        if ($validMimes && $mime !== '' && !in_array($mime, $validMimes, true)) {
            throw new RuntimeException('Format file belum sesuai dengan tipe file yang diupload.');
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) && @getimagesize($tmp) === false) {
            throw new RuntimeException('File gambar tidak valid.');
        }

        $dir = custom_form_upload_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @chmod($dir, 0775);

        $safeName = date('Ymd-His') . '-' . substr(hash('sha256', $original . microtime(true)), 0, 10) . '.' . $extension;
        $target = $dir . '/' . $safeName;
        if (!@move_uploaded_file((string)$file['tmp_name'], $target)) {
            throw new RuntimeException('File belum berhasil disimpan. Cek permission folder upload.');
        }
        @chmod($target, 0644);

        return [
            'name' => custom_form_clean_text($original, 160),
            'file' => $safeName,
            'url' => custom_form_upload_url($safeName),
            'size' => $size,
            'extension' => $extension,
        ];
    }
}

if (!function_exists('custom_form_rate_limit_key')) {
    function custom_form_rate_limit_key(): string
    {
        return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'ip') . '|' . (string)($_SERVER['HTTP_USER_AGENT'] ?? 'ua') . '|custom-form');
    }
}

if (!function_exists('custom_form_rate_limit_file')) {
    function custom_form_rate_limit_file(): string
    {
        return CACHE_PATH . '/custom-form-rate-limit.json';
    }
}

if (!function_exists('custom_form_is_rate_limited')) {
    function custom_form_is_rate_limited(): bool
    {
        $last = (int)($_SESSION['last_custom_form_submit_at'] ?? 0);
        if ($last > 0 && (time() - $last) < 12) {
            return true;
        }

        $file = custom_form_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = custom_form_rate_limit_key();
        $bucket = array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => (int)$ts > (time() - 3600)));
        return count($bucket) >= 10;
    }
}

if (!function_exists('custom_form_touch_rate_limit')) {
    function custom_form_touch_rate_limit(): void
    {
        $_SESSION['last_custom_form_submit_at'] = time();
        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }
        $file = custom_form_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = custom_form_rate_limit_key();
        $data[$key] = array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => (int)$ts > (time() - 3600)));
        $data[$key][] = time();
        if (count($data) > 600) {
            $data = array_slice($data, -600, null, true);
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}


if (!function_exists('custom_form_first_value')) {
    function custom_form_first_value(array $values, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            if (is_array($value)) {
                $value = implode(', ', array_map(static fn($item): string => (string)$item, $value));
            }
            $value = custom_form_clean_text($value, 220);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }
}

if (!function_exists('custom_form_marketing_payload')) {
    function custom_form_marketing_payload(array $form, array $entry): array
    {
        $values = is_array($entry['values'] ?? null) ? (array)$entry['values'] : [];
        $name = custom_form_first_value($values, ['nama', 'name', 'nama_lengkap', 'full_name', 'customer_name']);
        $phone = custom_form_first_value($values, ['whatsapp', 'wa', 'phone', 'telepon', 'no_hp', 'nomor_whatsapp', 'nomor_hp']);
        $email = custom_form_first_value($values, ['email', 'alamat_email']);
        $need = custom_form_first_value($values, ['kebutuhan', 'need', 'pesan', 'message', 'catatan', 'catatan_order']);
        $message = custom_form_first_value($values, ['pesan', 'message', 'catatan', 'catatan_order', 'kebutuhan', 'need']);
        $sourceLabel = custom_form_clean_text($entry['source_label'] ?? $entry['form_title'] ?? 'Form Custom', 160);
        $integrations = is_array($form['integrations'] ?? null) ? (array)$form['integrations'] : custom_form_default_integrations();

        return [
            'id' => (string)($entry['id'] ?? ''),
            'name' => $name !== '' ? $name : 'Customer',
            'phone' => $phone,
            'email' => $email,
            'need' => $need,
            'message' => $message,
            'source' => 'custom-form',
            'sumber' => 'custom-form',
            'category' => 'custom-form',
            'intent' => (string)($entry['form_type'] ?? 'custom'),
            'label' => $sourceLabel,
            'item_title' => (string)($entry['form_title'] ?? 'Form Custom'),
            'page_path' => (string)($entry['source_url'] ?? ''),
            'source_url' => (string)($entry['source_url'] ?? ''),
            'custom_form_slug' => (string)($entry['form_slug'] ?? ''),
            'custom_form_title' => (string)($entry['form_title'] ?? ''),
            'landing_page_id' => (string)($entry['source_landing_page_id'] ?? ''),
            'landing_page_slug' => (string)($entry['source_landing_page_slug'] ?? ''),
            'mailketing_list_id' => custom_form_clean_text($integrations['mailketing_list_id'] ?? '', 80),
            'consent' => !empty($entry['consent']),
            'consent_contact' => !empty($entry['consent']) ? 'yes' : 'no',
        ];
    }
}

if (!function_exists('custom_form_template_value_map')) {
    function custom_form_template_value_map(array $form, array $entry, array $payload = []): array
    {
        $values = is_array($entry['values'] ?? null) ? (array)$entry['values'] : [];
        $map = [
            'site_name' => SITE_NAME,
            'form_name' => (string)($entry['form_title'] ?? $form['title'] ?? 'Form Custom'),
            'form_slug' => (string)($entry['form_slug'] ?? $form['slug'] ?? ''),
            'submission_id' => (string)($entry['id'] ?? ''),
            'source_url' => (string)($entry['source_url'] ?? ''),
            'date' => date('d M Y'),
            'time' => date('H:i'),
            'summary' => (string)($entry['summary'] ?? ''),
            'nama' => (string)($payload['name'] ?? ''),
            'name' => (string)($payload['name'] ?? ''),
            'whatsapp' => (string)($payload['phone'] ?? ''),
            'phone' => (string)($payload['phone'] ?? ''),
            'email' => (string)($payload['email'] ?? ''),
            'kebutuhan' => (string)($payload['need'] ?? ''),
            'need' => (string)($payload['need'] ?? ''),
            'pesan' => (string)($payload['message'] ?? ''),
            'message' => (string)($payload['message'] ?? ''),
        ];

        foreach ($values as $key => $value) {
            $cleanKey = custom_form_field_key((string)$key, 'field');
            if (is_array($value)) {
                $value = implode(', ', array_map(static fn($item): string => (string)$item, $value));
            }
            $map[$cleanKey] = custom_form_clean_multiline((string)$value, 900);
        }

        return $map;
    }
}

if (!function_exists('custom_form_render_message_template')) {
    function custom_form_render_message_template(string $template, array $form, array $entry, array $payload = [], int $max = 4000): string
    {
        $template = custom_form_clean_multiline($template, $max);
        if ($template === '') {
            return '';
        }
        $map = custom_form_template_value_map($form, $entry, $payload);
        $replace = [];
        foreach ($map as $key => $value) {
            $replace['{' . $key . '}'] = (string)$value;
        }
        return custom_form_clean_multiline(strtr($template, $replace), $max);
    }
}


if (!function_exists('custom_form_message_template_or_global')) {
    function custom_form_message_template_or_global(array $integrations, array $globalMessages, string $key): string
    {
        $local = trim((string)($integrations[$key] ?? ''));
        if ($local !== '') {
            return $local;
        }
        return (string)($globalMessages[$key] ?? '');
    }
}

if (!function_exists('custom_form_log_automation')) {
    function custom_form_log_automation(string $provider, string $type, string $status, string $message, array $entry, array $meta = []): void
    {
        if (!function_exists('marketing_integration_log')) {
            return;
        }
        marketing_integration_log([
            'provider' => $provider,
            'type' => $type,
            'status' => $status,
            'message' => $message,
            'meta' => array_merge([
                'ref' => (string)($entry['id'] ?? ''),
                'form' => (string)($entry['form_slug'] ?? ''),
            ], $meta),
        ]);
    }
}

if (!function_exists('custom_form_dispatch_custom_messages')) {
    function custom_form_dispatch_custom_messages(array $form, array $entry, array $payload): void
    {
        $integrations = is_array($form['integrations'] ?? null) ? array_replace(custom_form_default_integrations(), (array)$form['integrations']) : custom_form_default_integrations();
        $hasConsent = !function_exists('marketing_integration_has_consent') || marketing_integration_has_consent($payload);
        if (!$hasConsent) {
            custom_form_log_automation('system', 'custom_form_message', 'skipped', 'Pesan otomatis dilewati karena persetujuan kontak belum ada.', $entry);
            return;
        }

        $marketingSettings = function_exists('marketing_integration_read_settings') ? marketing_integration_read_settings() : [];
        $globalMessages = function_exists('marketing_integration_form_message_defaults') ? marketing_integration_form_message_defaults($marketingSettings) : [
            'whatsapp_admin_template' => "Form baru masuk dari {form_name}\n\nNama: {nama}\nWhatsApp: {whatsapp}\nEmail: {email}\nKebutuhan/Pesan: {kebutuhan}\n\nRingkasan data:\n{summary}\n\nSumber: {source_url}\nID: {submission_id}",
            'whatsapp_customer_template' => "Halo {nama}, terima kasih sudah mengisi {form_name} di {site_name}.\n\nData Anda sudah kami terima. Admin kami akan menghubungi Anda untuk langkah berikutnya.\n\nSalam,\n{site_name}",
            'email_admin_subject' => 'Lead baru dari {form_name}',
            'email_customer_subject' => 'Terima kasih, data Anda sudah kami terima',
            'email_admin_template' => "Ada data form baru masuk dari {form_name}.\n\nNama: {nama}\nWhatsApp: {whatsapp}\nEmail: {email}\nKebutuhan/Pesan: {kebutuhan}\n\nRingkasan data lengkap:\n{summary}\n\nSumber: {source_url}\nID: {submission_id}",
            'email_customer_template' => "Halo {nama},\n\nTerima kasih sudah mengisi {form_name}. Data Anda sudah kami terima. Tim {site_name} akan menghubungi Anda untuk langkah berikutnya.\n\nSalam,\n{site_name}",
        ];
        // Form Custom memakai pengaturan provider langsung.
        // Catatan: tombol test koneksi bisa berhasil walau toggle global lama belum aktif,
        // jadi runtime form tidak lagi menahan pengiriman hanya karena toggle global itu off.
        // Yang wajib tetap: provider Fonnte aktif, token tersedia, nomor tujuan ada, dan form mengizinkan aksi.
        $fonnteReady = !empty($marketingSettings['fonnte']['enabled']) && trim((string)($marketingSettings['fonnte']['token'] ?? '')) !== '';

        if (!empty($integrations['whatsapp_admin_enabled'])) {
            $adminPhone = preg_replace('/\D+/', '', (string)($integrations['admin_whatsapp'] ?? '')) ?: (defined('SITE_WHATSAPP') ? SITE_WHATSAPP : '');
            $template = custom_form_message_template_or_global($integrations, $globalMessages, 'whatsapp_admin_template');
            $message = custom_form_render_message_template($template, $form, $entry, $payload, 1400);
            if ($fonnteReady && $adminPhone !== '' && $message !== '' && function_exists('marketing_integration_send_fonnte')) {
                marketing_integration_send_fonnte(array_merge($payload, ['phone' => $adminPhone]), $message, 'custom_form_admin');
            } else {
                custom_form_log_automation('fonnte', 'custom_form_admin', 'skipped', 'WhatsApp admin belum dikirim. Pastikan Fonnte aktif, token terisi, nomor admin tersedia, dan template pesan tidak kosong.', $entry, [
                    'fonnte_ready' => $fonnteReady,
                    'has_phone' => $adminPhone !== '',
                    'has_message' => $message !== '',
                ]);
            }
        }

        if (!empty($integrations['whatsapp_customer_enabled'])) {
            $template = custom_form_message_template_or_global($integrations, $globalMessages, 'whatsapp_customer_template');
            $message = custom_form_render_message_template($template, $form, $entry, $payload, 1400);
            if ($fonnteReady && trim((string)($payload['phone'] ?? '')) !== '' && $message !== '' && function_exists('marketing_integration_send_fonnte')) {
                marketing_integration_send_fonnte($payload, $message, 'custom_form_customer');
            } else {
                custom_form_log_automation('fonnte', 'custom_form_customer', 'skipped', 'WhatsApp lead/customer belum dikirim. Pastikan Fonnte aktif, token terisi, nomor WhatsApp lead/customer tersedia, dan template pesan tidak kosong.', $entry, [
                    'fonnte_ready' => $fonnteReady,
                    'has_phone' => trim((string)($payload['phone'] ?? '')) !== '',
                    'has_message' => $message !== '',
                ]);
            }
        }

        if (!empty($integrations['email_admin_enabled']) && function_exists('notification_send_email')) {
            $adminEmail = (string)($integrations['admin_email'] ?? '');
            if ($adminEmail === '' && function_exists('notification_admin_email')) {
                $adminEmail = notification_admin_email();
            }
            $subjectTemplate = custom_form_message_template_or_global($integrations, $globalMessages, 'email_admin_subject');
            $bodyTemplate = custom_form_message_template_or_global($integrations, $globalMessages, 'email_admin_template');
            $subject = custom_form_render_message_template($subjectTemplate, $form, $entry, $payload, 160);
            $body = custom_form_render_message_template($bodyTemplate, $form, $entry, $payload, 2600);
            if ($adminEmail !== '' && $subject !== '' && $body !== '') {
                $ok = notification_send_email($adminEmail, $subject, $body, ['type' => 'custom_form_admin', 'target_type' => 'custom_form_submission', 'target_ref' => (string)($entry['id'] ?? '')]);
                custom_form_log_automation('email', 'custom_form_admin', $ok ? 'success' : 'failed', $ok ? 'Email admin berhasil diproses.' : 'Email admin belum terkirim. Cek pengaturan email website.', $entry, [
                    'email_mask' => function_exists('marketing_integration_mask_email') ? marketing_integration_mask_email($adminEmail) : '',
                ]);
            } else {
                custom_form_log_automation('email', 'custom_form_admin', 'skipped', 'Email admin dilewati karena alamat/subjek/pesan belum lengkap.', $entry, [
                    'has_email' => $adminEmail !== '',
                    'has_subject' => $subject !== '',
                    'has_body' => $body !== '',
                ]);
            }
        }

        if (!empty($integrations['email_customer_enabled']) && function_exists('notification_send_email')) {
            $customerEmail = trim((string)($payload['email'] ?? ''));
            $subjectTemplate = custom_form_message_template_or_global($integrations, $globalMessages, 'email_customer_subject');
            $bodyTemplate = custom_form_message_template_or_global($integrations, $globalMessages, 'email_customer_template');
            $subject = custom_form_render_message_template($subjectTemplate, $form, $entry, $payload, 160);
            $body = custom_form_render_message_template($bodyTemplate, $form, $entry, $payload, 2600);
            if ($customerEmail !== '' && filter_var($customerEmail, FILTER_VALIDATE_EMAIL) && $subject !== '' && $body !== '') {
                $ok = notification_send_email($customerEmail, $subject, $body, ['type' => 'custom_form_customer', 'target_type' => 'custom_form_submission', 'target_ref' => (string)($entry['id'] ?? '')]);
                custom_form_log_automation('email', 'custom_form_customer', $ok ? 'success' : 'failed', $ok ? 'Email customer berhasil diproses.' : 'Email customer belum terkirim. Cek pengaturan email website.', $entry, [
                    'email_mask' => function_exists('marketing_integration_mask_email') ? marketing_integration_mask_email($customerEmail) : '',
                ]);
            } else {
                custom_form_log_automation('email', 'custom_form_customer', 'skipped', 'Email lead/customer dilewati karena email/subjek/pesan belum lengkap.', $entry, [
                    'has_email' => $customerEmail !== '',
                    'valid_email' => filter_var($customerEmail, FILTER_VALIDATE_EMAIL) !== false,
                    'has_subject' => $subject !== '',
                    'has_body' => $body !== '',
                ]);
            }
        }
    }
}

if (!function_exists('custom_form_send_webhook')) {
    function custom_form_send_webhook(string $url, array $payload): bool
    {
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        if (function_exists('marketing_integration_http_post_form')) {
            $flat = [];
            foreach ($payload as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $flat[$key] = (string)$value;
                }
            }
            $response = marketing_integration_http_post_form($url, $flat, [], 10);
            return !empty($response['ok']);
        }
        return false;
    }
}

if (!function_exists('custom_form_dispatch_mailketing')) {
    function custom_form_dispatch_mailketing(array $form, array $entry, array $payload): void
    {
        if (!function_exists('marketing_integration_read_settings') || !function_exists('marketing_integration_send_mailketing')) {
            custom_form_log_automation('mailketing', 'custom_form', 'skipped', 'Mailketing belum tersedia di runtime website.', $entry);
            return;
        }
        $settings = marketing_integration_read_settings();
        if (empty($settings['mailketing']['enabled'])) {
            custom_form_log_automation('mailketing', 'custom_form', 'skipped', 'Mailketing belum dikirim karena provider Mailketing belum aktif di menu WhatsApp & Email Marketing.', $entry);
            return;
        }
        if (empty($settings['mailketing']['sync_inquiry'])) {
            custom_form_log_automation('mailketing', 'custom_form', 'skipped', 'Mailketing belum dikirim karena opsi Kirim data form ke Mailketing belum aktif.', $entry);
            return;
        }
        if (function_exists('marketing_integration_has_consent') && !marketing_integration_has_consent($payload)) {
            custom_form_log_automation('mailketing', 'custom_form', 'skipped', 'Mailketing dilewati karena persetujuan kontak belum ada.', $entry);
            return;
        }
        $integrations = is_array($form['integrations'] ?? null) ? array_replace(custom_form_default_integrations(), (array)$form['integrations']) : custom_form_default_integrations();
        $customListId = custom_form_clean_text($integrations['mailketing_list_id'] ?? '', 80);
        $listId = $customListId !== '' ? $customListId : (string)($settings['mailketing']['inquiry_list_id'] ?: $settings['mailketing']['default_list_id']);
        marketing_integration_send_mailketing($payload, $listId, $customListId !== '' ? 'custom_form_list' : 'custom_form');
    }
}

if (!function_exists('custom_form_dispatch_integrations')) {
    function custom_form_dispatch_integrations(array $form, array $entry): void
    {
        $integrations = is_array($form['integrations'] ?? null) ? (array)$form['integrations'] : custom_form_default_integrations();
        $payload = custom_form_marketing_payload($form, $entry);

        if (!empty($integrations['send_to_marketing'])) {
            custom_form_dispatch_mailketing($form, $entry, $payload);
        }

        custom_form_dispatch_custom_messages($form, $entry, $payload);

        if (!empty($integrations['webhook_enabled']) && !empty($integrations['webhook_url'])) {
            $sent = custom_form_send_webhook((string)$integrations['webhook_url'], array_merge($payload, [
                'values_json' => json_encode($entry['values'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'summary' => (string)($entry['summary'] ?? ''),
                'created_at' => (string)($entry['created_at'] ?? ''),
            ]));
            if (function_exists('marketing_integration_log')) {
                marketing_integration_log([
                    'provider' => 'custom_webhook',
                    'type' => 'custom_form',
                    'status' => $sent ? 'success' : 'failed',
                    'message' => $sent ? 'Data form berhasil dikirim ke layanan lain.' : 'Data form belum berhasil dikirim ke layanan lain.',
                    'meta' => ['ref' => (string)($entry['id'] ?? ''), 'form' => (string)($entry['form_slug'] ?? '')],
                ]);
            }
        }
    }
}

if (!function_exists('custom_form_bridge_to_inquiry')) {
    function custom_form_bridge_to_inquiry(array $form, array $entry): ?bool
    {
        if (
            !function_exists('inquiry_enabled')
            || !inquiry_enabled()
            || !function_exists('inquiry_normalize_payload')
            || !function_exists('inquiry_store')
        ) {
            return null;
        }

        $values = is_array($entry['values'] ?? null) ? (array)$entry['values'] : [];
        $name = custom_form_first_value($values, ['nama', 'name', 'nama_lengkap', 'full_name', 'customer_name']);
        $phone = custom_form_first_value($values, ['whatsapp', 'wa', 'phone', 'telepon', 'no_hp', 'nomor_whatsapp', 'nomor_hp']);
        $email = custom_form_first_value($values, ['email', 'alamat_email']);
        $need = custom_form_first_value($values, ['kebutuhan', 'need', 'service', 'layanan', 'produk', 'product', 'subject']);
        $location = custom_form_first_value($values, ['lokasi', 'location', 'kota', 'city', 'alamat', 'address', 'domisili']);
        $message = custom_form_first_value($values, ['pesan', 'message', 'catatan', 'notes', 'note', 'catatan_order', 'keterangan']);

        $labels = [];
        foreach ((array)($form['fields'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = custom_form_field_key((string)($field['key'] ?? ''), '');
            if ($key === '') {
                continue;
            }
            $labels[$key] = custom_form_clean_text($field['label'] ?? ucwords(str_replace('_', ' ', $key)), 90);
        }

        $customValues = [];
        $customLabels = [];
        $contactKeys = [
            'nama', 'name', 'nama_lengkap', 'full_name', 'customer_name',
            'whatsapp', 'wa', 'phone', 'telepon', 'no_hp', 'nomor_whatsapp', 'nomor_hp',
            'email', 'alamat_email',
        ];

        foreach ($values as $rawKey => $value) {
            $key = custom_form_field_key((string)$rawKey, '');
            if ($key === '' || in_array($key, $contactKeys, true)) {
                continue;
            }
            $customValues[$key] = $value;
            $customLabels[$key] = $labels[$key] ?? ucwords(str_replace(['_', '-'], ' ', $key));
        }

        $formTitle = custom_form_clean_text($entry['form_title'] ?? $form['title'] ?? 'Form Custom', 120);
        $formSlug = custom_form_slug((string)($entry['form_slug'] ?? $form['slug'] ?? ''), '');
        $sourceType = custom_form_clean_text($entry['source_type'] ?? 'standalone_form', 80);
        $sourceLabel = custom_form_clean_text($entry['source_label'] ?? '', 120);
        $sourceUrl = custom_form_clean_text($entry['source_url'] ?? '', 360);
        $submissionId = custom_form_clean_text($entry['id'] ?? '', 80);

        $payload = [
            'name' => $name !== '' ? $name : 'Customer',
            'phone' => $phone,
            'email' => $email,
            'consent_contact' => !empty($entry['consent']) ? '1' : '',
            'need' => $need !== '' ? $need : $formTitle,
            'location' => $location,
            'message' => $message,
            'custom_fields' => $customValues,
            'custom_labels' => $customLabels,
            'source' => $sourceType === 'landing_page' ? 'custom-form-landing-page' : 'custom-form',
            'category' => custom_form_clean_text($entry['form_type'] ?? $form['type'] ?? 'custom', 80),
            'intent' => 'custom-form-submit',
            'label' => $sourceLabel !== '' ? $sourceLabel : $formTitle,
            'lp_form_name' => $formTitle,
            'item_title' => $formTitle,
            'item_url' => $sourceUrl,
            'landing_page_slug' => custom_form_clean_text($entry['source_landing_page_slug'] ?? '', 120),
            'landing_page_id' => custom_form_clean_text($entry['source_landing_page_id'] ?? '', 80),
            'page_path' => $sourceUrl,
            'lead_tags' => array_values(array_filter([
                'custom-form',
                $formSlug !== '' ? 'form-' . $formSlug : '',
                $sourceType === 'landing_page' ? 'landing-page' : 'standalone-form',
            ])),
        ];

        $inquiry = inquiry_normalize_payload($payload);
        if ($submissionId !== '') {
            $safeRef = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $submissionId) ?: '';
            $safeRef = trim($safeRef, '-_');
            if ($safeRef !== '') {
                $inquiry['id'] = 'inq_' . substr($safeRef, 0, 72);
            }
            $inquiry['source_submission_id'] = $submissionId;
        }
        $inquiry['source_form_id'] = custom_form_clean_text($entry['form_id'] ?? $form['id'] ?? '', 80);
        $inquiry['source_form_slug'] = $formSlug;

        $createdAt = (string)($entry['created_at'] ?? '');
        if ($createdAt !== '' && strtotime($createdAt) !== false) {
            $inquiry['time'] = $createdAt;
        }

        return inquiry_store($inquiry, true);
    }
}

if (!function_exists('custom_form_submit')) {
    function custom_form_submit(array $post): array
    {
        if (custom_form_is_rate_limited()) {
            throw new RuntimeException('Terlalu banyak pengiriman form. Coba lagi beberapa saat.');
        }

        $slug = custom_form_slug((string)($post['form_slug'] ?? ''), '');
        $form = custom_form_find($slug, true);
        if (!$form) {
            throw new RuntimeException('Form belum aktif atau tidak ditemukan.');
        }

        if (!empty($post['website_url'])) {
            throw new RuntimeException('Form belum bisa diproses.');
        }

        if (!verify_csrf()) {
            throw new RuntimeException('Sesi form sudah kedaluwarsa. Muat ulang halaman lalu kirim kembali.');
        }

        $values = [];
        $files = [];
        $summaryParts = [];

        foreach ((array)($form['fields'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = custom_form_field_key((string)($field['key'] ?? 'field'));
            $label = custom_form_clean_text($field['label'] ?? $key, 90);
            $type = (string)($field['type'] ?? 'text');
            $required = !empty($field['required']);
            $inputName = 'field_' . $key;

            if ($type === 'file') {
                $uploaded = custom_form_handle_file_upload($inputName);
                if ($required && !$uploaded) {
                    throw new RuntimeException($label . ' wajib diupload.');
                }
                if ($uploaded) {
                    $files[$key] = array_merge(['label' => $label], $uploaded);
                    $values[$key] = $uploaded['url'];
                    $summaryParts[] = $label . ': ' . $uploaded['name'];
                }
                continue;
            }

            $raw = $post[$inputName] ?? '';
            if ($type === 'checkbox') {
                $rawItems = is_array($raw) ? $raw : (($raw !== '') ? [$raw] : []);
                $allowed = array_map(static fn($option): string => (string)$option, (array)($field['options'] ?? []));
                $selected = [];
                foreach ($rawItems as $item) {
                    $item = custom_form_clean_text($item, 120);
                    if ($item !== '' && (!$allowed || in_array($item, $allowed, true))) {
                        $selected[] = $item;
                    }
                }
                if ($required && !$selected) {
                    throw new RuntimeException($label . ' wajib dipilih.');
                }
                $values[$key] = $selected;
                if ($selected) {
                    $summaryParts[] = $label . ': ' . implode(', ', $selected);
                }
                continue;
            }

            $value = $type === 'textarea' ? custom_form_clean_multiline($raw, 1600) : custom_form_clean_text($raw, 220);
            if ($required && $value === '') {
                throw new RuntimeException($label . ' wajib diisi.');
            }

            if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Format email belum valid.');
            }

            if ($type === 'number' && $value !== '' && !is_numeric(str_replace(',', '.', $value))) {
                throw new RuntimeException($label . ' harus berupa angka.');
            }

            if ($type === 'date' && $value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                throw new RuntimeException($label . ' harus berupa tanggal yang valid.');
            }

            if (in_array($type, ['select', 'radio'], true)) {
                $allowed = array_map(static fn($option): string => (string)$option, (array)($field['options'] ?? []));
                if ($value !== '' && $allowed && !in_array($value, $allowed, true)) {
                    throw new RuntimeException($label . ' belum sesuai pilihan yang tersedia.');
                }
            }

            $values[$key] = $value;
            if ($value !== '') {
                $summaryParts[] = $label . ': ' . str_replace("\n", ' ', $value);
            }
        }

        $consent = !empty($post['consent_contact']);
        if (!$consent && trim((string)($form['consent_text'] ?? '')) !== '') {
            throw new RuntimeException('Mohon centang persetujuan agar admin bisa menghubungi Anda.');
        }

        $entry = [
            'id' => 'cfs_' . date('YmdHis') . '_' . substr(hash('sha256', json_encode($values) . microtime(true)), 0, 8),
            'form_id' => (string)($form['id'] ?? ''),
            'form_slug' => (string)($form['slug'] ?? ''),
            'form_title' => (string)($form['title'] ?? 'Form Custom'),
            'form_type' => (string)($form['type'] ?? 'custom'),
            'values' => $values,
            'files' => $files,
            'summary' => custom_form_clean_multiline(implode("\n", $summaryParts), 2000),
            'consent' => $consent,
            'source_url' => custom_form_clean_text($post['source_url'] ?? current_url(), 360),
            'source_type' => custom_form_clean_text($post['source_type'] ?? 'standalone_form', 80),
            'source_label' => custom_form_clean_text($post['source_label'] ?? '', 160),
            'source_landing_page_id' => custom_form_clean_text($post['source_landing_page_id'] ?? '', 80),
            'source_landing_page_slug' => custom_form_clean_text($post['source_landing_page_slug'] ?? '', 120),
            'ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'user_agent' => custom_form_clean_text($_SERVER['HTTP_USER_AGENT'] ?? '', 240),
            'created_at' => date('c'),
        ];

        $mysqlOk = false;
        $mysqlActive = function_exists('storage_mysql_enabled') && storage_mysql_enabled('form_submissions');
        if ($mysqlActive && function_exists('storage_adapter_mysql_append_form_submission')) {
            $mysqlOk = storage_adapter_mysql_append_form_submission($entry);
        }

        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }

        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fileOk = $encoded !== false && @file_put_contents(custom_form_submission_file(), $encoded . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
        if (!$fileOk && (!$mysqlActive || !$mysqlOk || !(function_exists('storage_adapter_safe_fallback_enabled') && storage_adapter_safe_fallback_enabled()))) {
            throw new RuntimeException('Data form belum berhasil disimpan. Cek permission folder logs atau koneksi database.');
        }

        custom_form_touch_rate_limit();

        $inquiryBridge = custom_form_bridge_to_inquiry($form, $entry);

        if (function_exists('activity_log_record')) {
            activity_log_record('create', 'custom_form_submission', (string)$entry['id'], 'Form custom masuk.', [
                'form' => $entry['form_title'],
                'inquiry_bridge' => $inquiryBridge === true ? 'stored' : ($inquiryBridge === false ? 'failed' : 'skipped'),
            ]);
        }

        custom_form_dispatch_integrations($form, $entry);

        return ['form' => $form, 'entry' => $entry];
    }
}

if (!function_exists('custom_form_submission_files')) {
    function custom_form_submission_files(): array
    {
        $files = glob(LOGS_PATH . '/custom-form-submissions-*.jsonl') ?: [];
        rsort($files);
        return $files;
    }
}

if (!function_exists('custom_form_read_submissions')) {
    function custom_form_read_submissions(array $filters = [], int $limit = 300): array
    {
        $limit = max(1, min(5000, $limit));
        $items = [];
        $formSlug = custom_form_slug((string)($filters['form_slug'] ?? ''), '');
        $search = strtolower(custom_form_clean_text($filters['search'] ?? '', 120));
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($filters['date_from'] ?? '')) ? (string)$filters['date_from'] : '';
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($filters['date_to'] ?? '')) ? (string)$filters['date_to'] : '';
        $startTs = $dateFrom !== '' ? (strtotime($dateFrom . ' 00:00:00') ?: 0) : 0;
        $endTs = $dateTo !== '' ? (strtotime($dateTo . ' 23:59:59') ?: PHP_INT_MAX) : PHP_INT_MAX;

        if (function_exists('storage_adapter_mysql_read_form_submissions') && function_exists('storage_mysql_enabled') && storage_mysql_enabled('form_submissions')) {
            $mysqlItems = storage_adapter_mysql_read_form_submissions($filters, $limit);
            if (is_array($mysqlItems)) {
                return $mysqlItems;
            }
        }

        foreach (custom_form_submission_files() as $file) {
            $handle = @fopen($file, 'rb');
            if (!$handle) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                if (!is_array($row)) {
                    continue;
                }
                $ts = strtotime((string)($row['created_at'] ?? '')) ?: 0;
                if ($ts < $startTs || $ts > $endTs) {
                    continue;
                }
                if ($formSlug !== '' && (string)($row['form_slug'] ?? '') !== $formSlug) {
                    continue;
                }
                if ($search !== '') {
                    $haystack = strtolower(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                    if (!str_contains($haystack, $search)) {
                        continue;
                    }
                }
                $items[] = $row;
                if (count($items) >= $limit) {
                    break 2;
                }
            }
            fclose($handle);
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return $items;
    }
}


if (!function_exists('custom_form_submission_contact')) {
    function custom_form_submission_contact(array $row): array
    {
        $values = is_array($row['values'] ?? null) ? (array)$row['values'] : [];
        $name = custom_form_first_value($values, ['nama', 'name', 'nama_lengkap', 'full_name', 'customer_name']);
        $phone = custom_form_first_value($values, ['whatsapp', 'wa', 'phone', 'telepon', 'no_hp', 'nomor_whatsapp', 'nomor_hp']);
        $email = custom_form_first_value($values, ['email', 'alamat_email']);
        $need = custom_form_first_value($values, ['kebutuhan', 'need', 'pesan', 'message', 'catatan', 'catatan_order']);

        return [
            'name' => $name !== '' ? $name : 'Customer',
            'phone' => $phone,
            'email' => $email,
            'need' => $need,
        ];
    }
}

if (!function_exists('custom_form_pretty_field_label')) {
    function custom_form_pretty_field_label(string $key): string
    {
        $key = trim(str_replace(['_', '-'], ' ', $key));
        if ($key === '') {
            return 'Data';
        }
        $map = [
            'nama' => 'Nama',
            'name' => 'Nama',
            'whatsapp' => 'WhatsApp',
            'wa' => 'WhatsApp',
            'phone' => 'Telepon',
            'telepon' => 'Telepon',
            'email' => 'Email',
            'pesan' => 'Pesan',
            'message' => 'Pesan',
            'catatan' => 'Catatan',
            'kebutuhan' => 'Kebutuhan',
        ];
        $lower = strtolower($key);
        return $map[$lower] ?? ucwords($key);
    }
}

if (!function_exists('custom_form_submission_source_label')) {
    function custom_form_submission_source_label(array $row): string
    {
        $type = custom_form_clean_text($row['source_type'] ?? '', 80);
        $label = custom_form_clean_text($row['source_label'] ?? '', 160);
        $formTitle = custom_form_clean_text($row['form_title'] ?? 'Form Custom', 160);
        $slug = custom_form_clean_text($row['source_landing_page_slug'] ?? '', 120);

        if ($type === 'landing_page') {
            return $label !== '' ? 'Landing Page: ' . $label : ($slug !== '' ? 'Landing Page: ' . $slug : 'Landing Page');
        }
        if ($type === 'homepage') {
            return 'Beranda Website';
        }
        if ($type === 'checkout') {
            return 'Halaman Checkout';
        }
        return 'Halaman ' . $formTitle;
    }
}

if (!function_exists('custom_form_submission_action_urls')) {
    function custom_form_submission_action_urls(array $row): array
    {
        $contact = custom_form_submission_contact($row);
        $phone = preg_replace('/\D+/', '', (string)$contact['phone']) ?: '';
        if ($phone !== '') {
            if (function_exists('marketing_integration_phone_for_fonnte')) {
                $phone = marketing_integration_phone_for_fonnte($phone);
            } elseif (str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
            }
        }
        $waText = 'Halo ' . ($contact['name'] !== '' ? $contact['name'] : 'Kak') . ', saya admin ' . SITE_NAME . '. Saya ingin follow-up data form yang sudah Anda kirim.';
        $sourceUrl = custom_form_clean_text($row['source_url'] ?? '', 360);
        return [
            'whatsapp' => $phone !== '' ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($waText) : '',
            'email' => filter_var((string)$contact['email'], FILTER_VALIDATE_EMAIL) ? 'mailto:' . $contact['email'] . '?subject=' . rawurlencode('Follow-up dari ' . SITE_NAME) : '',
            'source' => $sourceUrl,
        ];
    }
}

if (!function_exists('custom_form_submission_integration_logs')) {
    function custom_form_submission_integration_logs(array $row, int $limit = 8): array
    {
        $ref = (string)($row['id'] ?? '');
        if ($ref === '') {
            return [];
        }
        $logs = [];
        if (function_exists('marketing_integration_recent_logs')) {
            foreach (marketing_integration_recent_logs(250) as $log) {
                $meta = is_array($log['meta'] ?? null) ? (array)$log['meta'] : [];
                if ((string)($meta['ref'] ?? '') === $ref) {
                    $logs[] = [
                        'channel' => custom_form_clean_text($log['provider'] ?? 'otomatis', 60),
                        'type' => custom_form_clean_text($log['type'] ?? '', 80),
                        'status' => custom_form_clean_text($log['status'] ?? 'info', 40),
                        'message' => custom_form_clean_text($log['message'] ?? '', 220),
                        'time' => custom_form_clean_text($log['time'] ?? '', 80),
                    ];
                }
                if (count($logs) >= $limit) {
                    break;
                }
            }
        }
        if (count($logs) < $limit && function_exists('notification_read_all')) {
            foreach (notification_read_all(3650, ['search' => $ref], 100) as $event) {
                if ((string)($event['target_ref'] ?? '') !== $ref) {
                    continue;
                }
                $logs[] = [
                    'channel' => 'email',
                    'type' => custom_form_clean_text($event['type'] ?? '', 80),
                    'status' => custom_form_clean_text($event['status'] ?? 'info', 40),
                    'message' => custom_form_clean_text($event['error'] ?? $event['subject'] ?? '', 220),
                    'time' => custom_form_clean_text($event['time'] ?? '', 80),
                ];
                if (count($logs) >= $limit) {
                    break;
                }
            }
        }
        return $logs;
    }
}

if (!function_exists('custom_form_stats')) {
    function custom_form_stats(): array
    {
        $forms = custom_form_read_forms();
        $submissions = custom_form_read_submissions([], 5000);
        $active = count(array_filter($forms, static fn(array $form): bool => (string)($form['status'] ?? '') === 'active'));
        $today = date('Y-m-d');
        $todayCount = count(array_filter($submissions, static fn(array $row): bool => str_starts_with((string)($row['created_at'] ?? ''), $today)));
        return [
            'forms' => count($forms),
            'active_forms' => $active,
            'submissions' => count($submissions),
            'today' => $todayCount,
        ];
    }
}

if (!function_exists('custom_form_render_field')) {
    function custom_form_render_field(array $field): void
    {
        $key = custom_form_field_key((string)($field['key'] ?? 'field'));
        $label = custom_form_clean_text($field['label'] ?? $key, 90);
        $type = (string)($field['type'] ?? 'text');
        $required = !empty($field['required']);
        $placeholder = custom_form_clean_text($field['placeholder'] ?? '', 120);
        $help = custom_form_clean_text($field['help'] ?? '', 180);
        $inputName = 'field_' . $key;
        $id = 'custom-form-' . $key . '-' . substr(hash('sha256', $label . $inputName), 0, 8);
        $requiredAttr = $required ? ' required' : '';

        echo '<label class="custom-form-field custom-form-field--' . esc($type) . '" for="' . esc($id) . '">';
        echo '<span>' . esc($label) . ($required ? ' <b>*</b>' : '') . '</span>';

        if ($type === 'textarea') {
            echo '<textarea id="' . esc($id) . '" name="' . esc($inputName) . '" rows="4" placeholder="' . esc($placeholder) . '"' . $requiredAttr . '></textarea>';
        } elseif ($type === 'select') {
            echo '<select id="' . esc($id) . '" name="' . esc($inputName) . '"' . $requiredAttr . '><option value="">Pilih salah satu</option>';
            foreach ((array)($field['options'] ?? []) as $option) {
                echo '<option value="' . esc((string)$option) . '">' . esc((string)$option) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'radio') {
            echo '<div class="custom-form-choice-group">';
            foreach ((array)($field['options'] ?? []) as $optionIndex => $option) {
                $choiceId = $id . '-' . $optionIndex;
                echo '<label class="custom-form-choice" for="' . esc($choiceId) . '"><input id="' . esc($choiceId) . '" type="radio" name="' . esc($inputName) . '" value="' . esc((string)$option) . '"' . $requiredAttr . '><span>' . esc((string)$option) . '</span></label>';
            }
            echo '</div>';
        } elseif ($type === 'checkbox') {
            echo '<div class="custom-form-choice-group">';
            foreach ((array)($field['options'] ?? []) as $optionIndex => $option) {
                $choiceId = $id . '-' . $optionIndex;
                echo '<label class="custom-form-choice" for="' . esc($choiceId) . '"><input id="' . esc($choiceId) . '" type="checkbox" name="' . esc($inputName) . '[]" value="' . esc((string)$option) . '"><span>' . esc((string)$option) . '</span></label>';
            }
            echo '</div>';
        } elseif ($type === 'file') {
            echo '<input id="' . esc($id) . '" type="file" name="' . esc($inputName) . '" accept=".jpg,.jpeg,.png,.webp,.pdf,.zip"' . $requiredAttr . '>';
            echo '<small>Format: JPG, PNG, WebP, PDF, ZIP. Maksimal 5MB.</small>';
        } else {
            $htmlType = match ($type) {
                'email' => 'email',
                'phone' => 'tel',
                'number' => 'number',
                'date' => 'date',
                default => 'text',
            };
            echo '<input id="' . esc($id) . '" type="' . esc($htmlType) . '" name="' . esc($inputName) . '" placeholder="' . esc($placeholder) . '"' . $requiredAttr . '>';
        }

        if ($help !== '') {
            echo '<small>' . esc($help) . '</small>';
        }

        echo '</label>';
    }
}

if (!function_exists('custom_form_render')) {
    function custom_form_render(string $slug, array $options = []): void
    {
        $form = custom_form_find($slug, true);
        if (!$form) {
            echo '<div class="custom-form custom-form--empty"><p>Form belum tersedia.</p></div>';
            return;
        }

        $title = (string)($options['title'] ?? $form['title']);
        $description = (string)($options['description'] ?? $form['description']);
        $showHeader = (bool)($options['show_header'] ?? true);
        $cardClass = custom_form_clean_text($options['class'] ?? '', 80);
        $submittedForm = custom_form_slug(is_string($_GET['submitted_form'] ?? null) ? (string)$_GET['submitted_form'] : '', '');
        $feedbackSuccess = '';
        $feedbackError = '';

        if ($submittedForm !== '' && hash_equals((string)$form['slug'], $submittedForm)) {
            $feedbackSuccess = custom_form_clean_text(is_string($_GET['success'] ?? null) ? (string)$_GET['success'] : '', 500);
            $feedbackError = custom_form_clean_text(is_string($_GET['error'] ?? null) ? (string)$_GET['error'] : '', 500);
        }

        echo '<section class="custom-form-card ' . esc($cardClass) . '" id="form-' . esc((string)$form['slug']) . '">';
        if ($showHeader) {
            echo '<div class="custom-form-head"><span class="custom-form-badge">Form</span><h2>' . esc($title) . '</h2>';
            if ($description !== '') {
                echo '<p>' . esc($description) . '</p>';
            }
            echo '</div>';
        }

        if ($feedbackSuccess !== '') {
            echo '<div class="form-message form-message--success" role="status" aria-live="polite">' . esc($feedbackSuccess) . '</div>';
        }
        if ($feedbackError !== '') {
            echo '<div class="form-message form-message--error" role="alert">' . esc($feedbackError) . '</div>';
        }

        echo '<form class="custom-form" method="post" action="' . esc(url('form-submit')) . '" enctype="multipart/form-data" data-custom-form="1">';
        echo csrf_field();
        echo '<input type="hidden" name="form_slug" value="' . esc((string)$form['slug']) . '">';
        echo '<input type="hidden" name="source_url" value="' . esc((string)($options['source_url'] ?? current_url())) . '">';
        echo '<input type="hidden" name="source_type" value="' . esc((string)($options['source_type'] ?? 'standalone_form')) . '">';
        echo '<input type="hidden" name="source_label" value="' . esc((string)($options['source_label'] ?? $title)) . '">';
        echo '<input type="hidden" name="source_landing_page_id" value="' . esc((string)($options['source_landing_page_id'] ?? '')) . '">';
        echo '<input type="hidden" name="source_landing_page_slug" value="' . esc((string)($options['source_landing_page_slug'] ?? '')) . '">';
        echo '<input class="custom-form-hp" type="text" name="website_url" value="" tabindex="-1" autocomplete="off" aria-hidden="true">';
        echo '<div class="custom-form-grid">';
        foreach ((array)($form['fields'] ?? []) as $field) {
            if (is_array($field)) {
                custom_form_render_field($field);
            }
        }
        echo '</div>';

        $consent = trim((string)($form['consent_text'] ?? ''));
        if ($consent !== '') {
            echo '<label class="custom-form-consent"><input type="checkbox" name="consent_contact" value="1" required><span>' . esc($consent) . '</span></label>';
        }

        echo '<div class="custom-form-actions"><button class="btn btn-primary custom-form-submit" type="submit">' . esc((string)($form['submit_label'] ?? 'Kirim Form')) . '</button></div>';
        echo '</form></section>';
    }
}
