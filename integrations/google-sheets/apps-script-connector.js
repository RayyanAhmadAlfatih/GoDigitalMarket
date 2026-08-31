/**
 * U-Growth Google Sheets Connector
 *
 * Cara pakai singkat:
 * 1. Buat Google Spreadsheet kosong.
 * 2. Buka Extensions -> Apps Script.
 * 3. Tempel seluruh kode ini.
 * 4. Isi Script Properties: SYNC_TOKEN, SPREADSHEET_ID (opsional), DRIVE_FOLDER_ID (opsional).
 * 5. Jalankan setupUGrowthSheets sekali.
 * 6. Deploy sebagai Web App dan tempel URL Web App ke menu Backup & Sync Data.
 */

const UGROWTH_CONNECTOR_VERSION = '2.0';
const UGROWTH_MAX_ROWS_PER_REQUEST = 10000;
const UGROWTH_SYNC_LOG_HEADERS = ['received_at', 'payload_id', 'source', 'sheet_name', 'mode', 'rows_received', 'rows_written', 'checksum', 'status', 'message'];

const UGROWTH_DEFAULT_SHEETS = {
  leads: ['received_at', 'date', 'lead_name', 'phone', 'email', 'form_name', 'page_url', 'source', 'campaign', 'lead_status', 'lead_score', 'followup_status', 'needs', 'message', '_exported_at'],
  orders: ['received_at', 'date', 'order_ref', 'customer_name', 'phone', 'email', 'product_name', 'source', 'campaign', 'order_status', 'payment_status', 'subtotal', 'shipping_cost', 'total', 'paid_amount', 'pending_amount', 'conversion_value', 'payment_channel', 'city', 'province', '_exported_at'],
  analytics_events: ['received_at', 'date', 'event_name', 'page_url', 'source', 'medium', 'campaign', 'content', 'visitor_id', 'lead_id', 'order_ref', 'value', '_exported_at'],
  landing_page_analytics: ['received_at', 'page_title', 'page_url', 'views', 'cta_clicks', 'leads', 'orders', 'revenue', 'conversion_rate', 'best_cta', 'action_plan', '_exported_at'],
  offer_cta_tests: ['received_at', 'variant_name', 'status', 'page_url', 'offer', 'cta_text', 'views', 'clicks', 'leads', 'orders', 'conversion_rate', 'notes', '_exported_at'],
  cta_placements: ['received_at', 'placement', 'page_title', 'page_url', 'cta_text', 'priority', 'status', 'note', '_exported_at'],
  cta_results: ['received_at', 'placement', 'page_url', 'period', 'views', 'clicks', 'leads', 'orders', 'revenue', 'conversion_rate', 'winner', '_exported_at'],
  seo_profit_attribution: ['received_at', 'page_title', 'page_url', 'keyword', 'traffic', 'leads', 'orders', 'revenue', 'conversion_rate', 'priority', 'action_plan', '_exported_at'],
  profit_actions: ['received_at', 'action_title', 'priority', 'status', 'pic', 'due_date', 'impact_score', 'note', '_exported_at'],
  seo_campaign_calendar: ['received_at', 'task_title', 'campaign', 'status', 'pic', 'deadline', 'priority', 'note', '_exported_at'],
  lead_quality_scores: ['received_at', 'date', 'lead_name', 'source', 'lead_score', 'temperature', 'followup_status', 'recommended_action', '_exported_at'],
  internal_link_cta: ['received_at', 'page_title', 'page_url', 'target_url', 'cta_text', 'priority', 'status', 'note', '_exported_at'],
  seo_content_refresh: ['received_at', 'page_title', 'page_url', 'last_refresh', 'next_action', 'priority', 'status', 'note', '_exported_at'],
  seo_money_pages: ['received_at', 'page_title', 'page_url', 'intent', 'status', 'priority', 'conversion_score', 'note', '_exported_at'],
  customers: ['received_at', 'date', 'buyer_name', 'email', 'phone', 'total_orders', 'total_spent', 'status', '_exported_at'],
  member_access: ['received_at', 'date', 'buyer_name', 'product_name', 'order_ref', 'access_status', 'expires_at', 'license_key', '_exported_at'],
  payment_proofs: ['received_at', 'date', 'order_ref', 'buyer_name', 'amount', 'payment_status', 'proof_status', 'payment_channel', '_exported_at'],
  email_logs: ['received_at', 'date', 'recipient', 'subject', 'template', 'status', 'module', '_exported_at']
};

