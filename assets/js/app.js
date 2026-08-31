/*
|--------------------------------------------------------------------------
| APP.JS
|--------------------------------------------------------------------------
| Main frontend interaction engine
|--------------------------------------------------------------------------
*/

document.addEventListener(

 'DOMContentLoaded',

 () => {

 initMobileMenu();

 initStickyHeader();

 initSmoothScroll();

 initBackToTop();

 initSearchFormGuard();

 initInquiryForms();

 initShippingEstimator();

 initOrderForms();

 initConversionTracking();

 initPageMarketingEvent();

 }
);

/*
|--------------------------------------------------------------------------
| MOBILE MENU
|--------------------------------------------------------------------------
*/

function initMobileMenu()
{
 const toggle =
 document.querySelector(
 '.mobile-toggle'
 );

 const mobileMenu =
 document.querySelector(
 '.mobile-menu'
 );

 const overlay =
 document.querySelector(
 '.mobile-overlay'
 );

 const closeButton =
 document.querySelector(
 '.mobile-close'
 );

 if (
 !toggle ||
 !mobileMenu ||
 !overlay
 ) {

 return;
 }

 /*
 |--------------------------------------------------------------------------
 | OPEN MENU
 |--------------------------------------------------------------------------
 */

 toggle.addEventListener(

 'click',

 () => {

 mobileMenu.classList.add(
 'active'
 );

 overlay.classList.add(
 'active'
 );

 document.body.classList.add(
 'menu-open'
 );

 toggle.setAttribute(
 'aria-expanded',
 'true'
 );

 }
 );

 /*
 |--------------------------------------------------------------------------
 | CLOSE FUNCTION
 |--------------------------------------------------------------------------
 */

 const closeMenu = () => {

 mobileMenu.classList.remove(
 'active'
 );

 overlay.classList.remove(
 'active'
 );

 document.body.classList.remove(
 'menu-open'
 );

 toggle.setAttribute(
 'aria-expanded',
 'false'
 );
 };

 /*
 |--------------------------------------------------------------------------
 | CLOSE EVENTS
 |--------------------------------------------------------------------------
 */

 overlay.addEventListener(
 'click',
 closeMenu
 );

 if (closeButton) {

 closeButton.addEventListener(
 'click',
 closeMenu
 );
 }

 mobileMenu.querySelectorAll('a').forEach((link) => {
 link.addEventListener('click', closeMenu);
 });

 document.addEventListener('keydown', (event) => {
 if (event.key === 'Escape' && mobileMenu.classList.contains('active')) {
 closeMenu();
 }
 });
}

/*
|--------------------------------------------------------------------------
| STICKY HEADER
|--------------------------------------------------------------------------
*/

function initStickyHeader()
{
 const header =
 document.querySelector(
 '.site-header'
 );

 if (!header) {
 return;
 }

 window.addEventListener(

 'scroll',

 () => {

 if (window.scrollY > 20) {

 header.classList.add(
 'scrolled'
 );

 } else {

 header.classList.remove(
 'scrolled'
 );
 }
 }
 );
}

/*
|--------------------------------------------------------------------------
| SMOOTH SCROLL
|--------------------------------------------------------------------------
*/

function initSmoothScroll()
{
 const links =
 document.querySelectorAll(
 'a[href^="#"]'
 );

 links.forEach((link) => {

 link.addEventListener(

 'click',

 (event) => {

 const targetId =
 link.getAttribute(
 'href'
 );

 if (
 !targetId ||
 targetId === '#'
 ) {

 return;
 }

 const target =
 document.querySelector(
 targetId
 );

 if (!target) {
 return;
 }

 event.preventDefault();

 target.scrollIntoView({

 behavior: 'smooth',

 block: 'start',

 });
 }
 );
 });
}

/*
|--------------------------------------------------------------------------
| BACK TO TOP
|--------------------------------------------------------------------------
*/

function initBackToTop()
{
 const button =
 document.querySelector(
 '.back-to-top'
 );

 if (!button) {
 return;
 }

 window.addEventListener(

 'scroll',

 () => {

 if (window.scrollY > 300) {

 button.classList.add(
 'show'
 );

 } else {

 button.classList.remove(
 'show'
 );
 }
 }
 );

 button.addEventListener(

 'click',

 () => {

 window.scrollTo({

 top: 0,

 behavior: 'smooth',

 });
 }
 );
}

/*
|--------------------------------------------------------------------------
| SEARCH FORM GUARD
|--------------------------------------------------------------------------
*/

function initSearchFormGuard()
{
 const forms = document.querySelectorAll(
 '.header-search-form, .search-page-form'
 );

 forms.forEach((form) => {
 form.addEventListener('submit', (event) => {
 const input = form.querySelector('input[name="q"]');

 if (!input) {
 return;
 }

 input.value = input.value.trim();

 if (input.value === '') {
 event.preventDefault();
 input.focus();
 }
 });
 });
}


/*
|--------------------------------------------------------------------------
| CONVERSION TRACKING
|--------------------------------------------------------------------------
| Anonymous click logging for WhatsApp and key CTA links. This helps the site
| owner understand which pages and article categories generate leads without
| depending on third-party scripts.
|--------------------------------------------------------------------------
*/


function readAbTestDataset(element, fallbackType = '')
{
 if (!element || !element.dataset) {
 return {};
 }

 const testType = String(element.dataset.abTestType || fallbackType || '').slice(0, 40);
 const testId = String(element.dataset.abTestId || '').slice(0, 90);
 const testName = String(element.dataset.abTestName || '').slice(0, 100);
 const variant = String(element.dataset.abVariant || '').toLowerCase().slice(0, 1);
 const variantLabel = String(element.dataset.abVariantLabel || '').slice(0, 80);

 if (!testId || !['a', 'b'].includes(variant)) {
 return {};
 }

 const payload = {
 ab_test_type: testType,
 ab_test_id: testId,
 ab_test_name: testName,
 ab_variant: variant,
 ab_variant_label: variantLabel,
 };

 if (testType === 'cta') {
 payload.ab_cta_test_id = testId;
 payload.ab_cta_test_name = testName;
 payload.ab_cta_variant = variant;
 payload.ab_cta_variant_label = variantLabel;
 }

 if (testType === 'form') {
 payload.ab_form_test_id = testId;
 payload.ab_form_test_name = testName;
 payload.ab_form_variant = variant;
 payload.ab_form_variant_label = variantLabel;
 }

 return payload;
}

