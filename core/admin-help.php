<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| ADMIN HELP & TOOLTIP SYSTEM
|--------------------------------------------------------------------------
| Small contextual guides for UMKM admins. The content is intentionally
| user-facing and focused on practical dashboard guidance.
|--------------------------------------------------------------------------
*/

if (!function_exists('admin_help_normalize_path')) {
    function admin_help_normalize_path(?string $path = null): string
    {
        $path = $path ?? (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '');
        $path = trim(str_replace('\\', '/', $path), '/');
        $basePath = defined('BASE_URL') ? trim((string)(parse_url((string)BASE_URL, PHP_URL_PATH) ?: ''), '/') : '';

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = trim(substr($path, strlen($basePath)), '/');
        }

        return $path === '' ? 'admin/brand' : $path;
    }
}

if (!function_exists('admin_help_item')) {
    function admin_help_item(
        string $key,
        string $title,
        string $group,
        string $summary,
        array $firstSteps,
        array $tips,
        string $primaryLabel,
        string $primaryPath,
        array $aliases = [],
        array $related = []
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'group' => $group,
            'summary' => $summary,
            'first_steps' => $firstSteps,
            'tips' => $tips,
            'primary_label' => $primaryLabel,
            'primary_path' => $primaryPath,
            'aliases' => array_values(array_unique(array_merge([$key], $aliases))),
            'related' => $related,
        ];
    }
}