function doGet(e) {
  const action = e && e.parameter && e.parameter.action ? String(e.parameter.action) : 'status';
  return jsonResponse_({
    ok: true,
    app: 'U-Growth Google Sheets Connector',
    version: UGROWTH_CONNECTOR_VERSION,
    action: action,
    message: 'Connector aktif. Gunakan POST dari menu Backup & Sync Data.',
    sheets: Object.keys(UGROWTH_DEFAULT_SHEETS),
    max_rows_per_request: UGROWTH_MAX_ROWS_PER_REQUEST
  });
}

function doPost(e) {
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    const payload = parsePayload_(e);
    verifyToken_(payload);

    const sheetName = normalizeSheetName_(payload.sheet_name || payload.source || 'ugrowth_data');
    const rows = validateRows_(payload.rows);
    const mode = normalizeMode_(payload.mode || 'replace');
    const payloadId = normalizePayloadId_(payload.payload_id || (payload.meta && payload.meta.payload_id) || 'sync-' + Date.now());
    const checksum = String(payload.rows_checksum || checksumRows_(rows)).slice(0, 80);
    const spreadsheet = getSpreadsheet_(payload.spreadsheet_id);
    const written = writeRows_(spreadsheet, sheetName, rows, mode);

    let driveBackup = null;
    if (payload.destinations && payload.destinations.google_drive) {
      driveBackup = writeDriveBackup_(payload);
    }

    appendSyncLog_(spreadsheet, {
      payload_id: payloadId,
      source: String(payload.source || ''),
      sheet_name: sheetName,
      mode: mode,
      rows_received: rows.length,
      rows_written: written.rows_written,
      checksum: checksum,
      status: 'ok',
      message: 'Data berhasil diterima.'
    });

    return jsonResponse_({
      ok: true,
      message: 'Data berhasil diterima.',
      version: UGROWTH_CONNECTOR_VERSION,
      payload_id: payloadId,
      checksum: checksum,
      spreadsheet_id: spreadsheet.getId(),
      spreadsheet_url: spreadsheet.getUrl(),
      sheet_name: sheetName,
      rows_received: rows.length,
      rows_written: written.rows_written,
      headers: written.headers,
      drive_backup: driveBackup
    });
  } catch (error) {
    return jsonResponse_({
      ok: false,
      message: error && error.message ? error.message : String(error)
    });
  } finally {
    lock.releaseLock();
  }
}

function setupUGrowthSheets() {
  const spreadsheet = getSpreadsheet_('');
  Object.keys(UGROWTH_DEFAULT_SHEETS).forEach(function (sheetName) {
    const sheet = getOrCreateSheet_(spreadsheet, sheetName);
    const headers = UGROWTH_DEFAULT_SHEETS[sheetName];
    if (sheet.getLastRow() === 0) {
      sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    } else {
      ensureHeaders_(sheet, headers);
    }
    sheet.setFrozenRows(1);
    sheet.autoResizeColumns(1, Math.min(headers.length, 12));
  });
  ensureSyncLogSheet_(spreadsheet);
  return jsonResponse_({
    ok: true,
    message: 'Sheet standar U-Growth sudah disiapkan.',
    spreadsheet_id: spreadsheet.getId(),
    spreadsheet_url: spreadsheet.getUrl()
  });
}

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('U-Growth')
    .addItem('Setup Sheet Standar', 'setupUGrowthSheets')
    .addItem('Setup Template Dashboard', 'setupUGrowthDashboardTemplate')
    .addToUi();
}