function initConversionTracking()
{
 const endpoint = window.__LEAD_TRACKING_ENDPOINT__ || '';

 if (!endpoint) {
 return;
 }

 const links = document.querySelectorAll(
 'a[data-conversion-track="1"], a[href*="wa.me"], a[href*="api.whatsapp.com"], a[href*="checkout"], a[href*="payment"], a[href*="qris"]'
 );

 links.forEach((link) => {
 if (link.dataset.conversionBound === '1') {
 return;
 }

 link.dataset.conversionBound = '1';

 link.addEventListener('click', () => {
 const parentContext = link.closest('[data-cta-source], [data-lead-source], [data-landing-block], [data-lp-block-type]');
 const href = link.href || '';
 const isWhatsapp = href.includes('wa.me') || href.includes('api.whatsapp.com');
 const inferredChannel = isWhatsapp
 ? 'whatsapp'
 : (href.includes('checkout') ? 'checkout' : (href.includes('payment') || href.includes('qris') ? 'payment' : 'click'));

 const payload = Object.assign({
 source: link.dataset.leadSource || parentContext?.dataset.ctaSource || parentContext?.dataset.leadSource || 'website',
 type: link.dataset.leadType || (isWhatsapp ? 'whatsapp' : inferredChannel),
 channel: link.dataset.leadChannel || parentContext?.dataset.leadChannel || inferredChannel,
 category: link.dataset.leadCategory || parentContext?.dataset.ctaCategory || parentContext?.dataset.leadCategory || '',
 location: link.dataset.leadLocation || parentContext?.dataset.ctaLocation || parentContext?.dataset.leadLocation || '',
 intent: link.dataset.leadIntent || parentContext?.dataset.leadIntent || '',
 label: link.dataset.leadLabel || link.textContent.trim().replace(/\s+/g, ' ').slice(0, 120),
 cta_deployment_id: link.dataset.ctaDeploymentId || parentContext?.dataset.ctaDeploymentId || '',
 offer_variant_id: link.dataset.offerVariantId || parentContext?.dataset.offerVariantId || '',
 cta_placement: link.dataset.ctaPlacement || parentContext?.dataset.ctaPlacement || '',
 landing_page_slug: link.dataset.landingPageSlug || parentContext?.dataset.landingPageSlug || '',
 landing_page_id: link.dataset.landingPageId || parentContext?.dataset.landingPageId || '',
 block_type: link.dataset.lpBlockType || parentContext?.dataset.lpBlockType || parentContext?.dataset.landingBlock || '',
 block_index: link.dataset.lpBlockIndex || parentContext?.dataset.lpBlockIndex || '',
 block_goal: link.dataset.lpBlockGoal || parentContext?.dataset.lpGoal || parentContext?.dataset.lpBlockGoal || '',
 cta_role: link.dataset.lpCtaRole || parentContext?.dataset.lpCtaRole || '',
 cta_signal: link.dataset.ctaSignal || parentContext?.dataset.ctaSignal || '',
 cta_signal_label: link.dataset.ctaSignalLabel || parentContext?.dataset.ctaSignalLabel || '',
 page_path: window.location.pathname + window.location.search,
 target_url: href,
 }, readAbTestDataset(link, link.dataset.abTestType || 'cta'));

 sendConversionEvent(endpoint, payload);
 }, { passive: true });
 });

 const forms = document.querySelectorAll('form[data-conversion-track="1"], form[data-lead-form="1"]');

 forms.forEach((form) => {
 if (form.dataset.inquiryForm === '1' || form.dataset.orderForm === '1') {
 return;
 }

 if (form.dataset.conversionBound === '1') {
 return;
 }

 form.dataset.conversionBound = '1';

 form.addEventListener('submit', () => {
 const payload = Object.assign({
 source: form.dataset.leadSource || form.dataset.ctaSource || 'form',
 type: form.dataset.leadType || 'form_submit',
 channel: form.dataset.leadChannel || 'form',
 category: form.dataset.leadCategory || form.dataset.ctaCategory || '',
 location: form.dataset.leadLocation || form.dataset.ctaLocation || '',
 intent: form.dataset.leadIntent || 'submit-form',
 label: form.dataset.leadLabel || form.getAttribute('aria-label') || form.getAttribute('name') || 'Form submit',
 cta_deployment_id: form.dataset.ctaDeploymentId || '',
 offer_variant_id: form.dataset.offerVariantId || '',
 cta_placement: form.dataset.ctaPlacement || '',
 landing_page_slug: form.dataset.landingPageSlug || '',
 landing_page_id: form.dataset.landingPageId || '',
 block_type: form.dataset.lpBlockType || '',
 block_index: form.dataset.lpBlockIndex || '',
 block_goal: form.dataset.lpBlockGoal || '',
 cta_role: form.dataset.lpCtaRole || '',
 cta_signal: form.dataset.ctaSignal || 'lead_form_submit',
 cta_signal_label: form.dataset.ctaSignalLabel || '',
 page_path: window.location.pathname + window.location.search,
 target_url: form.getAttribute('action') || window.location.href,
 }, readAbTestDataset(form, form.dataset.abTestType || 'form'));

 sendConversionEvent(endpoint, payload);
 }, { passive: true });
 });
}


function generateMarketingEventId(prefix = 'evt')
{
 const random = Math.random().toString(36).slice(2, 12);
 const time = Date.now().toString(36);
 return `${prefix}_${time}_${random}`.replace(/[^A-Za-z0-9_-]/g, '').slice(0, 90);
}