if (!function_exists('admin_help_definitions')) {
    function admin_help_definitions(): array
    {
        static $items = null;

        if (is_array($items)) {
            return $items;
        }

        $items = [
            'admin/brand' => admin_help_item(
                'admin/brand',
                'Brand & Warna',
                'Pengaturan Web',
                'Tempat mengisi identitas bisnis agar website terlihat resmi, dipercaya, dan mudah dihubungi calon customer.',
                ['Isi nama bisnis, tagline, WhatsApp, email, dan alamat.', 'Upload logo yang jelas dan favicon sederhana.', 'Pilih warna yang sesuai karakter brand.'],
                ['Brand yang jelas bikin iklan, SEO, dan landing page terasa konsisten.', 'Nomor WhatsApp sebaiknya aktif dan cepat dibalas.'],
                'Atur Brand',
                'admin/brand',
                ['admin', 'admin-brand', 'admin/theme'],
                ['admin/business', 'admin/homepage', 'admin/launch-readiness']
            ),
            'admin/business' => admin_help_item(
                'admin/business',
                'Mode & Kategori Bisnis',
                'Pengaturan Web',
                'Dipakai untuk menentukan apakah website lebih fokus jual produk, jasa, company profile, portfolio, atau kombinasi semuanya.',
                ['Pilih tipe bisnis utama.', 'Rapikan label produk/jasa sesuai bahasa bisnis.', 'Isi kategori yang paling sering dicari customer.'],
                ['Kategori yang rapi membantu pengunjung menemukan penawaran lebih cepat.', 'Gunakan istilah yang biasa dipakai customer, bukan istilah internal bisnis.'],
                'Atur Mode Bisnis',
                'admin/business',
                ['admin-business', 'admin/business-mode', 'admin/categories'],
                ['admin/starter-wizard', 'admin/produk', 'admin/universal-seo']
            ),
            'admin/starter-wizard' => admin_help_item(
                'admin/starter-wizard',
                'Website Starter Wizard',
                'Pengaturan Web',
                'Panduan cepat untuk memilih arah awal website. Cocok untuk admin yang ingin mulai dari preset, tetapi tetap bisa diedit lagi setelahnya.',
                ['Pilih jalur website yang paling mendekati bisnis.', 'Terapkan starter content bila ingin lebih cepat mulai.', 'Review lagi halaman publik setelah preset diterapkan.'],
                ['Preset adalah titik awal, bukan batasan.', 'Setelah diterapkan, lanjut edit brand, homepage, katalog, dan form.'],
                'Buka Starter Wizard',
                'admin/starter-wizard',
                ['admin-starter-wizard', 'admin/website-starter', 'admin/onboarding-wizard'],
                ['admin/brand', 'admin/homepage', 'admin/onboarding-assistant']
            ),
            'admin/onboarding-assistant' => admin_help_item(
                'admin/onboarding-assistant',
                'Onboarding Setup Assistant',
                'Pengaturan Web',
                'Panduan harian agar admin tahu urutan kerja paling aman dari brand sampai website siap promosi.',
                ['Ikuti fokus hari ini.', 'Klik tombol menuju menu terkait.', 'Tandai selesai kalau langkahnya sudah dikerjakan manual.'],
                ['Tidak perlu sempurna di hari pertama.', 'Yang penting website punya identitas, penawaran, bukti percaya, dan jalur kontak.'],
                'Buka Panduan Harian',
                'admin/onboarding-assistant',
                ['admin-onboarding-assistant', 'admin/panduan-harian'],
                ['admin/launch-readiness', 'admin/brand', 'admin/homepage']
            ),
            'admin/help-center' => admin_help_item(
                'admin/help-center',
                'Pusat Bantuan Dashboard',
                'Pengaturan Web',
                'Tempat mencari panduan singkat untuk menu dashboard, terutama saat admin baru belum tahu harus mulai dari mana.',
                ['Cari menu atau topik yang ingin dipahami.', 'Baca bagian kerjakan dulu.', 'Klik tombol menuju menu terkait.'],
                ['Pusat bantuan cocok dipakai sebagai peta dashboard.', 'Mulai dari panduan yang paling dekat dengan brand, homepage, katalog, SEO, dan lead.'],
                'Buka Pusat Bantuan',
                'admin/help-center',
                ['admin-help-center', 'admin/bantuan-dashboard', 'admin/panduan-dashboard'],
                ['admin/onboarding-assistant', 'admin/launch-readiness', 'admin/brand']
            ),
            'admin/menu-features' => admin_help_item(
                'admin/menu-features',
                'Menu & Fitur Admin',
                'Sistem',
                'Tempat menyembunyikan menu dashboard yang belum dipakai agar admin harian tidak bingung, tanpa menghapus data atau source code.',
                ['Centang menu yang ingin disembunyikan.', 'Jangan sembunyikan menu inti yang masih dipakai owner.', 'Simpan lalu refresh dashboard untuk melihat sidebar yang lebih ringkas.'],
                ['Toggle ini hanya memengaruhi tampilan sidebar.', 'Route dan data tetap ada agar update dan audit tetap aman.'],
                'Atur Menu Admin',
                'admin/menu-features',
                ['admin-menu-features', 'admin/feature-toggle', 'admin/menu-visibility'],
                ['admin/users', 'admin/security', 'admin/data-health']
            ),
            'admin/profit-action-dashboard' => admin_help_item(
                'admin/profit-action-dashboard',
                'Profit Action Dashboard',
                'Marketing & Analytics',
                'Pusat aksi harian yang mengubah sinyal SEO, lead, CTA, order, payment, dan follow-up menjadi langkah yang paling dekat dengan profit.',
                ['Lihat Prioritas Hari Ini terlebih dulu.', 'Kerjakan action money leak dan follow-up sebelum optimasi lain.', 'Tandai selesai agar checklist harian tetap rapi.'],
                ['Dahulukan order/payment/follow-up karena paling dekat dengan omzet.', 'Setelah money leak aman, poles SEO to Sales dan Trust & CTA.'],
                'Buka Profit Action',
                'admin/profit-action-dashboard',
                ['admin-profit-action-dashboard', 'admin/profit-actions', 'admin/profit', 'admin/daily-profit-actions'],
                ['admin/funnel-action-center', 'admin/conversion-opportunities', 'admin/reports']
            ),
            'admin/profit-playbook' => admin_help_item(
                'admin/profit-playbook',
                'Profit Playbook & Campaign Planner',
                'Marketing & Analytics',
                'Planner campaign 7, 14, dan 30 hari yang mengubah action profit, SEO, CTA, trust, dan follow-up menjadi rencana eksekusi harian.',
                ['Pilih durasi campaign dan goal utama.', 'Kerjakan timeline dari hari pertama, lalu tandai task yang selesai.', 'Gunakan export CSV/JSON bila ingin dibagikan ke tim.'],
                ['Mulai dari 7 hari kalau bisnis masih baru merapikan offer.', 'Pilih 14 atau 30 hari kalau sudah siap membangun ritme SEO, follow-up, dan scale.'],
                'Buka Profit Playbook',
                'admin/profit-playbook',
                ['admin-profit-playbook', 'admin/campaign-planner', 'admin/profit-campaign', 'admin/campaign-playbook'],
                ['admin/profit-action-dashboard', 'admin/content-performance', 'admin/trust-conversion']
            ),
            'admin/offer-cta-testing' => admin_help_item(
                'admin/offer-cta-testing',
                'Offer & CTA Testing Lab',
                'Marketing & Analytics',
                'Tempat membandingkan beberapa offer, headline, tombol CTA, proof, dan hipotesis sebelum dipakai di homepage, artikel, landing page, form, trust block, atau campaign.',
                ['Tambahkan beberapa varian offer atau ambil ide otomatis.', 'Isi headline, CTA, target audience, proof, dan hipotesis.', 'Setelah dibandingkan, pilih satu varian sebagai winner untuk dipakai di halaman penting.'],
                ['Uji satu hal dulu: headline, tombol, atau proof, jangan semuanya sekaligus.', 'Offer yang bagus harus jelas untuk siapa, manfaatnya apa, dan langkah berikutnya apa.'],
                'Buka Offer Lab',
                'admin/offer-cta-testing',
                ['admin-offer-cta-testing', 'admin/offer-cta-lab', 'admin/cta-testing', 'admin/offer-lab'],
                ['admin/profit-playbook', 'admin/landing-page-optimization', 'admin/trust-conversion']
            ),
            'admin/cta-placement' => admin_help_item(
                'admin/cta-placement',
                'CTA Placement Assistant',
                'Marketing & Analytics',
                'Assistant untuk mengarahkan winner Offer Lab ke area yang paling strategis seperti homepage, artikel, landing page, trust block, form, follow-up, dan campaign.',
                ['Pilih kandidat winner atau varian aktif dari Offer Lab.', 'Tambahkan rencana placement sesuai area yang ingin diperkuat.', 'Pasang CTA, ubah status, lalu pantau hasil klik, lead, dan order.'],
                ['Winner jangan berhenti di lab; pasang di area dengan traffic dan intent paling kuat.', 'Mulai dari Homepage Hero atau artikel SEO terbaik bila ingin efek paling cepat terlihat.'],
                'Buka CTA Placement',
                'admin/cta-placement',
                ['admin-cta-placement', 'admin/cta-deployment', 'admin/winner-deployment', 'admin/cta-placement-assistant'],
                ['admin/offer-cta-testing', 'admin/homepage', 'admin/profit-playbook']
            ),
            'admin/cta-result-tracker' => admin_help_item(
                'admin/cta-result-tracker',
                'CTA Result Tracker',
                'Marketing & Analytics',
                'Bridge hasil CTA yang membaca Lead Tracking existing, CTA Placement, dan Offer Lab tanpa membuat sistem tracking baru.',
                ['Lihat placement yang punya klik, lead, atau order.', 'Prioritaskan CTA yang kliknya ada tapi lead masih rendah.', 'Simpan keputusan: lanjutkan, scale, perbaiki, atau ganti CTA.'],
                ['Tracking Lead tetap menjadi sumber data utama.', 'Halaman ini membantu mengambil keputusan praktis dari hasil placement CTA.'],
                'Buka CTA Result',
                'admin/cta-result-tracker',
                ['admin-cta-result-tracker', 'admin/cta-results', 'admin/result-tracker', 'admin/lead-tracking-bridge'],
                ['admin/leads', 'admin/cta-placement', 'admin/offer-cta-testing']
            ),
            'admin/seo-profit-attribution' => admin_help_item(
                'admin/seo-profit-attribution',
                'SEO Profit Attribution Bridge',
                'Marketing & Analytics',
                'Bridge untuk membaca kontribusi artikel dan halaman SEO ke klik CTA, lead, order, payment, dan campaign profit dari Tracking Lead existing.',
                ['Lihat halaman SEO yang membawa klik, lead, atau order.', 'Prioritaskan halaman dengan klik tinggi tapi lead rendah.', 'Simpan keputusan: scale konten, perbaiki CTA, tambah internal link, atau refresh konten.'],
                ['Tracking Lead tetap menjadi sumber data utama.', 'Gunakan halaman ini untuk menjawab artikel mana yang mendatangkan lead dan order berasal dari halaman mana.'],
                'Buka SEO Profit',
                'admin/seo-profit-attribution',
                ['admin-seo-profit-attribution', 'admin/seo-profit', 'admin/seo-attribution', 'admin/seo-profit-bridge'],
                ['admin/leads', 'admin/universal-seo', 'admin/cta-result-tracker']
            ),
            'admin/seo-assisted-journey' => admin_help_item(
                'admin/seo-assisted-journey',
                'SEO Assisted Conversion Journey Map',
                'Marketing & Analytics',
                'Peta perjalanan dari artikel/halaman SEO ke klik CTA, lead, order, dan payment dengan membaca Tracking Lead existing.',
                ['Baca stage journey per halaman: SEO Page, CTA Click, Lead, Order/Payment.', 'Cari bottleneck paling sering: belum ada sinyal, klik belum jadi lead, atau lead belum jadi order.', 'Simpan keputusan action: tambah CTA, perbaiki offer, tambah trust, follow-up lead, atau scale halaman.'],
                ['Ini bukan tracking baru; sumber datanya tetap Lead Tracking existing.', 'Gunakan halaman ini setelah SEO Profit Attribution untuk melihat alur yang lebih mudah dipahami admin.'],
                'Buka Journey Map',
                'admin/seo-assisted-journey',
                ['admin-seo-assisted-journey', 'admin/seo-journey-map', 'admin/conversion-journey', 'admin/assisted-conversion'],
                ['admin/seo-profit-attribution', 'admin/cta-result-tracker', 'admin/profit-playbook']
            ),
            'admin/seo-money-page-optimizer' => admin_help_item(
                'admin/seo-money-page-optimizer',
                'SEO Money Page Optimizer',
                'Marketing & Analytics',
                'Optimizer untuk mengubah halaman SEO potensial menjadi money page dengan rekomendasi CTA, internal link, trust block, offer, dan content fix.',
                ['Mulai dari prioritas high terlebih dulu.', 'Baca brief CTA, internal link, trust block, dan content fix per halaman.', 'Simpan status pengerjaan agar antrean optimasi tetap rapi.'],
                ['Halaman ini meneruskan SEO Journey Map, bukan tracking baru.', 'Gunakan untuk menjawab halaman mana yang harus dipoles agar lebih dekat ke lead dan order.'],
                'Buka Money Page Optimizer',
                'admin/seo-money-page-optimizer',
                ['admin-seo-money-page-optimizer', 'admin/seo-money-page', 'admin/money-page-optimizer', 'admin/money-page'],
                ['admin/seo-assisted-journey', 'admin/cta-placement', 'admin/trust-conversion']
            ),
            'admin/money-page-deployment-checklist' => admin_help_item(
                'admin/money-page-deployment-checklist',
                'Money Page Deployment Checklist',
                'Marketing & Analytics',
                'Checklist eksekusi untuk mengubah rekomendasi Money Page Optimizer menjadi pekerjaan nyata: edit konten, pasang CTA, tambah internal link, trust block, offer, lalu pantau hasil.',
                ['Mulai dari money page prioritas high.', 'Kerjakan task CTA, internal link, trust, offer, dan monitoring secara berurutan.', 'Tandai progres task agar pemilik bisnis tahu halaman mana yang sudah dieksekusi.'],
                ['Ini bukan tracking baru; hasil tetap dibaca dari Lead Tracking, CTA Result, dan SEO Journey Map.', 'Gunakan setelah Money Page Optimizer agar rekomendasi tidak berhenti sebagai insight.'],
                'Buka Deployment Checklist',
                'admin/money-page-deployment-checklist',
                ['admin-money-page-deployment-checklist', 'admin/deployment-checklist', 'admin/seo-deployment-checklist', 'admin/money-page-checklist'],
                ['admin/seo-money-page-optimizer', 'admin/cta-placement', 'admin/seo-assisted-journey']
            ),
            'admin/internal-link-cta-injection' => admin_help_item(
                'admin/internal-link-cta-injection',
                'Internal Link & CTA Injection Assistant',
                'Marketing & Analytics',
                'Assistant untuk menentukan internal link dan CTA mana yang perlu disisipkan ke artikel, landing page, produk, atau halaman SEO agar traffic punya jalur menuju lead dan order.',
                ['Mulai dari rekomendasi prioritas high.', 'Buka halaman sumber lalu sisipkan internal link atau CTA sesuai snippet.', 'Tandai status sebagai sudah dipasang lalu pantau efeknya di CTA Result dan SEO Journey Map.'],
                ['Fitur ini bukan tracking baru; hasil tetap dibaca dari Lead Tracking existing.', 'Gunakan setelah Money Page Deployment Checklist agar rekomendasi benar-benar masuk ke konten halaman.'],
                'Buka Injection Assistant',
                'admin/internal-link-cta-injection',
                ['admin-internal-link-cta-injection', 'admin/internal-link-cta-assistant', 'admin/cta-injection', 'admin/link-cta-injection'],
                ['admin/money-page-deployment-checklist', 'admin/seo-money-page-optimizer', 'admin/cta-result-tracker']
            ),
            'admin/seo-content-refresh-planner' => admin_help_item(
                'admin/seo-content-refresh-planner',
                'SEO Content Refresh Planner',
                'Marketing & Analytics',
                'Planner untuk menghidupkan ulang artikel dan halaman lama dengan update konten, meta, FAQ, CTA, internal link, schema, dan offer berdasarkan data existing.',
                ['Mulai dari prioritas high atau alasan konten lama masih punya sinyal.', 'Update meta, konten, FAQ, CTA, internal link, schema, dan offer sesuai checklist.', 'Setelah publish, ubah status ke monitoring lalu pantau hasilnya di Lead Tracking, SEO Journey, dan CTA Result.'],
                ['Fitur ini bukan tracking baru; planner membaca Content Performance, Lead Tracking, Money Page, CTA, dan internal link yang sudah ada.', 'Cocok dipakai untuk menghidupkan artikel lama agar tetap relevan dan lebih dekat ke lead/order.'],
                'Buka Refresh Planner',
                'admin/seo-content-refresh-planner',
                ['admin-seo-content-refresh-planner', 'admin/content-refresh-planner', 'admin/seo-refresh', 'admin/content-refresh'],
                ['admin/content-performance', 'admin/seo-money-page-optimizer', 'admin/internal-link-cta-injection']
            ),
            'admin/lead-priority-scoring' => admin_help_item(
                'admin/lead-priority-scoring',
                'Lead Priority Scoring',
                'Marketing & Analytics',
                'Scoring untuk memprioritaskan lead, order, dan peluang follow-up berdasarkan Order, Inbox Lead/Form, CRM, dan Tracking Lead existing.',
                ['Mulai dari lead Hot dan Warm terlebih dulu.', 'Hubungi lead baru, order menunggu pembayaran, dan follow-up overdue sebelum prospek dingin.', 'Simpan status dan catatan CRM agar proses follow-up tidak tercecer.'],
                ['Fitur ini bukan tracking baru; sumber datanya tetap Order, Inbox Lead, Follow-up CRM, dan Tracking Lead existing.', 'Gunakan setelah SEO/CTA mulai menghasilkan lead agar admin tahu prospek mana yang paling dekat ke closing.'],
                'Buka Lead Priority',
                'admin/lead-priority-scoring',
                ['admin-lead-priority-scoring', 'admin/lead-priority-scoring', 'admin-lead-quality-scoring', 'admin/lead-quality', 'admin/followup-scoring', 'admin/lead-opportunity-scoring'],
                ['admin/leads', 'admin/inquiries', 'admin/orders', 'admin/followups']
            ),
            'admin/profit-report-builder' => admin_help_item(
                'admin/profit-report-builder',
                'Profit Report Builder',
                'Marketing & Analytics',
                'Builder laporan owner/CEO yang merangkum revenue signal, SEO attribution, CTA result, lead quality, money leak, dan action plan dari data existing.',
                ['Pilih periode laporan 7 sampai 365 hari.', 'Copy executive summary atau export CSV/JSON/Teks untuk laporan owner.', 'Gunakan action plan untuk menentukan prioritas kerja minggu ini.'],
                ['Fitur ini bukan tracking baru; semua angka dibaca dari Report Engine, Lead Tracking, SEO Profit, CTA Result, dan Lead Priority yang sudah ada.', 'Cocok dipakai admin untuk membuat laporan ke CEO tanpa harus membuka banyak menu satu per satu.'],
                'Buka Profit Report',
                'admin/profit-report-builder',
                ['admin-profit-report-builder', 'admin/profit-report', 'admin/ceo-report', 'admin/executive-report'],
                ['admin/reports', 'admin/seo-profit-attribution', 'admin/profit-action-dashboard', 'admin/lead-priority-scoring']
            ),
            'admin/seo-campaign-calendar' => admin_help_item(
                'admin/seo-campaign-calendar',
                'SEO Campaign Calendar & Growth Sprint Planner',
                'Marketing & Analytics',
                'Kalender kerja untuk menurunkan Profit Report, SEO, CTA, money page, content refresh, dan follow-up menjadi sprint 7/14/30 hari yang siap dieksekusi.',
                ['Mulai dari durasi 14 hari agar tim tidak overload.', 'Pilih fokus Balanced Growth untuk melihat semua action, atau filter ke SEO/CTA/follow-up.', 'Update status task, PIC, deadline, dan catatan agar owner/CEO tahu progress nyata.'],
                ['Fitur ini bukan tracking baru; planner membaca Profit Report Builder, Lead Tracking, SEO Profit, CTA Result, Money Page, Content Refresh, dan Lead Priority existing.', 'Gunakan setelah membuat laporan CEO agar admin sudah punya jawaban “terus action plan-nya apa?”.'],
                'Buka Campaign Calendar',
                'admin/seo-campaign-calendar',
                ['admin-seo-campaign-calendar', 'admin/growth-sprint-planner', 'admin/growth-sprint', 'admin/campaign-calendar'],
                ['admin/profit-report-builder', 'admin/profit-playbook', 'admin/profit-action-dashboard']
            ),
            'admin/u-growth-command-center' => admin_help_item(
                'admin/u-growth-command-center',
                'U-Growth Command Center',
                'Marketing & Analytics',
                'Pusat komando growth yang merangkum Profit Action, SEO Profit, CTA Result, Money Page, Content Refresh, Lead Priority, Profit Report, dan Growth Sprint menjadi prioritas eksekusi harian.',
                ['Mulai dari Command Score dan Prioritas Hari Ini.', 'Buka bottleneck map untuk melihat titik bocor: SEO ke CTA, CTA ke lead, lead ke closing, atau rekomendasi ke eksekusi.', 'Copy Owner Brief saat perlu memberi laporan singkat plus action plan ke owner/CEO.'],
                ['Fitur ini bukan tracking baru; semua angka dirangkum dari modul existing dan Lead Tracking yang sudah ada.', 'Cocok dipakai sebagai halaman pertama untuk admin marketing sebelum membuka modul detail.'],
                'Buka Command Center',
                'admin/u-growth-command-center',
                ['admin-u-growth-command-center', 'admin/growth-command-center', 'admin/command-center', 'admin/growth-command'],
                ['admin/profit-action-dashboard', 'admin/profit-report-builder', 'admin/seo-campaign-calendar', 'admin/lead-priority-scoring']
            ),
            'admin/release-audit' => admin_help_item(
                'admin/release-audit',
                'Audit Kesiapan Website',
                'Sistem',
                'Audit keamanan, route, alur data growth, copy publik, dan checklist sebelum source dipakai di production.',
                ['Lihat Skor Kesiapan dan temuan penting terlebih dulu.', 'Cek sambungan modul agar route, page, helper, menu, dan Pusat Bantuan tersambung.', 'Lengkapi manual checklist sebelum upload ke production.'],
                ['Audit ini tidak membuat tracking baru; ia memastikan modul growth tetap membaca Lead Tracking existing.', 'Gunakan bersama Cek Sistem, Kesiapan Website, Backup & Restore, dan Log Sistem sebelum rilis.'],
                'Buka Audit Kesiapan Website',
                'admin/release-audit',
                ['admin-release-audit', 'admin/final-release-audit', 'admin/final-hardening', 'admin/release-checklist'],
                ['admin/data-health', 'admin/production-readiness', 'admin/maintenance', 'admin/activity-log']
            ),
            'admin/launch-readiness' => admin_help_item(
                'admin/launch-readiness',
                'Launch Readiness',
                'Pengaturan Web',
                'Checklist kesiapan sebelum website dipakai promosi, mulai dari brand, katalog, form, trust block, SEO, hingga pengaturan teknis.',
                ['Lihat item yang masih belum hijau.', 'Kerjakan item dengan skor terbesar dulu.', 'Cek ulang setelah update data.'],
                ['Skor tinggi bukan sekadar rapi, tapi membantu website lebih siap menerima traffic.', 'Gunakan halaman ini sebelum menjalankan iklan atau campaign SEO besar.'],
                'Cek Kesiapan Website',
                'admin/launch-readiness',
                ['admin-launch-readiness', 'admin/guided-setup'],
                ['admin/onboarding-assistant', 'admin/trust-conversion', 'admin/universal-seo']
            ),
            'admin/homepage' => admin_help_item(
                'admin/homepage',
                'Atur Beranda',
                'Pengaturan Web',
                'Tempat mengatur isi dan urutan section homepage agar pengunjung langsung paham bisnis dan terdorong klik CTA.',
                ['Pastikan hero punya headline jelas.', 'Aktifkan section yang paling penting untuk bisnis.', 'Urutkan section dari awareness ke aksi.'],
                ['Homepage yang bagus menjawab: bisnis ini apa, untuk siapa, kenapa dipercaya, dan bagaimana cara order.', 'Jangan terlalu banyak section bila belum ada konten yang kuat.'],
                'Atur Beranda',
                'admin/homepage',
                ['admin-homepage', 'admin/beranda'],
                ['admin/trust-conversion', 'admin/forms', 'admin/produk']
            ),
            'admin/trust-conversion' => admin_help_item(
                'admin/trust-conversion',
                'Trust & Conversion Block',
                'Pengaturan Web',
                'Builder untuk menambah testimoni, FAQ, benefit, garansi, badge trust, before-after, dan CTA agar pengunjung lebih yakin.',
                ['Aktifkan minimal benefit, testimoni, FAQ, dan CTA.', 'Isi poin dengan bahasa yang singkat dan spesifik.', 'Letakkan block di posisi homepage yang paling dekat dengan keputusan beli.'],
                ['Trust block mengurangi ragu sebelum pengunjung chat atau checkout.', 'FAQ bagus untuk menjawab keberatan yang sering muncul.'],
                'Atur Trust Block',
                'admin/trust-conversion',
                ['admin-trust-conversion', 'admin/conversion-blocks'],
                ['admin/homepage', 'admin/conversion-opportunities', 'admin/launch-readiness']
            ),
            'admin/produk' => admin_help_item(
                'admin/produk',
                'Katalog Produk/Jasa',
                'Konten & SEO',
                'Tempat mengelola produk, jasa, paket, harga, gambar, deskripsi, dan CTA menuju WhatsApp atau checkout.',
                ['Tambahkan produk/jasa utama terlebih dulu.', 'Isi judul, harga, kategori, gambar, dan deskripsi singkat.', 'Gunakan slug yang jelas dan mudah dibaca.'],
                ['Judul produk yang spesifik lebih mudah ditemukan dari Google.', 'Foto yang rapi meningkatkan rasa percaya.'],
                'Kelola Katalog',
                'admin/produk',
                ['admin-produk'],
                ['admin/universal-seo', 'admin/media-library', 'admin/form-checkout']
            ),
            'admin/artikel' => admin_help_item(
                'admin/artikel',
                'Artikel',
                'Konten & SEO',
                'Tempat membuat konten edukasi untuk membantu ranking SEO dan menjawab pertanyaan calon customer.',
                ['Tulis artikel dari pertanyaan yang sering ditanyakan customer.', 'Masukkan keyword utama secara natural.', 'Hubungkan artikel ke produk, jasa, atau form yang relevan.'],
                ['Artikel edukasi membantu calon customer percaya sebelum beli.', 'Konten SEO yang konsisten bisa menjadi sumber traffic jangka panjang.'],
                'Kelola Artikel',
                'admin/artikel',
                ['admin-artikel'],
                ['admin/seo-content-planner', 'admin/seo-publish-checklist', 'admin/content-performance']
            ),
            'admin/migration-command-center' => admin_help_item(
                'admin/migration-command-center',
                'Migration Command Center',
                'Konten & SEO',
                'Pusat komando migrasi WordPress ke U-Growth untuk memantau import, redirect, media, cleaner, Elementor, internal link, dynamic content, dan checklist GSC dari satu tempat.',
                ['Cek health score dan checklist migrasi.', 'Buka modul yang masih perlu review.', 'Export JSON/CSV untuk dokumentasi migrasi.'],
                ['Command Center tidak menjalankan perubahan otomatis.', 'Semua aksi tetap dilakukan di modul masing-masing dengan preview, dry-run, backup, dan review.'],
                'Buka Command Center',
                'admin/migration-command-center',
                ['admin-migration-command-center', 'admin/migration-center', 'admin/wp-command-center', 'admin/migrasi-command-center'],
                ['admin/wp-migration', 'admin/seo-preservation', 'admin/internal-link-migration']
            ),
            'admin/wp-migration' => admin_help_item(
                'admin/wp-migration',
                'Migrasi WordPress',
                'Konten & SEO',
                'Tempat mengimpor artikel dan halaman dari WordPress lama ke U-Growth dengan preview, backup, log batch, dan rollback agar konten ranking tidak hilang saat pindah platform.',
                ['Upload file XML/WXR dari Tools → Export WordPress atau CSV mapping sederhana.', 'Cek preview jumlah artikel, halaman, konflik slug, shortcode, dan warning.', 'Jalankan import aman setelah backup storage aktif.'],
                ['Untuk artikel yang sudah ranking, simpan old URL dan canonical sebelum mengubah struktur URL.', 'V32.92 fokus import aman dulu; redirect 301 dan legacy URL resolver masuk fase SEO Preservation berikutnya.'],
                'Buka Migrasi WordPress',
                'admin/wp-migration',
                ['admin-wp-migration', 'admin/wordpress-migration', 'admin/migrasi-wordpress'],
                ['admin/artikel', 'admin/landing-pages', 'admin/universal-seo']
            ),
            'admin/wp-media-migration' => admin_help_item(
                'admin/wp-media-migration',
                'WordPress Media Migration',
                'Konten & SEO',
                'Tempat memindahkan gambar WordPress lama dari wp-content/uploads ke asset U-Growth dengan scan, download batch, media map, backup, dan rewrite URL aman.',
                ['Scan dulu URL gambar remote sebelum download.', 'Download gambar secara batch agar aman untuk shared hosting.', 'Rewrite URL hanya setelah file lokal berhasil dibuat dan backup storage aktif.'],
                ['Gambar artikel ranking jangan hilang saat domain WordPress lama dimatikan.', 'Media map membantu menjaga asal URL lama dan target asset baru agar audit migrasi lebih jelas.'],
                'Buka WordPress Media Migration',
                'admin/wp-media-migration',
                ['admin-wp-media-migration', 'admin/wordpress-media-migration', 'admin/migrasi-media-wordpress'],
                ['admin/wp-migration', 'admin/media-library', 'admin/internal-link-migration']
            ),
            'admin/wp-content-cleaner' => admin_help_item(
                'admin/wp-content-cleaner',
                'Shortcode & Gutenberg Cleaner',
                'Konten & SEO',
                'Preview dan bersihkan sisa shortcode plugin, Gutenberg block comment, builder markup, embed lama, dan tag berisiko dari konten WordPress yang dimigrasikan.',
                ['Jalankan scan setelah import WordPress dan sebelum publish ulang halaman penting.', 'Gunakan dry-run sebelum apply agar tahu field mana yang berubah.', 'Pertahankan unknown shortcode untuk review manual jika belum yakin shortcode tersebut aman dihapus.'],
                ['Cleaner membantu konten lama tampil rapi tanpa membawa plugin WordPress lama.', 'Apply membuat backup storage otomatis sehingga lebih aman untuk shared hosting.'],
                'Buka Shortcode Cleaner',
                'admin/wp-content-cleaner',
                ['admin-wp-content-cleaner', 'admin/shortcode-cleaner', 'admin/gutenberg-cleaner', 'admin/wordpress-content-cleaner'],
                ['admin/wp-migration', 'admin/wp-media-migration', 'admin/internal-link-migration']
            ),
            'admin/wp-elementor-import' => admin_help_item(
                'admin/wp-elementor-import',
                'Elementor Safe HTML Block Import',
                'Konten & SEO',
                'Deteksi halaman WordPress dari Elementor/page builder lalu import sebagai Landing Page draft dengan HTML block tersanitasi dan warning widget kompleks.',
                ['Scan job migrasi WordPress yang berisi page/landing page.', 'Gunakan mode HTML block aman untuk menjaga konten SEO tanpa membawa script/plugin lama.', 'Review widget kompleks seperti slider, popup, form, dan countdown sebelum publish.'],
                ['Tidak menjanjikan desain Elementor 100% sama karena CSS/JS plugin lama tidak dibawa.', 'Form WordPress/plugin sebaiknya diganti dengan Form Builder U-Growth agar tracking dan lead flow tetap aman.'],
                'Buka Elementor Safe Import',
                'admin/wp-elementor-import',
                ['admin-wp-elementor-import', 'admin/elementor-import', 'admin/page-builder-import', 'admin/elementor-safe-import'],
                ['admin/wp-migration', 'admin/wp-content-cleaner', 'admin/landing-pages']
            ),
            'admin/dynamic-content-guard' => admin_help_item(
                'admin/dynamic-content-guard',
                'Dynamic Content Guard',
                'Konten & SEO',
                'Audit relevansi dynamic content agar rekomendasi artikel, produk, layanan, dan landing page muncul sesuai niche, kategori, tag, keyword, slug, dan konteks halaman.',
                ['Cek Guard Score setelah import data lama atau migrasi konten.', 'Perhatikan item weak agar artikel/produk diberi kategori, tag, atau keyword yang lebih jelas.', 'Gunakan laporan ini sebelum mengaktifkan dynamic content di detail page yang ramai traffic.'],
                ['Dynamic content yang relevan membantu mengurangi thin content tanpa membuat rekomendasi terasa random.', 'Homepage boleh lebih luas; detail artikel/produk/LP harus lebih ketat relevansinya.'],
                'Buka Dynamic Content Guard',
                'admin/dynamic-content-guard',
                ['admin-dynamic-content-guard', 'admin/dynamic-content'],
                ['admin/artikel', 'admin/produk', 'admin/wp-migration', 'admin/content-performance']
            ),
            'admin/universal-seo' => admin_help_item(
                'admin/universal-seo',
                'Universal SEO Engine',
                'Konten & SEO',
                'Pusat pengaturan SEO dasar untuk halaman penting, schema, meta title, meta description, dan sinyal teknis.',
                ['Cek halaman prioritas: homepage, katalog, produk/jasa, artikel, dan landing page.', 'Pastikan title dan description tidak kosong.', 'Gunakan keyword sesuai niat pencarian customer.'],
                ['SEO bukan cuma keyword, tapi juga struktur halaman, kecepatan, konten, dan internal link.', 'Prioritaskan halaman yang paling dekat dengan penjualan.'],
                'Buka SEO Engine',
                'admin/universal-seo',
                ['admin-universal-seo', 'admin/seo-engine', 'admin/seo-audit'],
                ['admin/seo-growth-planner', 'admin/seo-quality', 'admin/seo-link-health']
            ),
            'admin/seo-growth-planner' => admin_help_item(
                'admin/seo-growth-planner',
                'SEO Growth Planner',
                'Konten & SEO',
                'Tempat menyusun rencana SEO agar konten dan landing page punya arah growth, bukan sekadar publish random.',
                ['Tentukan keyword utama dan turunannya.', 'Kelompokkan keyword berdasarkan produk, area, dan kebutuhan.', 'Jadikan rencana ini bahan kerja artikel dan landing page.'],
                ['Pilih keyword yang dekat dengan kebutuhan beli.', 'SEO yang rapi biasanya menang dari konsistensi, bukan dari sekali publish.'],
                'Buka SEO Planner',
                'admin/seo-growth-planner',
                ['admin-seo-growth-planner', 'admin/seo-planner', 'admin/internal-link-planner'],
                ['admin/seo-content-planner', 'admin/seo-execution-board', 'admin/seo-landings']
            ),
            'admin/forms' => admin_help_item(
                'admin/forms',
                'Form Custom',
                'Form Builder',
                'Tempat membuat form prospek, konsultasi, lead magnet, survey, atau kebutuhan data lain dari pengunjung.',
                ['Buat form dengan field seperlunya.', 'Bedakan pesan untuk admin dan pesan untuk lead.', 'Pasang form di halaman yang relevan.'],
                ['Form pendek biasanya lebih mudah diisi.', 'Pesan otomatis yang jelas membuat lead merasa cepat direspons.'],
                'Kelola Form',
                'admin/forms',
                ['admin-forms', 'admin/custom-forms'],
                ['admin/inquiries', 'admin/leads', 'admin/homepage']
            ),
            'admin/form-checkout' => admin_help_item(
                'admin/form-checkout',
                'Form Checkout',
                'Form Builder',
                'Pengaturan field checkout untuk bisnis yang ingin menerima order langsung dari website.',
                ['Aktifkan field wajib seperti nama, WhatsApp, alamat, dan catatan order.', 'Pastikan alur setelah submit jelas.', 'Hubungkan dengan pembayaran manual atau gateway bila dipakai.'],
                ['Checkout yang simpel mengurangi calon pembeli batal order.', 'Field terlalu banyak bisa menurunkan konversi.'],
                'Atur Checkout',
                'admin/form-checkout',
                ['admin-form-checkout', 'admin/checkout-form'],
                ['admin/payment-settings', 'admin/orders', 'admin/payment-gateway']
            ),
            'admin/shipping' => admin_help_item(
                'admin/shipping',
                'Shipping & Ongkir',
                'Order & Penjualan',
                'Tempat mengatur ongkir manual, provider API, mode hybrid, cache, free ongkir, handling fee, dan estimasi biaya kirim di checkout.',
                ['Pilih mode Manual, API, atau Hybrid.', 'Isi provider/API key dan mapping kode tujuan bila memakai API.', 'Tetapkan cache agar kuota API tidak boros.', 'Tes simulator sebelum dipakai live.'],
                ['Ongkir yang jelas mengurangi bolak-balik chat.', 'Mode Hybrid menjaga checkout tetap jalan saat API limit/down.', 'Untuk biaya final, admin tetap bisa validasi sebelum invoice.'],
                'Atur Ongkir',
                'admin/shipping',
                ['admin-shipping', 'admin/ongkir', 'admin/shipping-rates'],
                ['admin/form-checkout', 'admin/orders', 'admin/payment-settings']
            ),
            'admin/inventory' => admin_help_item(
                'admin/inventory',
                'Stock & Inventory',
                'Order & Penjualan',
                'Pantau stok, reserved order, low stock alert, pre-order, dan ketersediaan produk agar checkout tidak over-selling.',
                ['Aktifkan tracking stok di produk yang punya stok/kuota terbatas.', 'Cek produk low stock dan habis setiap hari.', 'Gunakan pre-order/backorder hanya jika proses bisnisnya siap.'],
                ['Stok yang rapi mencegah admin menerima order melebihi persediaan.', 'Reserved stock membantu melihat calon pembeli yang belum bayar tapi sudah menahan kuota.'],
                'Pantau Stok',
                'admin/inventory',
                ['admin-inventory', 'admin/stok', 'admin/product-availability'],
                ['admin/produk', 'admin/orders', 'admin/checkout-recovery']
            ),
            'admin/orders' => admin_help_item(
                'admin/orders',
                'Order',
                'Order & Penjualan',
                'Tempat memantau pesanan yang masuk, status pembayaran, dan detail customer.',
                ['Cek order baru setiap hari.', 'Update status order sesuai proses bisnis.', 'Gunakan data order untuk follow-up customer.'],
                ['Respons cepat sering jadi pembeda besar untuk closing.', 'Rapikan status agar laporan penjualan lebih akurat.'],
                'Lihat Order',
                'admin/orders',
                ['admin-orders'],
                ['admin/followups', 'admin/reports', 'admin/payment-proofs']
            ),
            'admin/followups' => admin_help_item(
                'admin/followups',
                'Follow-up & CRM',
                'Lead & Customer',
                'Tempat membaca peluang follow-up dari lead, inquiry, dan order agar calon customer tidak hilang begitu saja.',
                ['Prioritaskan lead baru dan order yang belum selesai.', 'Gunakan template follow-up yang sopan dan jelas.', 'Catat status agar tidak double follow-up.'],
                ['Banyak profit datang dari follow-up yang rapi.', 'Follow-up terbaik terasa membantu, bukan memaksa.'],
                'Buka Follow-up',
                'admin/followups',
                ['admin-followups'],
                ['admin/leads', 'admin/inquiries', 'admin/orders']
            ),
            'admin/marketing-integrations' => admin_help_item(
                'admin/marketing-integrations',
                'WhatsApp & Email Marketing',
                'Marketing & Analytics',
                'Tempat mengatur jalur kontak dan integrasi marketing agar prospek bisa masuk ke WhatsApp atau email dengan lebih rapi.',
                ['Isi nomor WhatsApp dan pengaturan email yang aktif.', 'Pastikan pesan otomatis sesuai bahasa brand.', 'Tes submit form setelah pengaturan diubah.'],
                ['Marketing automation yang rapi bikin admin tidak gampang miss lead.', 'Tetap jaga bahasa pesan agar terasa manusiawi.'],
                'Atur Marketing',
                'admin/marketing-integrations',
                ['admin-marketing-integrations', 'admin/marketing-integrations', 'admin/wa-email-marketing', 'admin/email-marketing', 'admin/marketing-fonnte', 'admin/mailketing-fonnte'],
                ['admin/forms', 'admin/smtp', 'admin/notifications']
            ),
            'admin/conversion-opportunities' => admin_help_item(
                'admin/conversion-opportunities',
                'Conversion Opportunities',
                'Marketing & Analytics',
                'Tempat membaca peluang perbaikan agar traffic yang sudah datang lebih mungkin jadi lead atau order.',
                ['Cek halaman dengan peluang konversi besar.', 'Tambahkan CTA, trust block, atau form di halaman tersebut.', 'Pantau perubahan setelah update.'],
                ['Traffic tanpa CTA yang jelas sering bocor.', 'Perbaikan kecil di halaman ramai bisa berdampak besar.'],
                'Lihat Peluang Konversi',
                'admin/conversion-opportunities',
                ['admin-conversion-opportunities', 'admin/conversion-opportunity', 'admin/conversion-roi'],
                ['admin/trust-conversion', 'admin/homepage', 'admin/forms']
            ),
            'admin/growth-snapshot' => admin_help_item(
                'admin/growth-snapshot',
                'Growth Snapshot',
                'Marketing & Analytics',
                'Ringkasan kondisi website, konten, lead, dan peluang growth agar owner bisa mengambil keputusan lebih cepat.',
                ['Lihat indikator yang paling merah dulu.', 'Hubungkan insight dengan action harian.', 'Gunakan sebagai review mingguan.'],
                ['Dashboard growth membantu fokus ke tindakan yang paling berdampak.', 'Jangan cuma lihat angka; lanjutkan ke action center.'],
                'Buka Growth Snapshot',
                'admin/growth-snapshot',
                ['admin-growth-snapshot', 'admin/growth-snapshot-report', 'admin/umkm-growth-report'],
                ['admin/funnel-action-center', 'admin/content-performance', 'admin/reports']
            ),
            'admin/analytics' => admin_help_item(
                'admin/analytics',
                'Analytics & Iklan',
                'Marketing & Analytics',
                'Tempat mengatur tracking dasar seperti pixel, analytics, dan event agar performa marketing bisa dibaca.',
                ['Isi ID tracking yang dipakai.', 'Tes event penting setelah disimpan.', 'Pastikan halaman privacy policy tersedia bila memakai tracking.'],
                ['Tracking membantu tahu halaman mana yang menghasilkan lead.', 'Jangan isi ID asal kalau belum punya akun platformnya.'],
                'Atur Analytics',
                'admin/analytics',
                ['admin-analytics', 'admin/analytics-settings'],
                ['admin/leads', 'admin/landing-page-analytics', 'admin/content-performance']
            ),
            'admin/security' => admin_help_item(
                'admin/security',
                'Keamanan',
                'Sistem',
                'Tempat mengecek pengaturan keamanan dasar dashboard seperti password admin, sesi login, dan proteksi akses.',
                ['Gunakan password admin yang kuat.', 'Cek sesi login dan logout setelah selesai kerja.', 'Jangan bagikan akses admin sembarangan.'],
                ['Keamanan adalah pondasi sebelum website dipakai serius.', 'Backup dan update file tetap perlu dilakukan secara berkala.'],
                'Cek Keamanan',
                'admin/security',
                ['admin-security'],
                ['admin/maintenance', 'admin/activity-log', 'admin/production-readiness']
            ),
            'admin/smtp' => admin_help_item(
                'admin/smtp',
                'SMTP / Email Server',
                'Sistem',
                'Tempat mengatur server email agar notifikasi form, order, dan reminder bisa terkirim lebih stabil.',
                ['Isi host, port, username, dan metode keamanan sesuai provider email.', 'Kirim email test setelah simpan.', 'Pastikan email admin aktif menerima notifikasi.'],
                ['SMTP yang benar membuat lead dan order tidak kelewat.', 'Gunakan email domain bisnis bila memungkinkan.'],
                'Atur SMTP',
                'admin/smtp',
                ['admin-smtp', 'admin/email-server'],
                ['admin/notifications', 'admin/forms', 'admin/payment-reminders']
            ),
            'admin/storage-database' => admin_help_item(
                'admin/storage-database',
                'Storage & Database',
                'Sistem',
                'Tempat mengecek dan menyiapkan transisi penyimpanan file/JSON ke MySQL secara bertahap tanpa mematikan fallback file-based.',
                ['Default aman tetap File / JSON.', 'Isi koneksi MySQL di .env sebelum memilih mode Hybrid/MySQL.', 'Aktifkan collection satu per satu setelah tabel dan data migrasi diuji.'],
                ['Jangan langsung full MySQL sebelum backup dan tes CRUD.', 'Gunakan halaman ini bersama Backup & Restore dan Cek Sistem.'],
                'Cek Storage',
                'admin/storage-database',
                ['admin-storage-database', 'admin/storage', 'admin/database', 'admin/mysql-readiness'],
                ['admin/maintenance', 'admin/data-health', 'admin/release-audit']
            ),
            'admin/cloud-backup-sync' => admin_help_item(
                'admin/cloud-backup-sync',
                'Backup & Sync Data',
                'Sistem',
                'Tempat menyiapkan export data lead, order, analytics, dan member ke Google Sheets, Google Drive, atau dashboard Looker Studio.',
                ['Mulai dari export lokal CSV/JSON dulu.', 'Salin kode Apps Script dari halaman ini agar Google Sheet siap otomatis.', 'Isi Apps Script URL jika ingin sync ke Google Sheets/Drive.', 'Gunakan nama sheet yang konsisten agar Looker Studio tidak perlu sering diubah.'],
                ['Data penting tetap berada di website; cloud sync hanya salinan untuk backup dan visualisasi.', 'Jangan kirim token, password, atau file bukti bayar mentah ke spreadsheet publik.'],
                'Buka Backup & Sync Data',
                'admin/cloud-backup-sync',
                ['admin-cloud-backup-sync', 'admin/data-backup-sync', 'admin/google-sheets-backup', 'admin/google-drive-backup', 'admin/looker-studio', 'admin/looker-studio-setup', 'admin/looker-setup-wizard', 'admin/dashboard-visual-setup', 'admin/dashboard-template-pack', 'admin/looker-dashboard-template', 'admin/business-dashboard-template', 'admin/data-export-center'],
                ['admin/storage-database', 'admin/data-migration', 'admin/marketing-analytics']
            ),
            'admin/maintenance' => admin_help_item(
                'admin/maintenance',
                'Backup & Restore',
                'Sistem',
                'Tempat membuat cadangan data dan melakukan perawatan sederhana agar website lebih aman saat ada perubahan besar.',
                ['Backup sebelum update file atau edit besar.', 'Simpan file backup di tempat aman.', 'Jangan restore tanpa tahu file mana yang dipakai.'],
                ['Backup itu penyelamat saat terjadi salah edit.', 'Lakukan backup rutin saat website sudah aktif menerima order.'],
                'Buka Backup',
                'admin/maintenance',
                ['admin-maintenance', 'admin/backup-restore', 'admin/tools-maintenance'],
                ['admin/security', 'admin/activity-log', 'admin/production-readiness']
            ),
        ];

        $genericPages = [
            'admin/navigation' => ['Menu & Footer', 'Pengaturan Web', 'Atur struktur menu, footer, link penting, dan CTA navigasi agar pengunjung mudah bergerak ke halaman yang menghasilkan lead.'],
            'admin/template-content' => ['Konten Template', 'Pengaturan Web', 'Edit teks bawaan template agar semua copy publik terasa sesuai bisnis dan tidak terlihat generik.'],
            'admin/media-library' => ['Media & Asset SEO', 'Konten & SEO', 'Kelola gambar dan asset agar ringan, rapi, dan punya nama yang membantu SEO.'],
            'admin/seo-content-planner' => ['SEO Content Planner', 'Konten & SEO', 'Susun ide artikel berdasarkan kebutuhan customer dan prioritas keyword.'],
            'admin/seo-execution-board' => ['SEO Execution Board', 'Konten & SEO', 'Kelola progress pekerjaan SEO dari rencana sampai publish.'],
            'admin/seo-publish-checklist' => ['SEO Publish Checklist', 'Konten & SEO', 'Cek kelengkapan artikel sebelum dipublish agar lebih siap ranking.'],
            'admin/seo-draft-publisher' => ['SEO Draft Publisher', 'Konten & SEO', 'Kelola draft artikel SEO sebelum masuk publik.'],
            'admin/seo-link-health' => ['Internal Link Manager', 'Konten & SEO', 'Perbaiki hubungan antar halaman agar pembaca dan Google lebih mudah memahami struktur website.'],
            'admin/content-performance' => ['Content Performance', 'Konten & SEO', 'Baca performa konten untuk tahu artikel atau halaman mana yang perlu diperbaiki.'],
            'admin/migration-command-center' => ['Migration Command Center', 'Konten & SEO', 'Pusat komando migrasi WordPress: import, redirect, media, cleaner, Elementor, internal link, dynamic content, dan checklist GSC.'],
            'admin/wp-content-cleaner' => ['Shortcode & Gutenberg Cleaner', 'Konten & SEO', 'Preview dan bersihkan shortcode, Gutenberg comment, dan sisa plugin WordPress setelah migrasi.'],
            'admin/wp-elementor-import' => ['Elementor Safe HTML Block Import', 'Konten & SEO', 'Import halaman Elementor/page builder sebagai Landing Page draft dengan HTML block aman dan warning widget kompleks.'],
            'admin/dynamic-content-guard' => ['Dynamic Content Guard', 'Konten & SEO', 'Audit relevansi dynamic content agar rekomendasi artikel, produk, layanan, dan landing page tidak random.'],
            'admin/seo-quality' => ['Cek SEO', 'Konten & SEO', 'Cek kualitas dasar SEO halaman agar title, description, dan struktur lebih siap.'],
            'admin/seo-landings' => ['SEO Landing Pages', 'Konten & SEO', 'Buat halaman landing berbasis keyword, area, atau kategori untuk menangkap traffic yang lebih spesifik.'],
            'admin/payment-settings' => ['Pembayaran Manual', 'Pembayaran', 'Atur rekening, instruksi transfer, dan informasi pembayaran manual.'],
            'admin/payment-gateway' => ['Payment Gateway', 'Pembayaran', 'Atur koneksi pembayaran otomatis bila bisnis sudah siap memakai gateway.'],
            'admin/payment-proofs' => ['Bukti Pembayaran', 'Pembayaran', 'Cek bukti transfer yang dikirim customer.'],
            'admin/payment-reminders' => ['Reminder Pembayaran', 'Pembayaran', 'Kelola pengingat pembayaran agar order pending tidak dibiarkan.'],
            'admin/transaction-audit' => ['Audit Transaksi', 'Pembayaran', 'Lihat jejak transaksi untuk membantu pengecekan dan troubleshooting.'],
            'admin/leads' => ['Tracking Lead', 'Lead & Customer', 'Baca sumber lead untuk tahu halaman mana yang mulai menghasilkan prospek.'],
            'admin/inquiries' => ['Inbox Lead / Form', 'Lead & Customer', 'Kelola pesan dan data prospek yang masuk dari form website.'],
            'admin/notifications' => ['Riwayat Email', 'SMTP / Email Server', 'Cek riwayat email/notifikasi yang dikirim sistem.'],
            'admin/landing-pages' => ['Landing Pages', 'Landing Page Builder', 'Buat halaman promosi khusus untuk campaign, produk, jasa, atau penawaran tertentu.'],
            'admin/landing-page-analytics' => ['Analisis Landing Page', 'Landing Page Builder', 'Pantau performa landing page agar bisa tahu mana yang perlu dioptimasi.'],
            'admin/landing-page-optimization' => ['Optimasi Landing Page', 'Landing Page Builder', 'Perbaiki copy, CTA, form, dan struktur landing page agar lebih siap closing.'],
            'admin/profit-action-dashboard' => ['Profit Action Dashboard', 'Marketing & Analytics', 'Baca prioritas aksi harian yang paling dekat dengan profit: payment, order, follow-up, CTA, SEO, trust, dan scale.'],
            'admin/profit-playbook' => ['Profit Playbook', 'Marketing & Analytics', 'Susun campaign 7/14/30 hari dari action profit, SEO, CTA, trust, follow-up, dan offer.'],
            'admin/offer-cta-testing' => ['Offer & CTA Testing Lab', 'Marketing & Analytics', 'Bandingkan varian offer, headline, tombol, proof, dan hipotesis agar CTA yang dipasang lebih siap menghasilkan lead/order.'],
            'admin/cta-placement' => ['CTA Placement Assistant', 'Marketing & Analytics', 'Arahkan winner Offer Lab ke homepage, artikel, landing page, trust block, form, follow-up, atau campaign agar CTA benar-benar dipasang.'],
            'admin/cta-result-tracker' => ['CTA Result Tracker', 'Marketing & Analytics', 'Baca hasil CTA dari Tracking Lead existing agar admin tahu mana yang perlu dilanjutkan, diperbaiki, atau di-scale.'],
            'admin/seo-profit-attribution' => ['SEO Profit Attribution Bridge', 'Marketing & Analytics', 'Baca kontribusi artikel/halaman SEO ke klik, lead, order, payment, dan action profit dari Tracking Lead existing.'],
            'admin/seo-assisted-journey' => ['SEO Assisted Conversion Journey Map', 'Marketing & Analytics', 'Peta alur dari halaman SEO ke klik CTA, lead, order, dan payment tanpa membuat tracking baru.'],
            'admin/seo-money-page-optimizer' => ['SEO Money Page Optimizer', 'Marketing & Analytics', 'Ubah artikel/halaman SEO potensial menjadi money page dengan CTA, internal link, trust block, offer, dan content fix.'],
            'admin/money-page-deployment-checklist' => ['Money Page Deployment Checklist', 'Marketing & Analytics', 'Checklist eksekusi untuk memasang rekomendasi Money Page Optimizer: konten, CTA, internal link, trust block, offer, dan monitoring hasil.'],
            'admin/internal-link-cta-injection' => ['Internal Link & CTA Injection Assistant', 'Marketing & Analytics', 'Tentukan internal link dan CTA mana yang harus disisipkan ke halaman SEO agar pembaca punya jalur menuju offer, form, katalog, lead, dan order.'],
            'admin/seo-content-refresh-planner' => ['SEO Content Refresh Planner', 'Marketing & Analytics', 'Hidupkan lagi artikel/halaman lama dengan update meta, konten, FAQ, CTA, internal link, schema, dan offer berdasarkan data existing.'],
            'admin/lead-priority-scoring' => ['Lead Priority Scoring', 'Marketing & Analytics', 'Prioritaskan lead/order yang paling dekat ke closing dengan membaca Order, Inbox Lead, Follow-up CRM, dan Tracking Lead existing.'],
            'admin/profit-report-builder' => ['Profit Report Builder', 'Marketing & Analytics', 'Buat laporan CEO/owner dari revenue signal, SEO attribution, CTA result, lead quality, money leak, dan action plan tanpa membuat tracking baru.'],
            'admin/seo-campaign-calendar' => ['SEO Campaign Calendar & Growth Sprint Planner', 'Marketing & Analytics', 'Turunkan laporan CEO menjadi kalender sprint SEO, CTA, money page, content refresh, dan follow-up tanpa membuat tracking baru.'],
            'admin/u-growth-command-center' => ['U-Growth Command Center', 'Marketing & Analytics', 'Pusat komando growth yang merangkum profit, SEO, CTA, lead, campaign, report, dan action plan dari data existing tanpa membuat tracking baru.'],
            'admin/growth-insights' => ['Growth Insight', 'Marketing & Analytics', 'Baca insight pertumbuhan website dan rekomendasi perbaikan yang bisa dikerjakan.'],
            'admin/sales-funnel-growth' => ['Sales Funnel Growth', 'Marketing & Analytics', 'Lihat alur dari pengunjung menjadi lead/order agar bottleneck lebih mudah ditemukan.'],
            'admin/funnel-action-center' => ['Funnel Action Center', 'Marketing & Analytics', 'Pusat action untuk memperbaiki funnel berdasarkan prioritas dampak.'],
            'admin/reports' => ['Laporan & Insight Penjualan', 'Order & Penjualan', 'Baca ringkasan penjualan, lead, order, payment, shipping, digital access, subscription, dan action plan dari satu menu terpadu.'],
            'admin/commerce-insight' => ['Laporan & Insight Penjualan', 'Order & Penjualan', 'Baca ringkasan penjualan, lead, order, payment, shipping, digital access, subscription, dan action plan dari satu menu terpadu.'],
            'admin/activity-log' => ['Log Sistem', 'Sistem', 'Lihat riwayat aktivitas penting di dashboard.'],
            'admin/data-health' => ['Cek Sistem', 'Sistem', 'Cek kondisi data dan file penting website.'],
            'admin/production-readiness' => ['Kesiapan Website', 'Sistem', 'Cek kesiapan teknis sebelum website dipakai lebih serius.'],
            'admin/release-audit' => ['Audit Kesiapan Website', 'Sistem', 'Audit keamanan, route, alur data, copy publik, dan checklist manual sebelum upload production.'],
        ];

        foreach ($genericPages as $key => $meta) {
            if (!isset($items[$key])) {
                $items[$key] = admin_help_item(
                    $key,
                    (string)$meta[0],
                    (string)$meta[1],
                    (string)$meta[2],
                    ['Baca ringkasan menu terlebih dulu.', 'Isi atau cek data yang paling penting.', 'Simpan perubahan lalu lihat hasilnya di website atau laporan terkait.'],
                    ['Mulai dari data yang paling dekat dengan penjualan.', 'Gunakan bahasa yang mudah dipahami customer.'],
                    'Buka Menu',
                    $key,
                    [str_replace('/', '-', $key)]
                );
            }
        }

        return $items;
    }
}