function setupUGrowthDashboardTemplate() {
  const spreadsheet = getSpreadsheet_('');
  writeGuideSheet_(spreadsheet, '_dashboard_guide', UGROWTH_DASHBOARD_GUIDE, ['dashboard', 'tujuan', 'sumber_data', 'scorecard', 'chart_disarankan', 'keputusan_bisnis']);
  writeGuideSheet_(spreadsheet, '_field_dictionary', buildFieldDictionary_(), ['source', 'sheet_name', 'field', 'kegunaan']);
  writeGuideSheet_(spreadsheet, '_chart_blueprint', UGROWTH_CHART_BLUEPRINT, ['dashboard', 'tipe_visual', 'judul', 'source', 'dimension', 'metric', 'catatan']);
  return jsonResponse_({
    ok: true,
    message: 'Template dashboard dan kamus field sudah disiapkan.',
    spreadsheet_id: spreadsheet.getId(),
    spreadsheet_url: spreadsheet.getUrl(),
    guide_sheets: ['_dashboard_guide', '_field_dictionary', '_chart_blueprint']
  });
}

const UGROWTH_DASHBOARD_GUIDE = [
  { dashboard: 'Owner Overview', tujuan: 'Melihat kondisi bisnis harian dan prioritas scale up.', sumber_data: 'orders, leads, seo_profit_attribution, profit_actions', scorecard: 'Omzet, order masuk, lead baru, order belum bayar', chart_disarankan: 'Omzet harian, lead vs order, status pembayaran', keputusan_bisnis: 'Channel mana yang perlu dinaikkan dan order mana yang perlu ditagih.' },
  { dashboard: 'Sales & Payment', tujuan: 'Memantau order, invoice, pembayaran, dan bukti bayar.', sumber_data: 'orders, payment_proofs, customers', scorecard: 'Order baru, lunas, tagihan tertunda, bukti bayar masuk', chart_disarankan: 'Omzet tanggal, payment status, kota pembeli', keputusan_bisnis: 'Prioritas follow-up pembayaran dan area penjualan.' },
  { dashboard: 'Lead & CRM', tujuan: 'Memilih lead yang paling siap difollow-up.', sumber_data: 'leads, lead_quality_scores, analytics_events', scorecard: 'Lead baru, skor lead, belum follow-up', chart_disarankan: 'Lead harian, sumber lead, lead score', keputusan_bisnis: 'Lead dan campaign mana yang harus dikejar duluan.' },
  { dashboard: 'SEO Profit', tujuan: 'Membaca halaman SEO yang menghasilkan lead, order, dan omzet.', sumber_data: 'seo_profit_attribution, seo_content_refresh, seo_money_pages', scorecard: 'Traffic SEO, lead SEO, order SEO, omzet SEO', chart_disarankan: 'Revenue per halaman, traffic vs lead, prioritas refresh', keputusan_bisnis: 'Halaman mana yang perlu dioptimasi atau dipertahankan.' },
  { dashboard: 'CTA & Campaign', tujuan: 'Menilai offer, CTA, dan campaign yang paling menghasilkan.', sumber_data: 'offer_cta_tests, cta_results, seo_campaign_calendar', scorecard: 'CTA aktif, klik CTA, lead CTA, conversion rate', chart_disarankan: 'Klik vs lead, conversion rate per CTA, campaign status', keputusan_bisnis: 'CTA pemenang dan campaign yang perlu dilanjutkan.' },
  { dashboard: 'Member & Digital Product', tujuan: 'Memantau pembeli, akses produk digital, dan masa akses.', sumber_data: 'member_access, customers, orders', scorecard: 'Member aktif, akses aktif, produk digital terjual', chart_disarankan: 'Akses per produk, member baru, status akses', keputusan_bisnis: 'Produk digital mana yang perlu dipromosikan dan member mana perlu dibantu.' }
];