function ensureMarketingEventId(payload, prefix = 'evt')
{
 if (!payload || typeof payload !== 'object') {
 return generateMarketingEventId(prefix);
 }

 if (!payload.event_id) {
 payload.event_id = generateMarketingEventId(prefix);
 }

 payload.event_id = String(payload.event_id || '').replace(/[^A-Za-z0-9_-]/g, '').slice(0, 90);
 return payload.event_id;
}

function normalizeMarketingEventName(payload)
{
 const text = [
 payload.type || '',
 payload.channel || '',
 payload.intent || '',
 payload.label || '',
 payload.page_path || '',
 payload.target_url || '',
 ].join(' ').toLowerCase();

 if ((payload.channel || '').toLowerCase() === 'whatsapp' || text.includes('wa.me') || text.includes('whatsapp')) {
 return 'contact_whatsapp';
 }

 if (text.includes('order_success') || text.includes('order-success') || text.includes('order berhasil')) {
 return 'order_success';
 }

 if (text.includes('order_submit') || text.includes('checkout') || (payload.channel || '').toLowerCase() === 'checkout') {
 return 'begin_checkout';
 }

 if (text.includes('inquiry') || text.includes('form_submit')) {
 return 'submit_inquiry';
 }

 if (text.includes('payment-proof') || text.includes('proof_submit') || text.includes('bukti pembayaran')) {
 return 'upload_payment_proof';
 }

 if (text.includes('/invoice') || text.includes('invoice')) {
 return 'view_invoice';
 }

 if (text.includes('order-status')) {
 return 'check_order_status';
 }

 return 'select_item';
}

function safeUrlPart(url, part)
{
 try {
 const parsed = new URL(url, window.location.origin);
 return part === 'host' ? parsed.host : parsed.pathname;
 } catch (error) {
 return '';
 }
}


function adsPixelStandardEventName(eventName)
{
 switch (eventName) {
 case 'contact_whatsapp':
 return { meta: 'Contact', tiktok: 'Contact', microsoft: 'contact', google: 'contact' };
 case 'begin_checkout':
 return { meta: 'InitiateCheckout', tiktok: 'InitiateCheckout', microsoft: 'begin_checkout', google: 'begin_checkout' };
 case 'submit_inquiry':
 return { meta: 'Lead', tiktok: 'SubmitForm', microsoft: 'submit_inquiry', google: 'generate_lead' };
 case 'upload_payment_proof':
 return { meta: 'Lead', tiktok: 'SubmitForm', microsoft: 'payment_proof', google: 'payment_proof' };
 case 'order_success':
 return { meta: 'Purchase', tiktok: 'CompletePayment', microsoft: 'purchase', google: 'purchase' };
 case 'view_invoice':
 case 'check_order_status':
 return { meta: 'ViewContent', tiktok: 'ViewContent', microsoft: 'page_view', google: 'page_view' };
 default:
 return { meta: 'ViewContent', tiktok: 'ViewContent', microsoft: 'select_item', google: 'select_item' };
 }
}

function safePixelParams(payload, eventName, eventGroup)
{
 return {
 event_id: String(payload.event_id || '').slice(0, 90),
 event_name: String(eventName || '').slice(0, 60),
 event_group: String(eventGroup || '').slice(0, 40),
 content_name: String(payload.label || payload.category || eventName || '').slice(0, 120),
 content_category: String(payload.category || '').slice(0, 80),
 content_type: String(payload.type || '').slice(0, 60),
 page_path: String(payload.page_path || window.location.pathname).slice(0, 180),
 target_path: safeUrlPart(payload.target_url || '', 'path').slice(0, 180),
 lead_channel: String(payload.channel || '').slice(0, 60),
 lead_intent: String(payload.intent || '').slice(0, 80),
 pii_safe: true,
 };
}


function googleAdsSendToForEvent(config, eventName)
{
 const google = config.google_ads || {};
 const conversionId = String(google.conversion_id || '').trim();
 if (!conversionId || google.fire_conversion_events === false) {
 return '';
 }

 const events = google.event_labels || {};
 const row = events[eventName] || {};
 if (row.enabled === false) {
 return '';
 }

 const label = String(row.conversion_label || google.conversion_label || '').trim();
 return label ? `${conversionId}/${label}` : '';
}

function googleAdsLabelForEvent(config, eventName, payload)
{
 const row = ((config.google_ads || {}).event_labels || {})[eventName] || {};
 return String(row.label || payload.label || eventName || '').slice(0, 120);
}


function marketingPixelDedupeStorageKey(eventName, eventId, eventGroup)
{
 const name = String(eventName || 'event').replace(/[^A-Za-z0-9_-]/g, '').slice(0, 60) || 'event';
 const id = String(eventId || '').replace(/[^A-Za-z0-9_-]/g, '').slice(0, 90) || [window.location.pathname, name, eventGroup || 'interaction'].join('_').replace(/[^A-Za-z0-9_-]/g, '').slice(0, 90);
 return `hq_ads_pixel_fired_${name}_${id}`;
}

function rememberMarketingPixelFire(eventName, eventId, eventGroup, ttlSeconds = 1800)
{
 try {
 if (eventGroup !== 'conversion') {
 return true;
 }

 const key = marketingPixelDedupeStorageKey(eventName, eventId, eventGroup);
 const now = Date.now();
 const ttl = Math.max(60, Math.min(86400, Number(ttlSeconds || 1800))) * 1000;
 const existing = window.sessionStorage ? Number(window.sessionStorage.getItem(key) || '0') : 0;

 if (existing && now - existing < ttl) {
 window.dispatchEvent(new CustomEvent('adsPixelTrackingDebug', {
 detail: { status: 'deduped', event_name: eventName, event_id: eventId || '', event_group: eventGroup }
 }));
 return false;
 }

 if (window.sessionStorage) {
 window.sessionStorage.setItem(key, String(now));
 }
 return true;
 } catch (error) {
 return true;
 }
}

function googleAdsDebugDispatch(detail)
{
 try {
 window.dispatchEvent(new CustomEvent('adsPixelTrackingDebug', { detail }));
 } catch (error) {
 // Debug event is optional.
 }
}

