<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| PAYMENT SETTINGS ENGINE - Template
|--------------------------------------------------------------------------
| File-based manual payment profile for transfer bank, QRIS manual, cash,
| and custom invoice instructions. This is not a payment gateway yet; it is
| a safe foundation for UMKM payment settings and public invoice clarity.
|--------------------------------------------------------------------------
*/

if (!function_exists('payment_settings_file')) {
    function payment_settings_file(): string
    {
        return STORAGE_PATH . '/payment-settings.json';
    }
}

if (!function_exists('payment_clean')) {
    function payment_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('payment_multiline_clean')) {
    function payment_multiline_clean(string $value, int $max = 1200): string
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

if (!function_exists('payment_bool_from_post')) {
    function payment_bool_from_post(array $source, string $key): bool
    {
        return !empty($source[$key]);
    }
}

if (!function_exists('payment_default_settings')) {
    function payment_default_settings(): array
    {
        $bankName = payment_clean((string)($_ENV['PAYMENT_BANK_NAME'] ?? ''), 80);
        $accountNumber = payment_clean((string)($_ENV['PAYMENT_ACCOUNT_NUMBER'] ?? ''), 80);
        $accountHolder = payment_clean((string)($_ENV['PAYMENT_ACCOUNT_HOLDER'] ?? ''), 120);
        $qrisNote = payment_multiline_clean((string)($_ENV['PAYMENT_QRIS_NOTE'] ?? ''), 500);
        $publicLabel = payment_clean((string)($_ENV['PAYMENT_PUBLIC_LABEL'] ?? 'Transfer/QRIS setelah konfirmasi admin'), 120);

        $bankAccounts = [];
        if ($bankName !== '' || $accountNumber !== '' || $accountHolder !== '') {
            $bankAccounts[] = [
                'id' => 'bank-1',
                'enabled' => true,
                'label' => $bankName !== '' ? $bankName : 'Transfer Bank',
                'bank_name' => $bankName,
                'account_number' => $accountNumber,
                'account_holder' => $accountHolder,
                'note' => 'Mohon kirim bukti transfer melalui WhatsApp admin setelah pembayaran.',
            ];
        }

        return [
            'version' => 'v29.17',
            'updated_at' => '',
            'public_label' => $publicLabel,
            'default_due_days' => max(1, min(30, (int)($_ENV['PAYMENT_DEFAULT_DUE_DAYS'] ?? 3))),
            'default_instruction' => payment_multiline_clean((string)($_ENV['PAYMENT_DEFAULT_INSTRUCTION'] ?? 'Admin akan mengonfirmasi stok, jadwal, nominal, dan metode pembayaran yang paling sesuai sebelum pembayaran dilakukan.'), 900),
            'default_public_note' => payment_multiline_clean((string)($_ENV['PAYMENT_DEFAULT_PUBLIC_NOTE'] ?? 'Invoice ini adalah draft instruksi pembayaran manual. Pembayaran dianggap valid setelah admin mengonfirmasi dana/bukti pembayaran.'), 700),
            'bank_accounts' => $bankAccounts,
            'qris' => [
                'enabled' => $qrisNote !== '',
                'label' => 'QRIS Manual',
                'image_url' => payment_clean((string)($_ENV['PAYMENT_QRIS_IMAGE_URL'] ?? ''), 240),
                'note' => $qrisNote,
            ],
            'cash' => [
                'enabled' => true,
                'label' => 'Tunai Saat Survey/Kirim',
                'note' => 'Pembayaran tunai dapat dikonfirmasi langsung dengan admin sesuai jadwal survey, pengiriman, atau layanan.',
            ],
            'custom' => [
                'enabled' => true,
                'label' => 'Instruksi Custom Admin',
                'note' => 'Admin dapat menulis instruksi pembayaran khusus pada invoice order.',
            ],
        ];
    }
}

if (!function_exists('payment_normalize_settings')) {
    function payment_normalize_settings(array $settings): array
    {
        $default = payment_default_settings();
        $settings = array_replace_recursive($default, $settings);

        $settings['version'] = 'v29.17';
        $settings['public_label'] = payment_clean((string)($settings['public_label'] ?? $default['public_label']), 120);
        $settings['default_due_days'] = max(1, min(30, (int)($settings['default_due_days'] ?? 3)));
        $settings['default_instruction'] = payment_multiline_clean((string)($settings['default_instruction'] ?? ''), 900);
        $settings['default_public_note'] = payment_multiline_clean((string)($settings['default_public_note'] ?? ''), 700);

        $bankAccounts = [];
        foreach ((array)($settings['bank_accounts'] ?? []) as $index => $account) {
            if (!is_array($account)) {
                continue;
            }
            $bank = payment_clean((string)($account['bank_name'] ?? ''), 80);
            $number = payment_clean((string)($account['account_number'] ?? ''), 80);
            $holder = payment_clean((string)($account['account_holder'] ?? ''), 120);
            $label = payment_clean((string)($account['label'] ?? $bank ?: 'Transfer Bank'), 80);
            $note = payment_multiline_clean((string)($account['note'] ?? ''), 400);
            if ($bank === '' && $number === '' && $holder === '' && $note === '') {
                continue;
            }
            $bankAccounts[] = [
                'id' => payment_clean((string)($account['id'] ?? 'bank-' . ($index + 1)), 40) ?: 'bank-' . ($index + 1),
                'enabled' => !empty($account['enabled']),
                'label' => $label !== '' ? $label : ($bank !== '' ? $bank : 'Transfer Bank'),
                'bank_name' => $bank,
                'account_number' => $number,
                'account_holder' => $holder,
                'note' => $note,
            ];
        }
        $settings['bank_accounts'] = $bankAccounts;

        $settings['qris'] = [
            'enabled' => !empty($settings['qris']['enabled']),
            'label' => payment_clean((string)($settings['qris']['label'] ?? 'QRIS Manual'), 80) ?: 'QRIS Manual',
            'image_url' => payment_clean((string)($settings['qris']['image_url'] ?? ''), 240),
            'note' => payment_multiline_clean((string)($settings['qris']['note'] ?? ''), 600),
        ];
        $settings['cash'] = [
            'enabled' => !empty($settings['cash']['enabled']),
            'label' => payment_clean((string)($settings['cash']['label'] ?? 'Tunai Saat Survey/Kirim'), 80) ?: 'Tunai Saat Survey/Kirim',
            'note' => payment_multiline_clean((string)($settings['cash']['note'] ?? ''), 500),
        ];
        $settings['custom'] = [
            'enabled' => !empty($settings['custom']['enabled']),
            'label' => payment_clean((string)($settings['custom']['label'] ?? 'Instruksi Custom Admin'), 80) ?: 'Instruksi Custom Admin',
            'note' => payment_multiline_clean((string)($settings['custom']['note'] ?? ''), 500),
        ];

        return $settings;
    }
}

if (!function_exists('payment_read_settings')) {
    function payment_read_settings(): array
    {
        $file = payment_settings_file();
        if (!is_file($file)) {
            return payment_normalize_settings(payment_default_settings());
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return payment_normalize_settings(is_array($data) ? $data : []);
    }
}

if (!function_exists('payment_write_settings')) {
    function payment_write_settings(array $settings): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        $settings = payment_normalize_settings($settings);
        $settings['updated_at'] = date('c');
        $ok = @file_put_contents(
            payment_settings_file(),
            json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            LOCK_EX
        ) !== false;
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('update', 'payment_settings', null, 'Payment settings diperbarui.', [
                'qris_enabled' => $settings['qris_enabled'] ?? null,
                'profiles_count' => is_array($settings['profiles'] ?? null) ? count($settings['profiles']) : 0,
                'default_profile' => $settings['default_profile'] ?? '',
            ]);
        }
        return $ok;
    }
}