if (!function_exists('admin_help_alias_map')) {
    function admin_help_alias_map(): array
    {
        static $map = null;

        if (is_array($map)) {
            return $map;
        }

        $map = [];
        foreach (admin_help_definitions() as $key => $item) {
            foreach ((array)($item['aliases'] ?? [$key]) as $alias) {
                $alias = trim((string)$alias, '/');
                if ($alias !== '') {
                    $map[$alias] = $key;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('admin_help_find_key')) {
    function admin_help_find_key(?string $path = null): string
    {
        $path = admin_help_normalize_path($path);
        $map = admin_help_alias_map();

        if (isset($map[$path])) {
            return $map[$path];
        }

        if (isset($map[str_replace('/', '-', $path)])) {
            return $map[str_replace('/', '-', $path)];
        }

        return 'admin/brand';
    }
}

if (!function_exists('admin_help_current')) {
    function admin_help_current(?string $path = null): array
    {
        $items = admin_help_definitions();
        $key = admin_help_find_key($path);

        return $items[$key] ?? $items['admin/brand'];
    }
}

if (!function_exists('admin_help_related_items')) {
    function admin_help_related_items(array $item): array
    {
        $items = admin_help_definitions();
        $related = [];

        foreach ((array)($item['related'] ?? []) as $key) {
            $key = admin_help_find_key((string)$key);
            if (isset($items[$key])) {
                $related[$key] = $items[$key];
            }
        }

        return $related;
    }
}

if (!function_exists('admin_help_search')) {
    function admin_help_search(string $query = ''): array
    {
        $query = trim($query);
        $query = function_exists('mb_strtolower') ? mb_strtolower($query) : strtolower($query);
        $items = admin_help_definitions();

        if ($query === '') {
            return $items;
        }

        return array_filter($items, static function (array $item) use ($query): bool {
            $text = implode(' ', [
                (string)($item['title'] ?? ''),
                (string)($item['group'] ?? ''),
                (string)($item['summary'] ?? ''),
                implode(' ', (array)($item['first_steps'] ?? [])),
                implode(' ', (array)($item['tips'] ?? [])),
            ]);
            $haystack = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);

            return str_contains($haystack, $query);
        });
    }
}


if (!function_exists('admin_help_grouped_items')) {
    function admin_help_grouped_items(array $items): array
    {
        $grouped = [];

        foreach ($items as $key => $item) {
            $group = (string)($item['group'] ?? 'Dashboard');
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][$key] = $item;
        }

        return $grouped;
    }
}

if (!function_exists('admin_help_render_popover')) {
    function admin_help_render_popover(?string $path = null): void
    {
        $item = admin_help_current($path);
        $related = admin_help_related_items($item);
        ?>
        <details class="admin-help-popover">
            <summary aria-label="Bantuan menu ini"><span>?</span><strong>Bantuan</strong></summary>
            <div class="admin-help-popover__panel" role="tooltip">
                <div class="admin-help-popover__head">
                    <span><?= esc((string)($item['group'] ?? 'Dashboard')); ?></span>
                    <h2><?= esc((string)($item['title'] ?? 'Bantuan Dashboard')); ?></h2>
                    <p><?= esc((string)($item['summary'] ?? 'Baca panduan singkat untuk memahami menu ini.')); ?></p>
                </div>
                <div class="admin-help-popover__grid">
                    <div>
                        <strong>Kerjakan dulu</strong>
                        <ol>
                            <?php foreach (array_slice((array)($item['first_steps'] ?? []), 0, 3) as $step): ?>
                                <li><?= esc((string)$step); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                    <div>
                        <strong>Tips profit/SEO</strong>
                        <ul>
                            <?php foreach (array_slice((array)($item['tips'] ?? []), 0, 2) as $tip): ?>
                                <li><?= esc((string)$tip); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="admin-help-popover__actions">
                    <a class="admin-help-primary" href="<?= esc(url((string)($item['primary_path'] ?? 'admin/brand'))); ?>"><?= esc((string)($item['primary_label'] ?? 'Buka Menu')); ?></a>
                    <a href="<?= esc(url('admin/help-center')); ?>">Buka Pusat Bantuan</a>
                </div>
                <?php if ($related): ?>
                    <div class="admin-help-related">
                        <span>Menu terkait:</span>
                        <?php foreach (array_slice($related, 0, 3) as $relatedItem): ?>
                            <a href="<?= esc(url((string)($relatedItem['primary_path'] ?? 'admin/brand'))); ?>"><?= esc((string)($relatedItem['title'] ?? 'Menu')); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>
        <?php
    }
}


/* V32.93 note: SEO Preservation & Redirect protects legacy WordPress URLs, canonical, sitemap, and internal 301 redirect map via admin/seo-preservation. */

/* V32.94 note: Breadcrumb & Internal Link Migration adds admin/internal-link-migration for breadcrumb mapping, internal link checker, safe rewrite, and WP legacy internal link reports. */

/* V32.95 note: WordPress Media Migration adds admin/wp-media-migration for wp-content image scan, batch download, media map, and safe rewrite with backup. */

/* V32.96 note: Shortcode & Gutenberg Cleaner adds admin/wp-content-cleaner for WordPress shortcode/plugin residue scan, dry-run cleaning, backup, and safe apply. */


/* V32.97 note: Elementor Safe HTML Block Import adds admin/wp-elementor-import for Elementor/page-builder detection, safe HTML block landing page drafts, complex widget warnings, and conservative sanitizer. */

/* Migration Command Center adds admin/migration-command-center as unified dashboard for WordPress migration modules, health score, checklist, and exportable report. */