function dispatchDirectAdsPixels(eventName, payload, eventGroup)
{
 try {
 const config = window.__ADS_PIXEL_CONFIG__ || {};
 if (!config.direct_enabled) {
 return;
 }

 const mapped = adsPixelStandardEventName(eventName);
 const params = safePixelParams(payload, eventName, eventGroup);
 const dedupeTtl = Number((config.google_ads && config.google_ads.dedupe_ttl_seconds) || config.dedupe_ttl_seconds || 1800);

 if (!rememberMarketingPixelFire(eventName, params.event_id, eventGroup, dedupeTtl)) {
 return;
 }

 if (config.meta && config.meta.enabled && typeof window.fbq === 'function') {
 window.fbq('track', mapped.meta, params, params.event_id ? { eventID: params.event_id } : undefined);
 }

 if (config.tiktok && config.tiktok.enabled && window.ttq && typeof window.ttq.track === 'function') {
 window.ttq.track(mapped.tiktok, params);
 }

 if (config.microsoft_uet && config.microsoft_uet.enabled) {
 window.uetq = window.uetq || [];
 window.uetq.push('event', mapped.microsoft, params);
 }

 if (config.google_ads && config.google_ads.enabled && typeof window.gtag === 'function') {
 const sendTo = googleAdsSendToForEvent(config, eventName);
 if (sendTo && (eventGroup === 'conversion' || eventName === 'order_success')) {
 window.gtag('event', 'conversion', {
 send_to: sendTo,
 event_category: 'Website Conversion',
 event_label: googleAdsLabelForEvent(config, eventName, payload),
 event_id: params.event_id || undefined,
 transaction_id: params.event_id || undefined,
 page_path: params.page_path,
 content_name: params.content_name,
 pii_safe: true,
 });
 googleAdsDebugDispatch({
 status: 'fired',
 platform: 'google_ads',
 event_name: eventName,
 event_id: params.event_id || '',
 event_group: eventGroup,
 send_to: sendTo,
 label: googleAdsLabelForEvent(config, eventName, payload),
 page_path: params.page_path,
 });
 }
 }

 // LinkedIn Insight base tag records page views. Conversion IDs can be managed in GTM for advanced setups.

 if (config.debug && window.console && typeof window.console.debug === 'function') {
 window.console.debug('[AdsPixel]', eventName, params);
 }
 } catch (error) {
 // Direct pixels are optional and must never block UX.
 }
}

function pushMarketingDataLayerEvent(payload)
{
 try {
 window.dataLayer = window.dataLayer || [];
 ensureMarketingEventId(payload, 'evt');

 const eventName = normalizeMarketingEventName(payload);
 const eventGroup = ['contact_whatsapp', 'begin_checkout', 'submit_inquiry', 'upload_payment_proof', 'order_success'].includes(eventName)
 ? 'conversion'
 : (['view_invoice', 'check_order_status'].includes(eventName) ? 'page_view' : 'interaction');

 const config = window.__ADS_PIXEL_CONFIG__ || {};
 const sendTo = googleAdsSendToForEvent(config, eventName);
 const dataLayerPayload = {
 event: eventName,
 event_id: String(payload.event_id || '').slice(0, 90),
 event_group: eventGroup,
 lead_source: String(payload.source || '').slice(0, 80),
 lead_type: String(payload.type || '').slice(0, 60),
 lead_channel: String(payload.channel || '').slice(0, 60),
 lead_category: String(payload.category || '').slice(0, 80),
 lead_location: String(payload.location || '').slice(0, 80),
 lead_intent: String(payload.intent || '').slice(0, 80),
 lead_label: String(payload.label || '').slice(0, 120),
 landing_page_slug: String(payload.landing_page_slug || '').slice(0, 120),
 lp_block_type: String(payload.block_type || '').slice(0, 60),
 lp_block_index: String(payload.block_index || '').slice(0, 20),
 lp_block_goal: String(payload.block_goal || '').slice(0, 40),
 lp_cta_role: String(payload.cta_role || '').slice(0, 40),
 cta_signal: String(payload.cta_signal || '').slice(0, 90),
 cta_signal_label: String(payload.cta_signal_label || '').slice(0, 120),
 ab_test_type: String(payload.ab_test_type || '').slice(0, 40),
 ab_test_id: String(payload.ab_test_id || '').slice(0, 90),
 ab_test_name: String(payload.ab_test_name || '').slice(0, 100),
 ab_variant: String(payload.ab_variant || '').slice(0, 10),
 ab_variant_label: String(payload.ab_variant_label || '').slice(0, 80),
 ab_cta_test_name: String(payload.ab_cta_test_name || '').slice(0, 100),
 ab_cta_variant: String(payload.ab_cta_variant || '').slice(0, 10),
 ab_form_test_name: String(payload.ab_form_test_name || '').slice(0, 100),
 ab_form_variant: String(payload.ab_form_variant || '').slice(0, 10),
 page_path: String(payload.page_path || window.location.pathname).slice(0, 180),
 target_path: safeUrlPart(payload.target_url || '', 'path').slice(0, 180),
 target_host: safeUrlPart(payload.target_url || '', 'host').slice(0, 120),
 google_ads_ready: Boolean(sendTo),
 google_ads_send_to: String(sendTo || '').slice(0, 140),
 google_ads_event_label: googleAdsLabelForEvent(config, eventName, payload),
 pii_safe: true,
 };

 window.dataLayer.push(dataLayerPayload);
 googleAdsDebugDispatch({ status: 'datalayer', platform: 'dataLayer', event_name: eventName, event_id: dataLayerPayload.event_id, event_group: eventGroup, send_to: dataLayerPayload.google_ads_send_to });

 dispatchDirectAdsPixels(eventName, payload, eventGroup);
 } catch (error) {
 // dataLayer is optional and must never block UX.
 }
}