if (!function_exists('payment_profiles')) {
    function payment_profiles(): array
    {
        $settings = payment_read_settings();
        $profiles = [];
        foreach ((array)$settings['bank_accounts'] as $index => $account) {
            if (empty($account['enabled'])) {
                continue;
            }
            $id = 'bank:' . payment_clean((string)($account['id'] ?? 'bank-' . ($index + 1)), 40);
            $profiles[$id] = [
                'id' => $id,
                'type' => 'bank',
                'label' => payment_clean((string)($account['label'] ?? 'Transfer Bank'), 80),
                'title' => trim((string)($account['bank_name'] ?? '') . ' ' . (string)($account['account_number'] ?? '')),
                'lines' => array_values(array_filter([
                    !empty($account['bank_name']) ? 'Bank: ' . (string)$account['bank_name'] : '',
                    !empty($account['account_number']) ? 'No. Rekening: ' . (string)$account['account_number'] : '',
                    !empty($account['account_holder']) ? 'Atas Nama: ' . (string)$account['account_holder'] : '',
                    !empty($account['note']) ? (string)$account['note'] : '',
                ])),
                'raw' => $account,
            ];
        }
        if (!empty($settings['qris']['enabled'])) {
            $profiles['qris'] = [
                'id' => 'qris',
                'type' => 'qris',
                'label' => (string)$settings['qris']['label'],
                'title' => (string)$settings['qris']['label'],
                'lines' => array_values(array_filter([
                    (string)($settings['qris']['note'] ?? ''),
                    'Setelah scan QRIS, kirim bukti pembayaran melalui WhatsApp admin.',
                ])),
                'image_url' => (string)($settings['qris']['image_url'] ?? ''),
                'raw' => $settings['qris'],
            ];
        }
        if (!empty($settings['cash']['enabled'])) {
            $profiles['cash'] = [
                'id' => 'cash',
                'type' => 'cash',
                'label' => (string)$settings['cash']['label'],
                'title' => (string)$settings['cash']['label'],
                'lines' => array_values(array_filter([(string)($settings['cash']['note'] ?? '')])),
                'raw' => $settings['cash'],
            ];
        }
        if (!empty($settings['custom']['enabled'])) {
            $profiles['custom'] = [
                'id' => 'custom',
                'type' => 'custom',
                'label' => (string)$settings['custom']['label'],
                'title' => (string)$settings['custom']['label'],
                'lines' => array_values(array_filter([(string)($settings['custom']['note'] ?? '')])),
                'raw' => $settings['custom'],
            ];
        }
        return $profiles;
    }
}