const UGROWTH_CHART_BLUEPRINT = [
  { dashboard: 'Owner Overview', tipe_visual: 'Scorecard', judul: 'Total Omzet', source: 'orders', dimension: '', metric: 'total', catatan: 'Aggregation SUM.' },
  { dashboard: 'Owner Overview', tipe_visual: 'Time series', judul: 'Omzet Harian', source: 'orders', dimension: 'date', metric: 'total', catatan: 'Pakai filter tanggal.' },
  { dashboard: 'Sales & Payment', tipe_visual: 'Bar chart', judul: 'Status Pembayaran', source: 'orders', dimension: 'payment_status', metric: 'order_ref', catatan: 'Aggregation COUNT.' },
  { dashboard: 'Lead & CRM', tipe_visual: 'Bar chart', judul: 'Sumber Lead', source: 'leads', dimension: 'source', metric: 'lead_name', catatan: 'Aggregation COUNT.' },
  { dashboard: 'SEO Profit', tipe_visual: 'Bar chart', judul: 'Revenue per Halaman', source: 'seo_profit_attribution', dimension: 'page_title', metric: 'revenue', catatan: 'Urutkan revenue terbesar.' },
  { dashboard: 'CTA & Campaign', tipe_visual: 'Bar chart', judul: 'Conversion Rate per CTA', source: 'cta_results', dimension: 'placement', metric: 'conversion_rate', catatan: 'Pakai average conversion rate.' },
  { dashboard: 'Member & Digital Product', tipe_visual: 'Bar chart', judul: 'Akses per Produk', source: 'member_access', dimension: 'product_name', metric: 'buyer_name', catatan: 'Aggregation COUNT.' }
];

function buildFieldDictionary_() {
  const rows = [];
  Object.keys(UGROWTH_DEFAULT_SHEETS).forEach(function(sheetName) {
    UGROWTH_DEFAULT_SHEETS[sheetName].forEach(function(field) {
      rows.push({ source: sheetName, sheet_name: sheetName, field: field, kegunaan: describeField_(field) });
    });
  });
  return rows;
}

function writeGuideSheet_(spreadsheet, sheetName, rows, headers) {
  const sheet = getOrCreateSheet_(spreadsheet, sheetName);
  sheet.clearContents();
  sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  if (rows.length) {
    const values = rows.map(function(row) {
      return headers.map(function(header) { return row[header] || ''; });
    });
    sheet.getRange(2, 1, values.length, headers.length).setValues(values);
  }
  sheet.setFrozenRows(1);
  sheet.autoResizeColumns(1, Math.min(headers.length, 12));
  return sheet;
}

function describeField_(field) {
  const map = {
    date: 'Dimensi tanggal untuk filter dan time series.',
    total: 'Nilai omzet/order untuk metrik penjualan.',
    payment_status: 'Status pembayaran untuk filter invoice dan follow-up.',
    source: 'Sumber lead/traffic untuk membaca channel terbaik.',
    campaign: 'Nama campaign untuk membandingkan performa marketing.',
    conversion_rate: 'Rasio konversi untuk mengukur efektivitas halaman/CTA.',
    lead_score: 'Skor prioritas lead untuk follow-up.',
    revenue: 'Nilai omzet yang dikaitkan ke halaman atau campaign.',
    access_status: 'Status akses member/produk digital.',
    _exported_at: 'Waktu data dikirim dari U-Growth.'
  };
  return map[field] || 'Field data U-Growth untuk tabel, filter, atau chart.';
}


function validateRows_(rows) {
  if (!Array.isArray(rows)) {
    return [];
  }
  if (rows.length > UGROWTH_MAX_ROWS_PER_REQUEST) {
    throw new Error('Jumlah baris terlalu besar. Maksimal ' + UGROWTH_MAX_ROWS_PER_REQUEST + ' baris per request.');
  }
  return rows.map(function(row) {
    if (!row || typeof row !== 'object' || Array.isArray(row)) {
      return { value: safeCell_(row) };
    }
    const clean = {};
    Object.keys(row).forEach(function(key) {
      const safeKey = String(key || '').replace(/[^a-zA-Z0-9_ -]/g, '').slice(0, 80) || 'field';
      clean[safeKey] = safeCell_(row[key]);
    });
    return clean;
  });
}