function initPageMarketingEvent()
{
 try {
 const payload = window.__MARKETING_PAGE_EVENT__ || null;
 if (!payload || window.__MARKETING_PAGE_EVENT_SENT__) {
 return;
 }
 window.__MARKETING_PAGE_EVENT_SENT__ = true;
 const endpoint = window.__LEAD_TRACKING_ENDPOINT__ || '';
 if (endpoint && String(payload.source || '') === 'landing-page-builder') {
 sendConversionEvent(endpoint, payload);
 } else {
 pushMarketingDataLayerEvent(payload);
 }
 } catch (error) {
 // Page marketing event is optional and must never block UX.
 }
}

function sendConversionEvent(endpoint, payload)
{
 ensureMarketingEventId(payload, 'evt');
 pushMarketingDataLayerEvent(payload);

 try {
 const body = JSON.stringify(payload);

 if (navigator.sendBeacon) {
 const blob = new Blob([body], { type: 'application/json' });
 navigator.sendBeacon(endpoint, blob);
 return;
 }

 fetch(endpoint, {
 method: 'POST',
 headers: {
 'Content-Type': 'application/json',
 },
 body,
 keepalive: true,
 credentials: 'same-origin',
 }).catch(() => {});
 } catch (error) {
 // Tracking should never block navigation or user interaction.
 }
}


/*
|--------------------------------------------------------------------------
| FORM INQUIRY AJAX
|--------------------------------------------------------------------------
| Lightweight enhancement for public inquiry forms. Non-JS fallback still
| submits to the same endpoint, but JS keeps users on the current page.
|--------------------------------------------------------------------------
*/

function initInquiryForms()
{
 const forms = document.querySelectorAll('form[data-inquiry-form="1"]');

 forms.forEach((form) => {
 if (form.dataset.inquiryBound === '1') {
 return;
 }

 form.dataset.inquiryBound = '1';

 form.addEventListener('submit', async (event) => {
 event.preventDefault();

 const status = form.querySelector('.inquiry-form__status');
 const button = form.querySelector('button[type="submit"]');
 const action = form.getAttribute('action') || window.location.href;
 const formData = new FormData(form);
 const trackingPayload = Object.assign({
 source: form.dataset.leadSource || 'inquiry-form',
 type: 'form_submit',
 channel: 'form',
 category: form.dataset.leadCategory || formData.get('category') || '',
 location: form.dataset.leadLocation || formData.get('location') || '',
 intent: form.dataset.leadIntent || 'inquiry',
 label: form.dataset.leadLabel || 'Form Permintaan',
 page_path: window.location.pathname + window.location.search,
 target_url: action,
 }, readAbTestDataset(form, form.dataset.abTestType || 'form'));
 formData.append('server_event_id', ensureMarketingEventId(trackingPayload, 'inq'));

 if (status) {
 status.className = 'inquiry-form__status is-loading';
 status.textContent = 'Mengirim inquiry...';
 }

 if (button) {
 button.disabled = true;
 button.dataset.originalText = button.textContent;
 button.textContent = 'Mengirim...';
 }

 try {
 const response = await fetch(action, {
 method: 'POST',
 body: formData,
 headers: {
 'Accept': 'application/json',
 'X-Requested-With': 'fetch',
 },
 credentials: 'same-origin',
 });

 const data = await response.json().catch(() => ({}));

 if (!response.ok || !data.ok) {
 throw new Error(data.message || 'Permintaan belum bisa dikirim.');
 }

 if (status) {
 status.className = 'inquiry-form__status is-success';
 status.textContent = data.message || 'Permintaan berhasil dikirim.';
 }

 pushMarketingDataLayerEvent(trackingPayload);

 form.reset();
 } catch (error) {
 if (status) {
 status.className = 'inquiry-form__status is-error';
 status.textContent = error.message || 'Permintaan belum bisa dikirim. Coba lagi beberapa saat.';
 }
 } finally {
 if (button) {
 button.disabled = false;
 button.textContent = button.dataset.originalText || 'Kirim Permintaan';
 }
 }
 });
 });
}



/*
|--------------------------------------------------------------------------
| CHECKOUT SHIPPING ESTIMATOR - Template
|--------------------------------------------------------------------------
| Lightweight client-side preview for the file-based manual ongkir rules.
| Server still recalculates the final estimate on submit.
|--------------------------------------------------------------------------
*/

