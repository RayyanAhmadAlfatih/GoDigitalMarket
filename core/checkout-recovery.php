<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| CHECKOUT RECOVERY / ABANDONED CHECKOUT HELPERS - Template
|--------------------------------------------------------------------------
| Turns unpaid checkout/order intent into a practical follow-up queue.
| This stays file-based and shared-hosting friendly while giving UMKM admins
| a clear action list, WhatsApp scripts, reminder scheduling, and recovery
| reporting. Anonymous checkout views are summarized only for insight.
|--------------------------------------------------------------------------
*/

if (!function_exists('checkout_recovery_clean')) {
    function checkout_recovery_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('checkout_recovery_multiline_clean')) {
    function checkout_recovery_multiline_clean(string $value, int $max = 1400): string
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

if (!function_exists('checkout_recovery_settings_file')) {
    function checkout_recovery_settings_file(): string
    {
        return STORAGE_PATH . '/checkout-recovery-settings.json';
    }
}

if (!function_exists('checkout_recovery_default_templates')) {
    function checkout_recovery_default_templates(): array
    {
        return [
            'first_nudge' => "Halo {name}, terima kasih sudah mengisi checkout di {site_name}.\n\nNo. Order: {order_ref}\nProduk: {product}\nTotal: {total}\n\nSaya bantu follow-up ya, apakah masih ingin dilanjutkan? Kalau ada kendala ongkir, pembayaran, atau detail produk, boleh langsung balas chat ini.",
            'payment_reminder' => "Halo {name}, izin mengingatkan pembayaran untuk order {order_ref}.\n\nProduk: {product}\nTotal: {total}\nStatus pembayaran: {payment_status}\n\nLink invoice: {invoice_url}\n\nJika sudah transfer, boleh kirim bukti pembayaran di chat ini agar admin bisa bantu proses lebih cepat.",
            'gateway_help' => "Halo {name}, saya bantu cek pembayaran otomatis untuk order {order_ref}.\n\nProduk: {product}\nTotal: {total}\n\nKalau tombol bayar sempat error atau belum sempat dibuka, ini link invoice/status ordernya:\n{invoice_url}\n\nBoleh kabari kendalanya ya, nanti admin bantu arahkan.",
            'shipping_question' => "Halo {name}, saya follow-up order {order_ref}.\n\nProduk: {product}\nTujuan: {destination}\nOngkir/estimasi: {shipping}\n\nApakah alamat dan pilihan pengiriman sudah sesuai? Kalau ingin dibantu cari opsi pengiriman terbaik, silakan balas chat ini ya.",
            'preorder_confirmation' => "Halo {name}, saya follow-up order pre-order {order_ref}.\n\nProduk: {product}\nCatatan PO: {preorder_note}\n\nApakah ingin lanjut kami proses sesuai estimasi pre-order? Jika ada tanggal kebutuhan khusus, boleh disampaikan ya.",
            'digital_pending' => "Halo {name}, saya follow-up akses produk digital untuk order {order_ref}.\n\nProduk: {product}\nStatus pembayaran: {payment_status}\n\nAkses digital akan dikirim setelah pembayaran tervalidasi. Jika sudah bayar, boleh kirim bukti pembayaran ya.",
            'last_call' => "Halo {name}, izin follow-up terakhir untuk order {order_ref}.\n\nProduk: {product}\nTotal: {total}\n\nKalau masih ingin lanjut, admin siap bantu proses. Kalau belum jadi juga tidak apa-apa, nanti order bisa kami arsipkan supaya stok/jadwal tidak tertahan.",
        ];
    }
}

if (!function_exists('checkout_recovery_default_settings')) {
    function checkout_recovery_default_settings(): array
    {
        return [
            'enabled' => true,
            'recovery_after_minutes' => 30,
            'hot_window_hours' => 24,
            'stale_after_days' => 7,
            'rate_limit_per_day' => 3,
            'auto_schedule_next' => true,
            'default_next_followup_hours' => 24,
            'whatsapp_intro' => 'Follow-up checkout dari website',
            'show_anonymous_intent' => true,
            'anonymous_days' => 7,
            'templates' => checkout_recovery_default_templates(),
        ];
    }
}

if (!function_exists('checkout_recovery_read_settings')) {
    function checkout_recovery_read_settings(): array
    {
        $defaults = checkout_recovery_default_settings();
        $file = checkout_recovery_settings_file();
        if (!is_file($file)) {
            return $defaults;
        }
        $data = json_decode((string)@file_get_contents($file), true);
        if (!is_array($data)) {
            return $defaults;
        }
        $data['templates'] = array_merge($defaults['templates'], is_array($data['templates'] ?? null) ? $data['templates'] : []);
        return array_merge($defaults, $data);
    }
}

if (!function_exists('checkout_recovery_write_settings')) {
    function checkout_recovery_write_settings(array $settings): bool
    {
        $defaults = checkout_recovery_default_settings();
        $templates = [];
        foreach (array_keys($defaults['templates']) as $key) {
            $templates[$key] = checkout_recovery_multiline_clean((string)($settings['templates'][$key] ?? $defaults['templates'][$key]), 1600);
        }
        $clean = [
            'enabled' => !empty($settings['enabled']),
            'recovery_after_minutes' => max(5, min(10080, (int)($settings['recovery_after_minutes'] ?? 30))),
            'hot_window_hours' => max(1, min(720, (int)($settings['hot_window_hours'] ?? 24))),
            'stale_after_days' => max(1, min(365, (int)($settings['stale_after_days'] ?? 7))),
            'rate_limit_per_day' => max(1, min(20, (int)($settings['rate_limit_per_day'] ?? 3))),
            'auto_schedule_next' => !empty($settings['auto_schedule_next']),
            'default_next_followup_hours' => max(1, min(720, (int)($settings['default_next_followup_hours'] ?? 24))),
            'whatsapp_intro' => checkout_recovery_clean((string)($settings['whatsapp_intro'] ?? $defaults['whatsapp_intro']), 180),
            'show_anonymous_intent' => !empty($settings['show_anonymous_intent']),
            'anonymous_days' => max(1, min(90, (int)($settings['anonymous_days'] ?? 7))),
            'templates' => $templates,
        ];
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        return @file_put_contents(checkout_recovery_settings_file(), json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}

if (!function_exists('checkout_recovery_order_paid')) {
    function checkout_recovery_order_paid(array $order): bool
    {
        $payment = (string)($order['payment_status'] ?? '');
        return in_array($payment, ['DP Masuk', 'Lunas', 'Tidak Perlu Payment'], true);
    }
}

if (!function_exists('checkout_recovery_order_closed')) {
    function checkout_recovery_order_closed(array $order): bool
    {
        $status = (string)($order['status'] ?? '');
        $payment = (string)($order['payment_status'] ?? '');
        if (in_array($status, ['Batal', 'Selesai', 'Spam'], true)) {
            return true;
        }
        if (in_array($payment, ['Refund'], true)) {
            return true;
        }
        return checkout_recovery_order_paid($order);
    }
}

if (!function_exists('checkout_recovery_order_age_minutes')) {
    function checkout_recovery_order_age_minutes(array $order): int
    {
        $ts = function_exists('order_event_timestamp') ? order_event_timestamp($order) : (strtotime((string)($order['time'] ?? '')) ?: 0);
        if ($ts <= 0) {
            return 0;
        }
        return max(0, (int)floor((time() - $ts) / 60));
    }
}

if (!function_exists('checkout_recovery_total')) {
    function checkout_recovery_total(array $order): int
    {
        if (function_exists('order_invoice_total')) {
            return order_invoice_total($order);
        }
        return ((int)($order['price'] ?? 0)) * max(1, (int)($order['quantity'] ?? 1));
    }
}

if (!function_exists('checkout_recovery_stage')) {
    function checkout_recovery_stage(array $order, array $settings = []): string
    {
        if (checkout_recovery_order_closed($order)) {
            return 'closed';
        }
        $ageMinutes = checkout_recovery_order_age_minutes($order);
        $grace = max(5, (int)($settings['recovery_after_minutes'] ?? 30));
        if ($ageMinutes < $grace) {
            return 'grace_period';
        }
        if (!empty($order['gateway_payment_url']) || !empty($order['gateway_provider'])) {
            return 'gateway_pending';
        }
        if ((string)($order['payment_status'] ?? '') === 'Menunggu Pembayaran') {
            return 'payment_pending';
        }
        if (!empty($order['preorder_enabled']) || !empty($order['preorder_note'])) {
            return 'preorder_pending';
        }
        if ((string)($order['shipping_required'] ?? 'yes') === 'yes' && ((string)($order['shipping_quote_source'] ?? '') === '' || (string)($order['shipping_method'] ?? '') === '')) {
            return 'shipping_question';
        }
        if (str_contains(strtolower((string)($order['commerce_payment_rule_label'] ?? $order['payment_method'] ?? '')), 'konsultasi')) {
            return 'consultation_needed';
        }
        return 'order_unpaid';
    }
}

if (!function_exists('checkout_recovery_stage_label')) {
    function checkout_recovery_stage_label(string $stage): string
    {
        return [
            'grace_period' => 'Baru Masuk',
            'gateway_pending' => 'Payment Gateway Pending',
            'payment_pending' => 'Menunggu Pembayaran',
            'preorder_pending' => 'Pre-order Perlu Konfirmasi',
            'shipping_question' => 'Perlu Bantu Ongkir',
            'consultation_needed' => 'Perlu Konsultasi',
            'order_unpaid' => 'Belum Closing',
            'closed' => 'Selesai/Closed',
        ][$stage] ?? 'Belum Closing';
    }
}

if (!function_exists('checkout_recovery_score_order')) {
    function checkout_recovery_score_order(array $order, string $stage, array $settings = []): int
    {
        if ($stage === 'closed') {
            return 0;
        }
        $score = 35;
        $ageMinutes = checkout_recovery_order_age_minutes($order);
        $hotWindow = max(1, (int)($settings['hot_window_hours'] ?? 24)) * 60;
        $total = checkout_recovery_total($order);
        if ((string)($order['phone'] ?? '') !== '') { $score += 18; }
        if ((string)($order['email'] ?? '') !== '') { $score += 5; }
        if ((string)($order['product_title'] ?? '') !== '') { $score += 8; }
        if ($total >= 500000) { $score += 14; } elseif ($total >= 100000) { $score += 8; } elseif ($total > 0) { $score += 4; }
        if (in_array($stage, ['gateway_pending', 'payment_pending'], true)) { $score += 18; }
        if (in_array($stage, ['preorder_pending', 'consultation_needed'], true)) { $score += 10; }
        if (!empty($order['shipping_total']) || !empty($order['shipping_estimated_total'])) { $score += 5; }
        if ($ageMinutes <= $hotWindow) { $score += 12; }
        if ($ageMinutes > (3 * 24 * 60)) { $score -= 10; }
        if ($ageMinutes > (7 * 24 * 60)) { $score -= 10; }
        return max(0, min(100, $score));
    }
}

if (!function_exists('checkout_recovery_priority_from_score')) {
    function checkout_recovery_priority_from_score(int $score): string
    {
        if ($score >= 85) { return 'Sangat Panas'; }
        if ($score >= 68) { return 'Tinggi'; }
        if ($score >= 42) { return 'Normal'; }
        return 'Rendah';
    }
}

if (!function_exists('checkout_recovery_latest_followup')) {
    function checkout_recovery_latest_followup(array $order): array
    {
        $id = checkout_recovery_clean((string)($order['id'] ?? ''), 100);
        if ($id === '' || !function_exists('crm_recent_for_target')) {
            return [];
        }
        $events = crm_recent_for_target('order', $id, 20);
        return $events[0] ?? [];
    }
}

if (!function_exists('checkout_recovery_followup_state')) {
    function checkout_recovery_followup_state(array $order): array
    {
        $latest = checkout_recovery_latest_followup($order);
        if (!$latest) {
            return ['label' => 'Belum pernah follow-up', 'state' => 'new', 'latest' => [], 'next_ts' => 0, 'count' => 0];
        }
        $events = function_exists('crm_recent_for_target') ? crm_recent_for_target('order', (string)($order['id'] ?? ''), 100) : [$latest];
        $outcome = (string)($latest['outcome'] ?? '');
        if (in_array($outcome, ['Deal', 'Tidak Jadi'], true)) {
            return ['label' => $outcome, 'state' => 'closed', 'latest' => $latest, 'next_ts' => 0, 'count' => count($events)];
        }
        $nextTs = function_exists('crm_due_timestamp') ? crm_due_timestamp($latest) : 0;
        if ($nextTs > 0) {
            $label = function_exists('crm_next_label') ? crm_next_label($latest) : date('d M Y H:i', $nextTs);
            $state = $nextTs < (strtotime(date('Y-m-d 00:00:00')) ?: time()) ? 'overdue' : (date('Y-m-d', $nextTs) === date('Y-m-d') ? 'today' : 'scheduled');
            return ['label' => $label, 'state' => $state, 'latest' => $latest, 'next_ts' => $nextTs, 'count' => count($events)];
        }
        return ['label' => 'Sudah follow-up, belum dijadwalkan lagi', 'state' => 'touched', 'latest' => $latest, 'next_ts' => 0, 'count' => count($events)];
    }
}

if (!function_exists('checkout_recovery_destination_label')) {
    function checkout_recovery_destination_label(array $order): string
    {
        $parts = array_filter([
            checkout_recovery_clean((string)($order['district'] ?? ''), 80),
            checkout_recovery_clean((string)($order['city'] ?? $order['location'] ?? ''), 100),
            checkout_recovery_clean((string)($order['province'] ?? ''), 100),
        ]);
        return $parts ? implode(', ', array_unique($parts)) : '-';
    }
}

if (!function_exists('checkout_recovery_shipping_label')) {
    function checkout_recovery_shipping_label(array $order): string
    {
        $amount = (int)($order['shipping_total'] ?? $order['shipping_estimated_total'] ?? $order['shipping_cost'] ?? 0);
        $service = checkout_recovery_clean((string)($order['shipping_service_label'] ?? $order['shipping_service'] ?? $order['shipping_method'] ?? ''), 140);
        $eta = checkout_recovery_clean((string)($order['shipping_eta'] ?? $order['shipping_estimated_eta'] ?? ''), 80);
        $parts = [];
        if ($service !== '') { $parts[] = $service; }
        if ($amount > 0 && function_exists('rupiah')) { $parts[] = rupiah($amount); }
        elseif ($amount === 0 && (string)($order['commerce_shipping_rule_label'] ?? '') !== '') { $parts[] = (string)$order['commerce_shipping_rule_label']; }
        if ($eta !== '') { $parts[] = 'ETA ' . $eta; }
        return $parts ? implode(' · ', $parts) : 'Belum ada estimasi ongkir';
    }
}

if (!function_exists('checkout_recovery_candidate_from_order')) {
    function checkout_recovery_candidate_from_order(array $order, array $settings = []): array
    {
        $stage = checkout_recovery_stage($order, $settings);
        $score = checkout_recovery_score_order($order, $stage, $settings);
        $followup = checkout_recovery_followup_state($order);
        $ageMinutes = checkout_recovery_order_age_minutes($order);
        return [
            'order' => $order,
            'id' => (string)($order['id'] ?? ''),
            'ref' => function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['ref'] ?? $order['id'] ?? ''),
            'name' => (string)($order['name'] ?? ''),
            'phone' => (string)($order['phone'] ?? ''),
            'email' => (string)($order['email'] ?? ''),
            'product' => (string)($order['product_title'] ?? 'Order'),
            'stage' => $stage,
            'stage_label' => checkout_recovery_stage_label($stage),
            'score' => $score,
            'priority' => checkout_recovery_priority_from_score($score),
            'total' => checkout_recovery_total($order),
            'age_minutes' => $ageMinutes,
            'followup' => $followup,
            'destination' => checkout_recovery_destination_label($order),
            'shipping' => checkout_recovery_shipping_label($order),
            'created_ts' => function_exists('order_event_timestamp') ? order_event_timestamp($order) : (strtotime((string)($order['time'] ?? '')) ?: 0),
        ];
    }
}

if (!function_exists('checkout_recovery_candidate_matches')) {
    function checkout_recovery_candidate_matches(array $candidate, array $filters = []): bool
    {
        $stage = checkout_recovery_clean((string)($filters['stage'] ?? ''), 60);
        if ($stage !== '' && $stage !== (string)($candidate['stage'] ?? '')) {
            return false;
        }
        $priority = checkout_recovery_clean((string)($filters['priority'] ?? ''), 60);
        if ($priority !== '' && $priority !== (string)($candidate['priority'] ?? '')) {
            return false;
        }
        $followup = checkout_recovery_clean((string)($filters['followup'] ?? ''), 60);
        if ($followup !== '' && $followup !== (string)($candidate['followup']['state'] ?? '')) {
            return false;
        }
        $search = strtolower(checkout_recovery_clean((string)($filters['search'] ?? ''), 140));
        if ($search !== '') {
            $order = (array)($candidate['order'] ?? []);
            $haystack = strtolower(implode(' ', array_map('strval', [
                $candidate['ref'] ?? '', $candidate['name'] ?? '', $candidate['phone'] ?? '', $candidate['email'] ?? '',
                $candidate['product'] ?? '', $candidate['stage_label'] ?? '', $candidate['priority'] ?? '',
                $order['city'] ?? '', $order['district'] ?? '', $order['location'] ?? '', $order['payment_method'] ?? '', $order['payment_status'] ?? '',
            ])));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('checkout_recovery_candidates')) {
    function checkout_recovery_candidates(int $days = 30, array $filters = [], int $max = 5000): array
    {
        if (!function_exists('order_read_all')) {
            return [];
        }
        $settings = checkout_recovery_read_settings();
        if (!empty($filters['_all_time'])) {
            $days = 0;
        }
        $orders = order_read_all($days, $filters, max(100, min(50000, $max * 3)));
        $items = [];
        foreach ($orders as $order) {
            if (checkout_recovery_order_closed($order)) {
                continue;
            }
            $candidate = checkout_recovery_candidate_from_order($order, $settings);
            if (($candidate['stage'] ?? '') === 'grace_period' && empty($filters['include_grace'])) {
                continue;
            }
            if (($candidate['followup']['state'] ?? '') === 'closed') {
                continue;
            }
            if (!checkout_recovery_candidate_matches($candidate, $filters)) {
                continue;
            }
            $items[] = $candidate;
            if (count($items) >= $max) {
                break;
            }
        }
        usort($items, static function (array $a, array $b): int {
            $priorityA = (int)($a['score'] ?? 0);
            $priorityB = (int)($b['score'] ?? 0);
            if ($priorityA !== $priorityB) {
                return $priorityB <=> $priorityA;
            }
            return ((int)($b['created_ts'] ?? 0)) <=> ((int)($a['created_ts'] ?? 0));
        });
        return $items;
    }
}

if (!function_exists('checkout_recovery_summary')) {
    function checkout_recovery_summary(array $candidates): array
    {
        $summary = [
            'total' => count($candidates),
            'hot' => 0,
            'today' => 0,
            'overdue' => 0,
            'untouched' => 0,
            'estimated_value' => 0,
            'by_stage' => [],
            'by_priority' => [],
        ];
        $today = date('Y-m-d');
        foreach ($candidates as $candidate) {
            $summary['estimated_value'] += (int)($candidate['total'] ?? 0);
            if (in_array((string)($candidate['priority'] ?? ''), ['Tinggi', 'Sangat Panas'], true)) {
                $summary['hot']++;
            }
            $state = (string)($candidate['followup']['state'] ?? '');
            if ($state === 'new') { $summary['untouched']++; }
            if ($state === 'today') { $summary['today']++; }
            if ($state === 'overdue') { $summary['overdue']++; }
            $stage = (string)($candidate['stage_label'] ?? 'Belum Closing');
            $priority = (string)($candidate['priority'] ?? 'Normal');
            $summary['by_stage'][$stage] = ($summary['by_stage'][$stage] ?? 0) + 1;
            $summary['by_priority'][$priority] = ($summary['by_priority'][$priority] ?? 0) + 1;
        }
        arsort($summary['by_stage']);
        arsort($summary['by_priority']);
        return $summary;
    }
}

if (!function_exists('checkout_recovery_template_options')) {
    function checkout_recovery_template_options(): array
    {
        return [
            'first_nudge' => 'Follow-up pertama',
            'payment_reminder' => 'Reminder pembayaran',
            'gateway_help' => 'Bantu payment gateway',
            'shipping_question' => 'Bantu ongkir/pengiriman',
            'preorder_confirmation' => 'Konfirmasi pre-order',
            'digital_pending' => 'Produk digital belum aktif',
            'last_call' => 'Follow-up terakhir',
        ];
    }
}

if (!function_exists('checkout_recovery_recommended_template')) {
    function checkout_recovery_recommended_template(array $candidate): string
    {
        $stage = (string)($candidate['stage'] ?? '');
        $order = (array)($candidate['order'] ?? []);
        if ($stage === 'gateway_pending') { return 'gateway_help'; }
        if ($stage === 'payment_pending') { return 'payment_reminder'; }
        if ($stage === 'shipping_question') { return 'shipping_question'; }
        if ($stage === 'preorder_pending') { return 'preorder_confirmation'; }
        if (str_contains(strtolower((string)($order['product_type'] ?? $order['commerce_shipping_rule_label'] ?? '')), 'digital')) { return 'digital_pending'; }
        if ((int)($candidate['age_minutes'] ?? 0) > 3 * 24 * 60) { return 'last_call'; }
        return 'first_nudge';
    }
}

if (!function_exists('checkout_recovery_template_vars')) {
    function checkout_recovery_template_vars(array $candidate): array
    {
        $order = (array)($candidate['order'] ?? []);
        $total = (int)($candidate['total'] ?? 0);
        return [
            '{site_name}' => defined('SITE_NAME') ? SITE_NAME : 'Website',
            '{name}' => (string)($order['name'] ?? 'Kak'),
            '{order_ref}' => (string)($candidate['ref'] ?? ($order['ref'] ?? '')),
            '{product}' => (string)($order['product_title'] ?? 'pesanan'),
            '{quantity}' => (string)max(1, (int)($order['quantity'] ?? 1)),
            '{total}' => $total > 0 && function_exists('rupiah') ? rupiah($total) : 'Belum ditentukan',
            '{payment_status}' => (string)($order['payment_status'] ?? 'Belum Ditagih'),
            '{payment_method}' => (string)($order['payment_method'] ?? ''),
            '{invoice_url}' => function_exists('order_public_invoice_url') ? order_public_invoice_url($order) : '',
            '{status_url}' => function_exists('order_status_url') ? order_status_url($order) : '',
            '{checkout_url}' => function_exists('order_checkout_url') ? order_checkout_url((string)($order['product_slug'] ?? '')) : '',
            '{destination}' => checkout_recovery_destination_label($order),
            '{shipping}' => checkout_recovery_shipping_label($order),
            '{preorder_note}' => (string)($order['preorder_note'] ?? $order['commerce_preorder_note'] ?? 'Admin akan konfirmasi estimasi pre-order.'),
        ];
    }
}

if (!function_exists('checkout_recovery_render_message')) {
    function checkout_recovery_render_message(array $candidate, string $templateKey = ''): string
    {
        $settings = checkout_recovery_read_settings();
        $templates = (array)($settings['templates'] ?? checkout_recovery_default_templates());
        if ($templateKey === '') {
            $templateKey = checkout_recovery_recommended_template($candidate);
        }
        $template = (string)($templates[$templateKey] ?? $templates['first_nudge'] ?? 'Halo {name}, saya follow-up order {order_ref}.');
        return strtr($template, checkout_recovery_template_vars($candidate));
    }
}

if (!function_exists('checkout_recovery_whatsapp_url')) {
    function checkout_recovery_whatsapp_url(array $candidate, string $templateKey = ''): string
    {
        $phone = (string)($candidate['phone'] ?? '');
        if (function_exists('order_phone_for_whatsapp')) {
            $phone = order_phone_for_whatsapp($phone);
        } else {
            $phone = preg_replace('/\D+/', '', $phone) ?: '';
        }
        if ($phone === '') {
            return '';
        }
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode(checkout_recovery_render_message($candidate, $templateKey));
    }
}

if (!function_exists('checkout_recovery_store_followup')) {
    function checkout_recovery_store_followup(array $payload): bool
    {
        if (!function_exists('crm_store_followup')) {
            return false;
        }
        $orderId = checkout_recovery_clean((string)($payload['order_id'] ?? ''), 100);
        $order = $orderId !== '' && function_exists('order_find_by_id') ? order_find_by_id($orderId) : null;
        if (!is_array($order)) {
            return false;
        }
        $candidate = checkout_recovery_candidate_from_order($order, checkout_recovery_read_settings());
        $templateKey = checkout_recovery_clean((string)($payload['template_key'] ?? checkout_recovery_recommended_template($candidate)), 60);
        $note = checkout_recovery_multiline_clean((string)($payload['note'] ?? ''), 1000);
        if ($note === '') {
            $note = 'Template: ' . (checkout_recovery_template_options()[$templateKey] ?? $templateKey) . "\n" . checkout_recovery_render_message($candidate, $templateKey);
        }
        $nextDate = checkout_recovery_clean((string)($payload['next_followup_date'] ?? ''), 20);
        $nextTime = checkout_recovery_clean((string)($payload['next_followup_time'] ?? ''), 10);
        $settings = checkout_recovery_read_settings();
        if ($nextDate === '' && !empty($settings['auto_schedule_next'])) {
            $nextTs = time() + (max(1, (int)($settings['default_next_followup_hours'] ?? 24)) * 3600);
            $nextDate = date('Y-m-d', $nextTs);
            $nextTime = date('H:i', $nextTs);
        }
        return crm_store_followup([
            'target_type' => 'order',
            'target_id' => (string)($order['id'] ?? ''),
            'target_ref' => (string)($candidate['ref'] ?? ''),
            'target_name' => (string)($order['name'] ?? ''),
            'phone' => (string)($order['phone'] ?? ''),
            'email' => (string)($order['email'] ?? ''),
            'subject' => 'Recovery checkout: ' . (string)($candidate['stage_label'] ?? 'Belum Closing'),
            'priority' => checkout_recovery_clean((string)($payload['priority'] ?? $candidate['priority'] ?? 'Normal'), 40),
            'outcome' => checkout_recovery_clean((string)($payload['outcome'] ?? 'Chat Terkirim'), 60),
            'note' => $note,
            'next_followup_date' => $nextDate,
            'next_followup_time' => $nextTime,
            'created_by' => 'admin',
            'source' => 'checkout_recovery_center',
        ]);
    }
}

if (!function_exists('checkout_recovery_export_csv')) {
    function checkout_recovery_export_csv(array $candidates): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="checkout-recovery-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['order_ref', 'time', 'name', 'phone', 'email', 'product', 'stage', 'priority', 'score', 'payment_status', 'total', 'followup_state', 'next_followup']);
        foreach ($candidates as $candidate) {
            $order = (array)($candidate['order'] ?? []);
            fputcsv($out, [
                (string)($candidate['ref'] ?? ''),
                (string)($order['time'] ?? ''),
                (string)($candidate['name'] ?? ''),
                (string)($candidate['phone'] ?? ''),
                (string)($candidate['email'] ?? ''),
                (string)($candidate['product'] ?? ''),
                (string)($candidate['stage_label'] ?? ''),
                (string)($candidate['priority'] ?? ''),
                (string)($candidate['score'] ?? 0),
                (string)($order['payment_status'] ?? ''),
                (string)($candidate['total'] ?? 0),
                (string)($candidate['followup']['state'] ?? ''),
                (string)($candidate['followup']['label'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}

if (!function_exists('checkout_recovery_anonymous_intents')) {
    function checkout_recovery_anonymous_intents(int $days = 7, int $limit = 10): array
    {
        if (!function_exists('conversion_read_lead_events')) {
            return [];
        }
        $events = conversion_read_lead_events($days, ['channel' => 'checkout'], 20000);
        $submitRefs = [];
        foreach ($events as $event) {
            if (str_contains(strtolower((string)($event['type'] ?? '')), 'submit')) {
                $submitRefs[(string)($event['page_path'] ?? '')] = true;
            }
        }
        $pages = [];
        foreach ($events as $event) {
            $type = strtolower((string)($event['type'] ?? ''));
            if (!str_contains($type, 'checkout') && !str_contains($type, 'begin')) {
                continue;
            }
            if (str_contains($type, 'submit')) {
                continue;
            }
            $path = checkout_recovery_clean((string)($event['page_path'] ?? '/checkout'), 220) ?: '/checkout';
            $label = checkout_recovery_clean((string)($event['label'] ?? $path), 160) ?: $path;
            if (!isset($pages[$path])) {
                $pages[$path] = ['page_path' => $path, 'label' => $label, 'views' => 0, 'last_ts' => 0];
            }
            $pages[$path]['views']++;
            $pages[$path]['last_ts'] = max((int)$pages[$path]['last_ts'], (int)($event['_ts'] ?? 0));
        }
        usort($pages, static fn(array $a, array $b): int => ((int)$b['views'] <=> (int)$a['views']) ?: ((int)$b['last_ts'] <=> (int)$a['last_ts']));
        return array_slice($pages, 0, max(1, $limit));
    }
}