function normalizeMode_(mode) {
  mode = String(mode || 'replace').toLowerCase();
  return mode === 'append' ? 'append' : 'replace';
}

function normalizePayloadId_(value) {
  return String(value || '').replace(/[^a-zA-Z0-9_.-]/g, '-').slice(0, 100) || ('sync-' + Date.now());
}

function safeCell_(value) {
  if (value === null || value === undefined) return '';
  if (typeof value === 'object') return JSON.stringify(value).slice(0, 5000);
  return String(value).slice(0, 5000);
}

function checksumRows_(rows) {
  const text = JSON.stringify(rows || []);
  let hash = 0;
  for (let i = 0; i < text.length; i++) {
    hash = ((hash << 5) - hash) + text.charCodeAt(i);
    hash |= 0;
  }
  return String(Math.abs(hash));
}

function ensureSyncLogSheet_(spreadsheet) {
  const sheet = getOrCreateSheet_(spreadsheet, '_sync_log');
  if (sheet.getLastRow() === 0) {
    sheet.getRange(1, 1, 1, UGROWTH_SYNC_LOG_HEADERS.length).setValues([UGROWTH_SYNC_LOG_HEADERS]);
    sheet.setFrozenRows(1);
  } else {
    ensureHeaders_(sheet, UGROWTH_SYNC_LOG_HEADERS);
  }
  return sheet;
}

function appendSyncLog_(spreadsheet, entry) {
  try {
    const sheet = ensureSyncLogSheet_(spreadsheet);
    const row = Object.assign({ received_at: new Date().toISOString() }, entry || {});
    sheet.appendRow(UGROWTH_SYNC_LOG_HEADERS.map(function(header) { return row[header] || ''; }));
  } catch (error) {
    // Log sheet failure must not block main data sync.
  }
}

function parsePayload_(e) {
  if (!e || !e.postData || !e.postData.contents) {
    throw new Error('Payload kosong. Kirim request POST JSON dari U-Growth.');
  }
  try {
    return JSON.parse(e.postData.contents);
  } catch (error) {
    throw new Error('Payload bukan JSON valid.');
  }
}

function verifyToken_(payload) {
  const expected = String(PropertiesService.getScriptProperties().getProperty('SYNC_TOKEN') || '').trim();
  if (!expected) {
    return true;
  }
  const received = String(
    (payload && payload.auth && payload.auth.token) ||
    payload.sync_token ||
    payload.token ||
    ''
  ).trim();
  if (received !== expected) {
    throw new Error('Token sinkronisasi tidak valid.');
  }
  return true;
}

function getSpreadsheet_(payloadSpreadsheetId) {
  const props = PropertiesService.getScriptProperties();
  const id = String(payloadSpreadsheetId || props.getProperty('SPREADSHEET_ID') || '').trim();
  if (id) {
    return SpreadsheetApp.openById(id);
  }

  try {
    const active = SpreadsheetApp.getActiveSpreadsheet();
    if (active) {
      return active;
    }
  } catch (error) {
    // Standalone Web App kadang tidak punya active spreadsheet.
  }

  const created = SpreadsheetApp.create('U-Growth Dashboard Data');
  props.setProperty('SPREADSHEET_ID', created.getId());
  return created;
}