function initShippingEstimator()
{
 const widgets = document.querySelectorAll('[data-shipping-estimator]');
 if (!widgets.length) return;

 const formatRupiah = (value) => {
 const amount = Math.max(0, Number(value || 0));
 try {
 return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
 } catch (error) {
 return 'Rp ' + String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
 }
 };

 const normalize = (value) => String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();

 const isFreeFlowMethod = (method, config) => {
 const normalized = normalize(method);
 return (config.pickup_keywords || []).some((keyword) => {
 keyword = normalize(keyword);
 return keyword && normalized.includes(keyword);
 });
 };

 widgets.forEach((widget) => {
 let config = {};
 try {
 config = JSON.parse(widget.getAttribute('data-shipping-estimator') || '{}');
 } catch (error) {
 config = {};
 }
 if (!config.enabled) return;

 const form = widget.closest('form');
 if (!form) return;

 const amountNode = widget.querySelector('[data-shipping-estimator-amount]');
 const statusNode = widget.querySelector('[data-shipping-estimator-status]');
 const resultsNode = widget.querySelector('[data-shipping-estimator-results]');
 const button = widget.querySelector('[data-shipping-estimator-check]');
 const fields = ['city', 'district', 'province', 'postal_code', 'address_line', 'location', 'shipping_method', 'quantity'];

 const readValue = (name) => {
 const field = form.querySelector('[name="' + name + '"]');
 return field ? field.value : '';
 };

 const writeHidden = (key, value) => {
 const field = form.querySelector('[data-shipping-field="' + key + '"]');
 if (field) field.value = value || '';
 };

 const clearQuote = () => {
 ['provider', 'courier', 'service', 'service_label', 'option_id', 'cache_key', 'destination_code'].forEach((key) => writeHidden(key, ''));
 writeHidden('quote_source', 'manual');
 if (resultsNode) {
 resultsNode.hidden = true;
 resultsNode.innerHTML = '';
 }
 };

 const matchRule = (destination) => {
 const haystack = normalize(destination);
 if (!haystack) return null;
 return (config.rules || []).find((rule) => {
 return (rule.keywords || []).some((keyword) => {
 keyword = normalize(keyword);
 return keyword && haystack.includes(keyword);
 });
 }) || null;
 };

 const manualEstimate = () => {
 const method = readValue('shipping_method');
 const destination = [readValue('district'), readValue('city'), readValue('location'), readValue('province'), readValue('postal_code'), readValue('address_line')].join(' ');
 const qty = Math.max(1, Math.min(999, parseInt(readValue('quantity') || '1', 10) || 1));
 const subtotal = Math.max(0, Number(config.price || 0) * qty);
 const rawWeight = Math.max(0.1, Number(config.unit_weight_kg || 1) * qty);
 const weight = config.round_weight_up ? Math.max(1, Math.ceil(rawWeight)) : rawWeight;

 if (method && isFreeFlowMethod(method, config)) {
 return {
 total: 0,
 status: 'Metode ini tidak memakai ongkir ekspedisi.',
 label: method || 'Pickup / tidak perlu pengiriman',
 eta: 'Sesuai jadwal admin/customer',
 source: 'manual',
 estimated: true,
 };
 }

 const rule = matchRule(destination) || (config.fallback_enabled ? {
 name: 'Fallback Manual',
 base_cost: Number(config.fallback_base_cost || 0),
 per_kg: Number(config.fallback_per_kg || 0),
 eta: config.fallback_eta || 'Konfirmasi admin',
 note: 'Kota belum masuk zona khusus, memakai tarif fallback manual.'
 } : null);

 if (!rule) {
 return {
 total: null,
 status: 'Isi kota/kabupaten agar sistem mencari zona ongkir manual.',
 label: '',
 eta: '',
 source: 'manual',
 estimated: false,
 };
 }

 const baseCost = Number(rule.base_cost || 0);
 const perKg = Number(rule.per_kg || 0);
 const handling = Number(config.handling_fee || 0);
 const threshold = Number(config.free_shipping_threshold || 0);
 const shippingCost = Math.max(0, baseCost + (perKg * Math.max(1, Math.ceil(weight))));
 const discount = threshold > 0 && subtotal >= threshold ? shippingCost : 0;
 const total = Math.max(0, shippingCost + handling - discount);
 const freeText = discount > 0 ? ' • free ongkir aktif' : '';

 return {
 total,
 status: (rule.name || 'Zona Ongkir') + ' • berat tagihan ' + weight + ' kg • ETA ' + (rule.eta || 'konfirmasi admin') + freeText,
 label: rule.name || 'Zona Ongkir',
 eta: rule.eta || '',
 source: 'manual',
 estimated: true,
 };
 };

 const applyManualPreview = () => {
 const estimate = manualEstimate();
 clearQuote();
 writeHidden('quote_source', 'manual');
 writeHidden('service_label', estimate.label || '');
 if (amountNode) amountNode.textContent = estimate.total === null ? 'Konfirmasi admin' : formatRupiah(estimate.total);
 if (statusNode) statusNode.textContent = estimate.status;
 widget.classList.toggle('is-estimated', !!estimate.estimated);
 };

 const renderApiOptions = (estimate) => {
 const options = Array.isArray(estimate.options) ? estimate.options : [];
 if (!resultsNode) return;
 resultsNode.innerHTML = '';
 if (!options.length) {
 resultsNode.hidden = true;
 return;
 }
 const title = document.createElement('div');
 title.className = 'order-shipping-estimator__result-title';
 title.textContent = 'Pilih layanan pengiriman:';
 resultsNode.appendChild(title);

 options.slice(0, 8).forEach((option, index) => {
 const id = 'shipping-option-' + (option.id || index);
 const label = document.createElement('label');
 label.className = 'order-shipping-option';
 label.setAttribute('for', id);
 const input = document.createElement('input');
 input.type = 'radio';
 input.id = id;
 input.name = 'shipping_option_ui';
 input.value = option.id || '';
 input.checked = (estimate.selected_option_id && option.id === estimate.selected_option_id) || (!estimate.selected_option_id && index === 0);
 const body = document.createElement('span');
 body.innerHTML = '<strong>' + (option.label || 'Layanan') + '</strong><small>' + formatRupiah((option.total ?? option.cost) || 0) + (option.eta ? ' · ETA ' + option.eta : '') + '</small>';
 label.appendChild(input);
 label.appendChild(body);
 resultsNode.appendChild(label);

 input.addEventListener('change', () => {
 if (!input.checked) return;
 writeHidden('quote_source', estimate.quote_source || 'api');
 writeHidden('provider', estimate.provider || '');
 writeHidden('courier', option.courier || '');
 writeHidden('service', option.service || '');
 writeHidden('service_label', option.label || '');
 writeHidden('option_id', option.id || '');
 writeHidden('cache_key', estimate.cache_key || '');
 writeHidden('destination_code', estimate.destination_code || '');
 if (amountNode) amountNode.textContent = formatRupiah((option.total ?? option.cost) || 0);
 if (statusNode) statusNode.textContent = (option.label || 'Layanan') + (option.eta ? ' • ETA ' + option.eta : '') + (estimate.cache_status && estimate.cache_status !== 'none' ? ' • cache ' + estimate.cache_status : '');
 });

 if (input.checked) {
 input.dispatchEvent(new Event('change'));
 }
 });
 resultsNode.hidden = false;
 };

 const applyServerEstimate = (estimate) => {
 if (!estimate || typeof estimate !== 'object') return;
 writeHidden('quote_source', estimate.quote_source || 'manual');
 writeHidden('provider', estimate.provider || '');
 writeHidden('courier', estimate.courier || '');
 writeHidden('service', estimate.service || '');
 writeHidden('service_label', estimate.service_label || estimate.rule_name || '');
 writeHidden('option_id', estimate.selected_option_id || '');
 writeHidden('cache_key', estimate.cache_key || '');
 writeHidden('destination_code', estimate.destination_code || '');

 if (amountNode) amountNode.textContent = formatRupiah(estimate.total || 0);
 if (statusNode) {
 const chunks = [];
 chunks.push(estimate.service_label || estimate.rule_name || 'Estimasi ongkir');
 if (estimate.billable_weight_kg) chunks.push('berat ' + estimate.billable_weight_kg + ' kg');
 if (estimate.eta) chunks.push('ETA ' + estimate.eta);
 if (estimate.quote_source) chunks.push(estimate.quote_source);
 if (estimate.cache_status && estimate.cache_status !== 'none') chunks.push('cache ' + estimate.cache_status);
 if (estimate.api_error_note) chunks.push('API fallback');
 statusNode.textContent = chunks.join(' • ');
 }
 widget.classList.add('is-estimated');
 renderApiOptions(estimate);
 };

 const requestServerEstimate = async () => {
 if (!config.estimate_endpoint) {
 applyManualPreview();
 return;
 }
 if (button) {
 button.disabled = true;
 button.dataset.originalText = button.textContent;
 button.textContent = 'Mengecek...';
 }
 if (statusNode) statusNode.textContent = 'Mengecek ongkir. Sebentar ya...';
 try {
 const formData = new FormData(form);
 formData.append('shipping_estimate_action', 'estimate');
 const response = await fetch(config.estimate_endpoint, {
 method: 'POST',
 body: formData,
 headers: {
 'Accept': 'application/json',
 'X-Requested-With': 'fetch',
 },
 credentials: 'same-origin',
 });
 const data = await response.json().catch(() => ({}));
 if (!response.ok || !data.ok) {
 throw new Error(data.message || 'Ongkir belum bisa dicek.');
 }
 applyServerEstimate(data.estimate || {});
 } catch (error) {
 if (statusNode) statusNode.textContent = (error && error.message) ? error.message : 'Ongkir belum bisa dicek. Admin akan konfirmasi manual.';
 applyManualPreview();
 } finally {
 if (button) {
 button.disabled = false;
 button.textContent = button.dataset.originalText || 'Cek Ongkir';
 }
 }
 };

 fields.forEach((name) => {
 form.querySelectorAll('[name="' + name + '"]').forEach((field) => {
 field.addEventListener('input', () => {
 if (config.api_enabled) {
 clearQuote();
 if (statusNode) statusNode.textContent = 'Alamat berubah. Klik Cek Ongkir untuk update estimasi.';
 if (amountNode) amountNode.textContent = 'Belum dicek';
 widget.classList.remove('is-estimated');
 } else {
 applyManualPreview();
 }
 });
 field.addEventListener('change', () => {
 if (config.api_enabled) {
 clearQuote();
 if (statusNode) statusNode.textContent = 'Alamat berubah. Klik Cek Ongkir untuk update estimasi.';
 if (amountNode) amountNode.textContent = 'Belum dicek';
 widget.classList.remove('is-estimated');
 } else {
 applyManualPreview();
 }
 });
 });
 });

 if (button) {
 button.addEventListener('click', requestServerEstimate);
 button.hidden = false;
 }

 if (config.api_enabled) {
 clearQuote();
 if (amountNode) amountNode.textContent = 'Belum dicek';
 } else {
 if (button) button.hidden = true;
 applyManualPreview();
 }
 });
}