if (!function_exists('payment_profile_options')) {
    function payment_profile_options(): array
    {
        $options = ['' => 'Pilih Metode Manual'];
        foreach (payment_profiles() as $id => $profile) {
            $options[$id] = (string)($profile['label'] ?? $id);
        }
        return $options;
    }
}

if (!function_exists('payment_find_profile')) {
    function payment_find_profile(string $id): ?array
    {
        $id = payment_clean($id, 80);
        if ($id === '') {
            return null;
        }
        $profiles = payment_profiles();
        return $profiles[$id] ?? null;
    }
}

if (!function_exists('payment_first_profile_id')) {
    function payment_first_profile_id(): string
    {
        $profiles = payment_profiles();
        $first = array_key_first($profiles);
        return is_string($first) ? $first : '';
    }
}

if (!function_exists('payment_profile_label')) {
    function payment_profile_label(string $id): string
    {
        $profile = payment_find_profile($id);
        return $profile ? (string)($profile['label'] ?? $id) : '';
    }
}

if (!function_exists('payment_instruction_from_profile')) {
    function payment_instruction_from_profile(?array $profile): string
    {
        if (!$profile) {
            $settings = payment_read_settings();
            return payment_multiline_clean((string)($settings['default_instruction'] ?? ''), 1000);
        }
        $lines = [];
        foreach ((array)($profile['lines'] ?? []) as $line) {
            $line = payment_multiline_clean((string)$line, 400);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return payment_multiline_clean(implode("\n", $lines), 1200);
    }
}

if (!function_exists('payment_order_profile_id')) {
    function payment_order_profile_id(array $order): string
    {
        $stored = payment_clean((string)($order['invoice_payment_profile'] ?? ''), 80);
        if ($stored !== '' && payment_find_profile($stored)) {
            return $stored;
        }
        $channel = strtolower((string)($order['invoice_payment_channel'] ?? $order['payment_method'] ?? ''));
        foreach (payment_profiles() as $id => $profile) {
            if ($id !== '' && str_contains($channel, strtolower((string)($profile['label'] ?? $id)))) {
                return (string)$id;
            }
            if (($profile['type'] ?? '') === 'qris' && str_contains($channel, 'qris')) {
                return (string)$id;
            }
            if (($profile['type'] ?? '') === 'bank' && (str_contains($channel, 'transfer') || str_contains($channel, 'bank'))) {
                return (string)$id;
            }
            if (($profile['type'] ?? '') === 'cash' && (str_contains($channel, 'tunai') || str_contains($channel, 'survey'))) {
                return (string)$id;
            }
        }
        return payment_first_profile_id();
    }
}

if (!function_exists('payment_instruction_for_order')) {
    function payment_instruction_for_order(array $order): string
    {
        $profileId = payment_order_profile_id($order);
        $profile = $profileId !== '' ? payment_find_profile($profileId) : null;
        $instruction = payment_instruction_from_profile($profile);
        if ($instruction !== '') {
            return $instruction;
        }
        $settings = payment_read_settings();
        return (string)($settings['default_instruction'] ?? 'Admin akan mengirim instruksi pembayaran setelah nominal dikonfirmasi.');
    }
}

if (!function_exists('payment_public_note')) {
    function payment_public_note(): string
    {
        $settings = payment_read_settings();
        $note = payment_multiline_clean((string)($settings['default_public_note'] ?? ''), 700);
        return $note !== '' ? $note : 'Pembayaran dianggap valid setelah admin mengonfirmasi dana/bukti pembayaran.';
    }
}

if (!function_exists('payment_public_label')) {
    function payment_public_label(): string
    {
        $settings = payment_read_settings();
        $label = payment_clean((string)($settings['public_label'] ?? ''), 120);
        return $label !== '' ? $label : 'Transfer/QRIS setelah konfirmasi admin';
    }
}

if (!function_exists('payment_render_public_profile')) {
    function payment_render_public_profile(array $order): string
    {
        $profileId = payment_order_profile_id($order);
        $profile = $profileId !== '' ? payment_find_profile($profileId) : null;
        if (!$profile) {
            return '';
        }

        ob_start();
        ?>
        <div class="payment-public-profile--template">
            <h3><?= esc((string)($profile['label'] ?? 'Metode Pembayaran')); ?></h3>
            <?php if (($profile['type'] ?? '') === 'qris' && !empty($profile['image_url'])): ?>
                <div class="payment-public-qris--template"><img src="<?= esc((string)$profile['image_url']); ?>" alt="QRIS manual pembayaran"></div>
            <?php endif; ?>
            <ul>
                <?php foreach ((array)($profile['lines'] ?? []) as $line): ?>
                    <?php $line = payment_multiline_clean((string)$line, 400); if ($line === '') { continue; } ?>
                    <li><?= nl2br(esc($line)); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
