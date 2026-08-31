<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$allowedDays = [7, 14, 30, 60, 90, 180, 365];
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, $allowedDays, true)) {
    $days = 30;
}
$typeOptions = function_exists('lead_quality_type_options') ? lead_quality_type_options() : ['all' => 'Semua Sumber'];
$type = (string)($_GET['type'] ?? 'all');
if (!isset($typeOptions[$type])) {
    $type = 'all';
}
$priorityOptions = function_exists('lead_quality_priority_options') ? lead_quality_priority_options() : ['all' => 'Semua Prioritas'];
$priority = (string)($_GET['priority'] ?? 'all');
if (!isset($priorityOptions[$priority])) {
    $priority = 'all';
}
$statusOptions = function_exists('lead_quality_filter_options') ? lead_quality_filter_options() : ['open' => 'Belum Selesai'];
$status = (string)($_GET['status'] ?? 'open');
if (!isset($statusOptions[$status])) {
    $status = 'open';
}

$redirectBase = static function (string $message = '') use ($days, $type, $priority, $status): string {
    $query = [
        'days' => $days,
        'type' => $type,
        'priority' => $priority,
        'status' => $status,
    ];
    if ($message !== '') {
        $query['message'] = $message;
    }
    return 'admin/lead-priority-scoring?' . http_build_query($query);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'update_item') {
            $itemId = (string)($_POST['item_id'] ?? '');
            $leadStatus = (string)($_POST['lead_status'] ?? 'new_opportunity');
            $note = (string)($_POST['lead_note'] ?? '');
            $owner = (string)($_POST['owner'] ?? '');
            $date = (string)($_POST['next_followup_date'] ?? '');
            $time = (string)($_POST['next_followup_time'] ?? '');
            lead_quality_update_item($itemId, $leadStatus, $note, $owner, $date, $time);

            $crmNote = trim((string)($_POST['crm_note'] ?? ''));
            $shouldRecordCrm = isset($_POST['record_crm']) || $crmNote !== '' || $date !== '';
            if ($shouldRecordCrm) {
                lead_quality_record_crm_followup([
                    'target_type' => (string)($_POST['target_type'] ?? 'manual'),
                    'target_id' => (string)($_POST['target_id'] ?? ''),
                    'target_ref' => (string)($_POST['target_ref'] ?? ''),
                    'target_name' => (string)($_POST['target_name'] ?? ''),
                    'phone' => (string)($_POST['phone'] ?? ''),
                    'email' => (string)($_POST['email'] ?? ''),
                    'subject' => (string)($_POST['subject'] ?? ''),
                    'priority' => (string)($_POST['crm_priority'] ?? 'Normal'),
                    'outcome' => (string)($_POST['crm_outcome'] ?? 'Catatan'),
                    'note' => $crmNote !== '' ? $crmNote : $note,
                    'next_followup_date' => $date,
                    'next_followup_time' => $time,
                ]);
            }
            redirect_302($redirectBase('Status lead dan catatan follow-up berhasil disimpan.'));
        }
        if ($action === 'reset_item') {
            lead_quality_reset_item((string)($_POST['item_id'] ?? ''));
            redirect_302($redirectBase('Catatan lead ini sudah direset.'));
        }
        if ($action === 'reset_all') {
            lead_quality_reset_all();
            redirect_302($redirectBase('Semua catatan Lead Priority Scoring sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = lead_quality_summary($days, $type, $priority, $status);
$items = (array)($summary['items'] ?? []);
$counts = (array)($summary['counts'] ?? []);
$topItem = is_array($summary['top_item'] ?? null) ? (array)$summary['top_item'] : null;
$typeOptions = (array)($summary['type_options'] ?? $typeOptions);
$priorityOptions = (array)($summary['priority_options'] ?? $priorityOptions);
$statusOptions = (array)($summary['status_options'] ?? $statusOptions);
$statusActionOptions = (array)($summary['status_action_options'] ?? lead_quality_status_options());

$baseUrl = static function (array $override = []) use ($days, $type, $priority, $status): string {
    $query = array_merge([
        'days' => $days,
        'type' => $type,
        'priority' => $priority,
        'status' => $status,
    ], $override);
    return url('admin/lead-priority-scoring?' . http_build_query($query));
};

if (($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="lead-quality-followup-scoring-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_GET['export'] ?? '') === 'csv') {
    lead_quality_export_csv($items);
}

$statusLabel = static function (string $value) use ($statusActionOptions): string {
    return (string)($statusActionOptions[$value] ?? ucfirst(str_replace('_', ' ', $value)));
};
$priorityLabel = static function (string $value): string {
    return function_exists('lead_quality_bucket_label') ? lead_quality_bucket_label($value) : ucfirst($value ?: 'Lead');
};
$formatDue = static function (?array $event): string {
    if (!$event) {
        return 'Belum ada jadwal';
    }
    $date = trim((string)($event['next_followup_date'] ?? ''));
    $time = trim((string)($event['next_followup_time'] ?? ''));
    return trim($date . ' ' . $time) ?: 'Belum ada jadwal';
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Lead Priority Scoring - Admin',
    'description' => 'Prioritaskan lead, order, dan peluang follow-up berdasarkan data order, form, CRM, dan Lead Tracking existing.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-lead-quality-shell">
    <section class="admin-hero admin-lead-quality-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Lead & Profit Execution</div>
                <h1>Lead Priority Scoring</h1>
                <p>Prioritaskan lead yang paling dekat ke closing. Halaman ini membaca Order, Inbox Lead/Form, CRM Follow-up, dan Tracking Lead existing tanpa membuat sistem tracking baru.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/leads')); ?>">Tracking Lead</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/inquiries')); ?>">Inbox Lead</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/orders')); ?>">Order</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/followups')); ?>">Follow-up CRM</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-result-overview admin-lead-quality-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Lead Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($summary['average_lead_score'] ?? 0); ?>;">
                        <strong><?= (int)($summary['average_lead_score'] ?? 0); ?></strong><span>/100</span>
                    </div>
                    <h2>Prioritas Follow-up</h2>
                    <p><?= esc((string)($summary['top_focus'] ?? 'Prioritaskan lead yang paling dekat ke closing.')); ?></p>
                </article>

                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Pipeline Lead</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($counts['visible'] ?? 0); ?></strong> tampil</span>
                        <span><strong><?= (int)($counts['hot'] ?? 0); ?></strong> hot</span>
                        <span><strong><?= (int)($counts['warm'] ?? 0); ?></strong> warm</span>
                        <span><strong><?= (int)($counts['open'] ?? 0); ?></strong> open</span>
                    </div>
                    <p>Skor dibuat dari sinyal order, form, lead tracking, status pembayaran, kelengkapan kontak, usia lead, dan jadwal follow-up.</p>
                </article>

                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Money Leak Watch</span>
                    <h2><?= (int)($counts['waiting_payment'] ?? 0); ?> menunggu bayar</h2>
                    <p><?= (int)($counts['contact_today'] ?? 0); ?> perlu follow-up hari ini, <?= (int)($counts['scheduled'] ?? 0); ?> sudah dijadwalkan, <?= (int)($counts['won'] ?? 0); ?> deal/selesai.</p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'json'])); ?>">Export JSON</a>
                    </div>
                </article>
            </div>

            <section class="admin-card admin-cta-result-filter-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Filter Scoring</span>
                        <h2>Fokus ke lead yang paling perlu dikerjakan</h2>
                        <p>Pakai filter ini untuk memilih periode, sumber lead, prioritas, dan status follow-up.</p>
                    </div>
                </div>
                <div class="admin-seo-profit-filter-row">
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($allowedDays as $rangeDays): ?>
                            <a class="<?= $days === $rangeDays ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['days' => $rangeDays])); ?>"><?= (int)$rangeDays; ?> hari</a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
                            <a class="<?= $type === (string)$typeKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['type' => (string)$typeKey])); ?>"><?= esc((string)$typeLabel); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($priorityOptions as $priorityKey => $priorityText): ?>
                            <a class="<?= $priority === (string)$priorityKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['priority' => (string)$priorityKey])); ?>"><?= esc((string)$priorityText); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($statusOptions as $statusKey => $statusText): ?>
                            <a class="<?= $status === (string)$statusKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['status' => (string)$statusKey])); ?>"><?= esc((string)$statusText); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <?php if ($topItem): ?>
                <section class="admin-card admin-cta-result-winner-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Top Priority</span>
                            <h2><?= esc((string)($topItem['name'] ?? 'Lead prioritas')); ?></h2>
                            <p><?= esc((string)($topItem['reason'] ?? 'Lead ini layak diprioritaskan.')); ?></p>
                        </div>
                        <div class="admin-cta-result-mini-metrics">
                            <span><strong><?= (int)($topItem['score'] ?? 0); ?></strong> score</span>
                            <span><strong><?= esc($priorityLabel((string)($topItem['priority'] ?? ''))); ?></strong> prioritas</span>
                            <span><strong><?= esc((string)($topItem['type_label'] ?? 'Lead')); ?></strong> sumber</span>
                        </div>
                    </div>
                    <div class="admin-insight-callout">
                        <strong>Aksi disarankan:</strong> <?= esc((string)($topItem['next_action'] ?? 'Follow-up lead ini lebih dulu.')); ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Lead Queue</span>
                        <h2>Daftar prioritas lead dan peluang follow-up</h2>
                        <p>Update status, jadwalkan follow-up, dan simpan catatan agar prospek tidak lepas.</p>
                    </div>
                </div>

                <?php if (!$items): ?>
                    <div class="admin-empty-state">
                        <h3>Belum ada lead pada filter ini</h3>
                        <p>Coba longgarkan periode/filter, atau cek menu Tracking Lead, Inbox Lead, dan Order.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-stack">
                        <?php foreach ($items as $item): ?>
                            <?php
                            $itemStatus = (string)($item['status'] ?? 'new_opportunity');
                            $itemPriority = (string)($item['priority'] ?? 'cold');
                            $itemId = (string)($item['item_id'] ?? '');
                            $targetType = (string)($item['target_type'] ?? 'manual');
                            $targetId = (string)($item['target_id'] ?? '');
                            $phone = (string)($item['phone'] ?? '');
                            $waPhone = preg_replace('/\D+/', '', $phone);
                            $waMessage = rawurlencode('Halo ' . (string)($item['name'] ?? '') . ', saya follow-up terkait ' . (string)($item['subject'] ?? 'kebutuhan Anda') . '.');
                            ?>
                            <article class="admin-card admin-cta-result-item-card admin-lead-quality-item">
                                <div class="admin-card-header">
                                    <div>
                                        <span class="admin-badge"><?= esc((string)($item['type_label'] ?? 'Lead')); ?> · <?= esc($priorityLabel($itemPriority)); ?> · Score <?= (int)($item['score'] ?? 0); ?></span>
                                        <h3><?= esc((string)($item['name'] ?? 'Lead')); ?></h3>
                                        <p><?= esc((string)($item['subject'] ?? 'Peluang follow-up')); ?></p>
                                    </div>
                                    <div class="admin-cta-result-mini-metrics">
                                        <span><strong><?= esc($statusLabel($itemStatus)); ?></strong> status</span>
                                        <span><strong><?= (int)($item['followup_count'] ?? 0); ?></strong> catatan CRM</span>
                                        <span><strong><?= esc((string)($item['last_time'] ?? '-')); ?></strong> terakhir</span>
                                    </div>
                                </div>

                                <div class="admin-grid admin-grid--3">
                                    <div class="admin-soft-card">
                                        <strong>Kontak</strong>
                                        <p><?= $phone !== '' ? esc($phone) : 'Belum ada nomor'; ?><?= (string)($item['email'] ?? '') !== '' ? '<br>' . esc((string)$item['email']) : ''; ?></p>
                                        <?php if ($waPhone !== ''): ?>
                                            <a class="admin-btn admin-btn--light" href="https://wa.me/<?= esc($waPhone); ?>?text=<?= esc($waMessage); ?>" target="_blank" rel="noopener nofollow">Follow-up via WA</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="admin-soft-card">
                                        <strong>Stage & sumber</strong>
                                        <p><?= esc((string)($item['stage'] ?? '-')); ?><br><?= esc((string)($item['source'] ?? '-')); ?></p>
                                        <?php if ((string)($item['page_path'] ?? '') !== ''): ?><small><?= esc((string)$item['page_path']); ?></small><?php endif; ?>
                                    </div>
                                    <div class="admin-soft-card">
                                        <strong>Jadwal follow-up</strong>
                                        <p><?= esc($formatDue(is_array($item['followup_next_due'] ?? null) ? $item['followup_next_due'] : null)); ?></p>
                                        <?php if (!empty($item['followup_overdue'])): ?><span class="admin-badge admin-badge--warning">Overdue</span><?php endif; ?>
                                        <?php if (!empty($item['followup_today'])): ?><span class="admin-badge">Hari ini</span><?php endif; ?>
                                    </div>
                                </div>

                                <div class="admin-insight-callout">
                                    <strong>Kenapa diprioritaskan:</strong> <?= esc((string)($item['reason'] ?? '-')); ?><br>
                                    <strong>Aksi berikutnya:</strong> <?= esc((string)($item['next_action'] ?? '-')); ?>
                                </div>

                                <form method="post" action="<?= esc($baseUrl()); ?>" class="admin-form-grid">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_item">
                                    <input type="hidden" name="item_id" value="<?= esc($itemId); ?>">
                                    <input type="hidden" name="target_type" value="<?= esc($targetType); ?>">
                                    <input type="hidden" name="target_id" value="<?= esc($targetId); ?>">
                                    <input type="hidden" name="target_ref" value="<?= esc((string)($item['target_ref'] ?? '')); ?>">
                                    <input type="hidden" name="target_name" value="<?= esc((string)($item['name'] ?? '')); ?>">
                                    <input type="hidden" name="phone" value="<?= esc($phone); ?>">
                                    <input type="hidden" name="email" value="<?= esc((string)($item['email'] ?? '')); ?>">
                                    <input type="hidden" name="subject" value="<?= esc((string)($item['subject'] ?? '')); ?>">
                                    <input type="hidden" name="crm_priority" value="<?= esc((string)($item['recommended_priority'] ?? 'Normal')); ?>">
                                    <input type="hidden" name="crm_outcome" value="<?= esc((string)($item['recommended_outcome'] ?? 'Catatan')); ?>">

                                    <label>Status Lead
                                        <select name="lead_status">
                                            <?php foreach ($statusActionOptions as $statusKey => $statusText): ?>
                                                <option value="<?= esc((string)$statusKey); ?>" <?= $itemStatus === (string)$statusKey ? 'selected' : ''; ?>><?= esc((string)$statusText); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Owner / PIC
                                        <input type="text" name="owner" value="<?= esc((string)($item['owner'] ?? '')); ?>" placeholder="Admin / CS">
                                    </label>
                                    <label>Tanggal follow-up berikutnya
                                        <input type="date" name="next_followup_date" value="<?= esc((string)($item['next_followup_date'] ?? '')); ?>">
                                    </label>
                                    <label>Jam follow-up
                                        <input type="time" name="next_followup_time" value="<?= esc((string)($item['next_followup_time'] ?? '')); ?>">
                                    </label>
                                    <label class="admin-form-full">Catatan internal
                                        <textarea name="lead_note" rows="2" placeholder="Catatan status lead untuk dashboard ini..."><?= esc((string)($item['admin_note'] ?? '')); ?></textarea>
                                    </label>
                                    <label class="admin-form-full">Catatan CRM / isi follow-up
                                        <textarea name="crm_note" rows="2" placeholder="Contoh: Sudah chat customer, kirim katalog, tunggu respon sore ini."></textarea>
                                    </label>
                                    <label class="admin-inline-check admin-form-full">
                                        <input type="checkbox" name="record_crm" value="1"> Simpan juga ke Follow-up CRM
                                    </label>
                                    <div class="admin-form-actions admin-form-full">
                                        <button class="admin-btn" type="submit">Simpan Status & Follow-up</button>
                                    </div>
                                </form>
                                <form method="post" action="<?= esc($baseUrl()); ?>" onsubmit="return confirm('Reset catatan lead ini?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="reset_item">
                                    <input type="hidden" name="item_id" value="<?= esc($itemId); ?>">
                                    <button class="admin-btn admin-btn--light" type="submit">Reset Catatan Lead Ini</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-result-danger-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Reset Catatan</span>
                        <h2>Reset status Lead Priority Scoring</h2>
                        <p>Ini hanya menghapus catatan status scoring. Order, Inbox Lead, Tracking Lead, dan Follow-up CRM tidak ikut dihapus.</p>
                    </div>
                    <form method="post" action="<?= esc($baseUrl()); ?>" onsubmit="return confirm('Reset semua catatan Lead Priority?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_all">
                        <button class="admin-btn admin-btn--danger" type="submit">Reset Semua Catatan</button>
                    </form>
                </div>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