/*
|--------------------------------------------------------------------------
| ORDER / CHECKOUT DRAFT FORM AJAX - Template
|--------------------------------------------------------------------------
| Keeps users on the page while sending structured order intent to the
| file-based admin order inbox. Non-JS fallback still submits normally.
|--------------------------------------------------------------------------
*/

function initOrderForms()
{
 const forms = document.querySelectorAll('form[data-order-form="1"]');

 forms.forEach((form) => {
 if (form.dataset.orderBound === '1') {
 return;
 }

 form.dataset.orderBound = '1';

 form.addEventListener('submit', async (event) => {
 event.preventDefault();

 const status = form.querySelector('.order-form__status');
 const button = form.querySelector('button[type="submit"]');
 const action = form.getAttribute('action') || window.location.href;
 const formData = new FormData(form);
 const trackingPayload = Object.assign({
 source: form.dataset.leadSource || 'order-form',
 type: 'order_submit',
 channel: 'checkout',
 category: form.dataset.leadCategory || formData.get('category') || '',
 location: form.dataset.leadLocation || formData.get('location') || '',
 intent: form.dataset.leadIntent || 'order-draft',
 label: form.dataset.leadLabel || formData.get('product_title') || 'Order Draft',
 page_path: window.location.pathname + window.location.search,
 target_url: action,
 }, readAbTestDataset(form, form.dataset.abTestType || 'form'));
 formData.append('server_event_id', ensureMarketingEventId(trackingPayload, 'ord'));

 if (status) {
 status.className = 'order-form__status is-loading';
 status.textContent = 'Mengirim data pemesanan...';
 }

 if (button) {
 button.disabled = true;
 button.dataset.originalText = button.textContent;
 button.textContent = 'Mengirim...';
 }

 try {
 const response = await fetch(action, {
 method: 'POST',
 body: formData,
 headers: {
 'Accept': 'application/json',
 'X-Requested-With': 'fetch',
 },
 credentials: 'same-origin',
 });

 const data = await response.json().catch(() => ({}));

 if (!response.ok || !data.ok) {
 throw new Error(data.message || 'Data pemesanan belum bisa dikirim.');
 }

 if (status) {
 status.className = 'order-form__status is-success';
 status.textContent = data.message || 'Data pemesanan berhasil dikirim.';
 }

 pushMarketingDataLayerEvent(trackingPayload);

 if (data.redirect_url) {
 window.location.href = data.redirect_url;
 return;
 }

 form.reset();
 } catch (error) {
 if (status) {
 status.className = 'order-form__status is-error';
 status.textContent = error.message || 'Data pemesanan belum bisa dikirim. Coba lagi beberapa saat.';
 }
 } finally {
 if (button) {
 button.disabled = false;
 button.textContent = button.dataset.originalText || 'Ajukan Pemesanan';
 }
 }
 });
 });
}

