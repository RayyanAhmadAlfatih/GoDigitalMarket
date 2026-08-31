var cc = DataStudioApp.createCommunityConnector();
var DEFAULT_SOURCE = 'orders';
var MAX_LOOKER_ROWS = 5000;
var CONNECTOR_VERSION = '2.0';

function getAuthType() {
  return cc.newAuthTypeResponse().setAuthType(cc.AuthType.NONE).build();
}

function getConfig(request) {
  var config = cc.getConfig();
  config.newInfo()
    .setId('intro')
    .setText('Hubungkan U-Growth langsung ke Looker Studio. Isi URL endpoint dan token dari menu Backup & Sync Data.');
  config.newTextInput()
    .setId('api_url')
    .setName('U-Growth Looker API URL')
    .setHelpText('Contoh: https://domainanda.com/api/looker-studio-data')
    .setPlaceholder('https://domainanda.com/api/looker-studio-data');
  config.newTextInput()
    .setId('api_token')
    .setName('Token koneksi')
    .setHelpText('Token harus sama dengan token Looker Studio Direct di U-Growth.')
    .setPlaceholder('Tempel token rahasia');
  var sourceSelect = config.newSelectSingle()
    .setId('source')
    .setName('Sumber data');
  [
    ['Order / Checkout', 'orders'],
    ['Lead / Data Masuk Form', 'form_submissions'],
    ['Analytics / Event Tracking', 'analytics_events'],
    ['Landing Page Analytics', 'landing_page_analytics'],
    ['Offer & CTA Testing', 'offer_cta_tests'],
    ['CTA Placement', 'cta_placements'],
    ['CTA Result Tracker', 'cta_results'],
    ['SEO Profit Attribution', 'seo_profit_attribution'],
    ['Profit Action Dashboard', 'profit_actions'],
    ['SEO Campaign Calendar', 'seo_campaign_calendar'],
    ['Lead Priority Scoring', 'lead_quality_scores'],
    ['Internal Link & CTA Injection', 'internal_link_cta'],
    ['SEO Content Refresh', 'seo_content_refresh'],
    ['SEO Money Page Optimizer', 'seo_money_pages'],
    ['Pembeli / Member', 'buyer_accounts'],
    ['Akses Produk Digital', 'member_access'],
    ['Bukti Pembayaran', 'payment_proofs'],
    ['Riwayat Email', 'email_logs']
  ].forEach(function(item) {
    sourceSelect.addOption(config.newOptionBuilder().setLabel(item[0]).setValue(item[1]));
  });
  config.setDateRangeRequired(false);
  return config.build();
}

function getSchema(request) {
  var payload = fetchUGrowth_(request, 'schema');
  return { schema: buildFields_(payload.schema || []) };
}

function getData(request) {
  var requestedFields = (request.fields || []).map(function(field) { return field.name; });
  var payload = fetchUGrowth_(request, 'data', requestedFields);
  var schemaPayload = payload.schema || [];
  var schema = buildFields_(schemaPayload);
  var typeByName = {};
  schemaPayload.forEach(function(item) { typeByName[item.name] = item.type || 'TEXT'; });
  var rows = (payload.rows || []).map(function(row) {
    return { values: requestedFields.map(function(name) { return normalizeValue_(row[name], typeByName[name]); }) };
  });
  var filteredSchema = schema.filter(function(field) {
    return requestedFields.indexOf(field.name) !== -1;
  });
  return { schema: filteredSchema, rows: rows };
}

function isAdminUser() {
  return false;
}

function fetchUGrowth_(request, action, fields) {
  var params = request.configParams || {};
  var apiUrl = normalizeApiUrl_(params.api_url);
  var token = String(params.api_token || '').trim();
  var source = normalizeSource_(params.source || DEFAULT_SOURCE);
  if (!apiUrl) {
    throw new Error('U-Growth Looker API URL belum diisi.');
  }
  if (!token) {
    throw new Error('Token koneksi belum diisi.');
  }
  var query = '?action=' + encodeURIComponent(action) + '&source=' + encodeURIComponent(source) + '&limit=' + MAX_LOOKER_ROWS;
  if (fields && fields.length) {
    query += '&fields=' + encodeURIComponent(fields.join(','));
  }
  var lastError = '';
  for (var attempt = 1; attempt <= 2; attempt++) {
    try {
      var response = UrlFetchApp.fetch(apiUrl + query, {
        method: 'get',
        muteHttpExceptions: true,
        followRedirects: true,
        headers: {
          'X-Ugrowth-Looker-Token': token,
          'X-Ugrowth-Token': token,
          'X-Ugrowth-Connector-Version': CONNECTOR_VERSION
        }
      });
      var code = response.getResponseCode();
      var text = response.getContentText();
      if (code < 200 || code >= 300) {
        lastError = 'U-Growth API belum mengembalikan status sukses: ' + code + ' ' + text.slice(0, 160);
        continue;
      }
      var json = JSON.parse(text);
      if (!json.ok) {
        lastError = json.message || 'U-Growth API belum siap.';
        continue;
      }
      return json;
    } catch (error) {
      lastError = error && error.message ? error.message : String(error);
    }
  }
  throw new Error(lastError || 'U-Growth API belum bisa dibaca.');
}

function normalizeApiUrl_(value) {
  var url = String(value || '').trim();
  if (!/^https:\/\//i.test(url)) {
    throw new Error('URL API harus HTTPS.');
  }
  if (url.indexOf('?') !== -1) {
    url = url.split('?')[0];
  }
  return url.replace(/\/+$/, '');
}

function normalizeSource_(value) {
  var source = String(value || DEFAULT_SOURCE).replace(/[^a-zA-Z0-9_]/g, '');
  return source || DEFAULT_SOURCE;
}

function buildFields_(schema) {
  var fields = cc.getFields();
  var types = cc.FieldType;
  var aggregations = cc.AggregationType;
  schema.forEach(function(item) {
    var id = item.name;
    var label = item.label || item.name;
    var typeName = item.type || 'TEXT';
    var field = fields.newDimension().setId(id).setName(label);
    if (typeName === 'NUMBER') {
      field = fields.newMetric().setId(id).setName(label).setType(types.NUMBER).setAggregation(aggregations.SUM);
    } else if (typeName === 'YEAR_MONTH_DAY') {
      field.setType(types.YEAR_MONTH_DAY);
    } else {
      field.setType(types.TEXT);
    }
  });
  return fields.build();
}

function normalizeValue_(value, typeName) {
  if (value === null || typeof value === 'undefined') {
    return '';
  }
  if (typeName === 'YEAR_MONTH_DAY') {
    return normalizeDate_(value);
  }
  if (typeof value === 'object') {
    return JSON.stringify(value);
  }
  return value;
}

function normalizeDate_(value) {
  var raw = String(value || '').trim();
  if (/^\d{8}$/.test(raw)) {
    return raw;
  }
  var parsed = new Date(raw);
  if (isNaN(parsed.getTime())) {
    return '';
  }
  var y = parsed.getUTCFullYear();
  var m = String(parsed.getUTCMonth() + 1);
  var d = String(parsed.getUTCDate());
  if (m.length < 2) m = '0' + m;
  if (d.length < 2) d = '0' + d;
  return String(y) + m + d;
}