function writeRows_(spreadsheet, sheetName, rows, mode) {
  const sheet = getOrCreateSheet_(spreadsheet, sheetName);
  const receivedAt = new Date().toISOString();
  const normalizedRows = rows.map(function (row) {
    const clean = row && typeof row === 'object' ? Object.assign({}, row) : { value: String(row) };
    clean.received_at = clean.received_at || receivedAt;
    return clean;
  });
  const headers = collectHeaders_(sheetName, normalizedRows, sheet);

  if (mode !== 'append') {
    sheet.clearContents();
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  } else if (sheet.getLastRow() === 0) {
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  } else {
    ensureHeaders_(sheet, headers);
  }

  if (normalizedRows.length) {
    const values = normalizedRows.map(function (row) {
      return headers.map(function (header) {
        const value = row[header];
        return safeCell_(value);
      });
    });
    const startRow = sheet.getLastRow() + 1;
    sheet.getRange(startRow, 1, values.length, headers.length).setValues(values);
  }

  sheet.setFrozenRows(1);
  if (headers.length) {
    sheet.autoResizeColumns(1, Math.min(headers.length, 20));
  }

  return {
    rows_written: normalizedRows.length,
    headers: headers
  };
}

function collectHeaders_(sheetName, rows, sheet) {
  const headers = [];
  const defaultHeaders = UGROWTH_DEFAULT_SHEETS[sheetName] || [];
  defaultHeaders.forEach(function (header) {
    pushUnique_(headers, header);
  });

  const existing = readExistingHeaders_(sheet);
  existing.forEach(function (header) {
    pushUnique_(headers, header);
  });

  rows.forEach(function (row) {
    Object.keys(row).forEach(function (key) {
      pushUnique_(headers, normalizeHeader_(key));
    });
  });

  if (!headers.length) {
    headers.push('received_at', 'value');
  }
  return headers;
}

function ensureHeaders_(sheet, desiredHeaders) {
  const existing = readExistingHeaders_(sheet);
  const headers = existing.length ? existing.slice() : [];
  desiredHeaders.forEach(function (header) {
    pushUnique_(headers, header);
  });
  if (!headers.length) return headers;
  sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  return headers;
}

function readExistingHeaders_(sheet) {
  if (!sheet || sheet.getLastRow() < 1 || sheet.getLastColumn() < 1) {
    return [];
  }
  return sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0]
    .map(function (value) { return normalizeHeader_(value); })
    .filter(Boolean);
}

function getOrCreateSheet_(spreadsheet, sheetName) {
  const safeName = normalizeSheetName_(sheetName);
  return spreadsheet.getSheetByName(safeName) || spreadsheet.insertSheet(safeName);
}

function writeDriveBackup_(payload) {
  const folderId = String(payload.drive_folder_id || PropertiesService.getScriptProperties().getProperty('DRIVE_FOLDER_ID') || '').trim();
  if (!folderId) {
    return { ok: false, message: 'Drive Folder ID belum diisi.' };
  }
  const folder = DriveApp.getFolderById(folderId);
  const source = normalizeSheetName_(payload.source || payload.sheet_name || 'ugrowth');
  const filename = source + '-' + Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyyMMdd-HHmmss') + '.json';
  const backupPayload = Object.assign({}, payload);
  delete backupPayload.auth;
  delete backupPayload.token;
  delete backupPayload.sync_token;
  const file = folder.createFile(filename, JSON.stringify(backupPayload, null, 2), MimeType.PLAIN_TEXT);
  return { ok: true, file_id: file.getId(), file_name: file.getName(), file_url: file.getUrl() };
}

function normalizeSheetName_(value) {
  const safe = String(value || 'ugrowth_data')
    .replace(/[\\/?*\[\]:]/g, '_')
    .replace(/\s+/g, '_')
    .replace(/[^a-zA-Z0-9_ -]/g, '')
    .trim();
  return (safe || 'ugrowth_data').slice(0, 80);
}

function normalizeHeader_(value) {
  return String(value || '')
    .replace(/[^a-zA-Z0-9_]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .toLowerCase()
    .slice(0, 120);
}

function pushUnique_(list, value) {
  const item = normalizeHeader_(value);
  if (item && list.indexOf(item) === -1) {
    list.push(item);
  }
}

function jsonResponse_(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data, null, 2))
    .setMimeType(ContentService.MimeType.JSON);
}