/* Template SEO Quality Assistant live hints */
(function () {
 const assistantCards = document.querySelectorAll('[data-seo-quality-assistant]');
 if (!assistantCards.length) return;

 const textLength = (selector) => {
 const el = document.querySelector(selector);
 if (!el) return 0;
 const tmp = document.createElement('div');
 tmp.innerHTML = el.value || '';
 return (tmp.textContent || tmp.innerText || '').trim().length;
 };

 const value = (selector) => (document.querySelector(selector)?.value || '').trim();

 const update = () => {
 const title = value('[name="title"]');
 const metaTitle = value('[name="meta_title"]');
 const metaDescription = value('[name="meta_description"]');
 const imageAlt = value('[name="image_alt"]');
 const contentLen = textLength('[name="content"]') || textLength('[name="description"]');
 const hints = [];

 if (title && title.length < 25) hints.push('Judul masih pendek. Tambahkan jenis produk/layanan dan lokasi bila relevan.');
 if (!metaTitle) hints.push('Meta title masih kosong. Isi 45-65 karakter.');
 else if (metaTitle.length < 35) hints.push('Meta title masih pendek: ' + metaTitle.length + ' karakter.');
 else if (metaTitle.length > 70) hints.push('Meta title agak panjang: ' + metaTitle.length + ' karakter.');

 if (!metaDescription) hints.push('Meta description masih kosong. Isi 120-160 karakter.');
 else if (metaDescription.length < 90) hints.push('Meta description masih pendek: ' + metaDescription.length + ' karakter.');
 else if (metaDescription.length > 170) hints.push('Meta description agak panjang: ' + metaDescription.length + ' karakter.');

 if (!imageAlt) hints.push('Alt gambar masih kosong. Isi deskripsi gambar yang natural.');
 if (contentLen > 0 && contentLen < 250) hints.push('Konten/deskripsi masih tipis. Tambahkan detail layanan, lokasi, dan CTA.');

 assistantCards.forEach((card) => {
 const box = card.querySelector('[data-seo-live-hints]');
 if (!box) return;
 box.innerHTML = '';
 hints.slice(0, 5).forEach((hint) => {
 const div = document.createElement('div');
 div.textContent = hint;
 box.appendChild(div);
 });
 });
 };

 ['title','meta_title','meta_description','image_alt','content','description'].forEach((name) => {
 document.querySelectorAll('[name="' + name + '"]').forEach((el) => {
 el.addEventListener('input', update);
 el.addEventListener('change', update);
 });
 });

 update();
})();

/* lightweight reveal motion for public/admin UI */
(function () {
 const body = document.body;
 if (!body || !body.classList.contains('motion-enabled')) return;
 if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

 const selector = [
 '.hero .container',
 '.mini-hero .container',
 '.section > .container',
 '.dynamic-panel',
 '.dynamic-faq-card',
 '.landing-package-card',
 '.landing-step-card',
 '.landing-location-card',
 '.product-card',
 '.article-card',
 '.inquiry-card',
 '.lp-section > .container',
 '.lp-card',
 '.lp-price-card',
 '.lp-faq-item',
 '.lp-custom-menu__item',
 '.admin-hero__inner',
 '.admin-card',
 '.admin-page-tab-panel.is-active'
 ].join(',');

 const nodes = Array.from(document.querySelectorAll(selector)).filter(function (node) {
 const lpSection = node.closest ? node.closest('.lp-section') : null;
 if (lpSection && lpSection.getAttribute('data-lp-animation') === 'none') {
 return false;
 }
 return node && !node.classList.contains('motion-reveal') && !node.closest('.lpw-preview-canvas');
 });

 if (!nodes.length) return;

 nodes.forEach(function (node, index) {
 node.classList.add('motion-reveal');
 node.style.setProperty('--motion-delay', Math.min(index % 6, 5) * 35 + 'ms');
 });

 if (!('IntersectionObserver' in window)) {
 nodes.forEach(function (node) { node.classList.add('is-visible'); });
 return;
 }

 const observer = new IntersectionObserver(function (entries) {
 entries.forEach(function (entry) {
 if (!entry.isIntersecting) return;
 entry.target.classList.add('is-visible');
 observer.unobserve(entry.target);
 });
 }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

 nodes.forEach(function (node) { observer.observe(node); });
})();

/* v33.1.9: fallback for LP custom menu sticky when parent layout/browser overflow blocks CSS sticky */
(function () {
 const menus = Array.from(document.querySelectorAll('.landing-page-builder .lp-custom-menu--header.lp-custom-menu--sticky, .landing-page-builder .lp-custom-menu--header[data-lp-menu-position="sticky"]'));
 if (!menus.length) return;
 menus.forEach(function (menu) {
  if (menu.dataset.lpStickyFallbackBound === '1') return;
  menu.dataset.lpStickyFallbackBound = '1';
  const placeholder = document.createElement('div');
  placeholder.className = 'lp-custom-menu__sticky-placeholder';
  placeholder.setAttribute('aria-hidden', 'true');
  menu.parentNode.insertBefore(placeholder, menu);
  let startY = 0;
  function measure() {
   const wasFixed = menu.classList.contains('is-lp-fixed-sticky');
   if (wasFixed) menu.classList.remove('is-lp-fixed-sticky');
   placeholder.style.height = '0px';
   startY = menu.getBoundingClientRect().top + window.pageYOffset;
   if (wasFixed) menu.classList.add('is-lp-fixed-sticky');
  }
  function update() {
   const height = menu.offsetHeight || 0;
   const shouldStick = window.pageYOffset > startY;
   menu.classList.toggle('is-lp-fixed-sticky', shouldStick);
   placeholder.style.height = shouldStick ? height + 'px' : '0px';
  }
  measure();
  update();
  window.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', function () { measure(); update(); }, { passive: true });
  window.setTimeout(function () { measure(); update(); }, 250);
 });
})();
