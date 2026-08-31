<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }

if (!function_exists('universal_seo_text')) {
    function universal_seo_text(mixed $value): string {
        if (is_array($value)) { $value = implode(' ', array_map('strval', $value)); }
        $text = html_entity_decode(strip_tags((string)$value), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';
        return trim($text);
    }
}

if (!function_exists('universal_seo_len')) {
    function universal_seo_len(mixed $value): int { $text = universal_seo_text($value); return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text); }
}

if (!function_exists('universal_seo_words')) {
    function universal_seo_words(mixed $value): int { $text = universal_seo_text($value); if ($text === '') return 0; return count(array_filter(preg_split('/\s+/u', $text) ?: [])); }
}

if (!function_exists('universal_seo_keywords')) {
    function universal_seo_keywords(mixed $keywords): array {
        if (is_array($keywords)) return array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $keywords)));
        return array_values(array_filter(array_map('trim', explode(',', (string)$keywords))));
    }
}

if (!function_exists('universal_seo_grade')) {
    function universal_seo_grade(int $score): string { return $score >= 90 ? 'A' : ($score >= 78 ? 'B' : ($score >= 65 ? 'C' : ($score >= 50 ? 'D' : 'E'))); }
}

if (!function_exists('universal_seo_issue')) {
    function universal_seo_issue(string $severity, string $field, string $title, string $message, string $suggestion = '', int $penalty = 0): array {
        return ['severity'=>$severity,'field'=>$field,'title'=>$title,'message'=>$message,'suggestion'=>$suggestion,'penalty'=>max(0,$penalty)];
    }
}

if (!function_exists('universal_seo_status')) {
    function universal_seo_status(array $issues, int $score): string {
        foreach ($issues as $i) if (($i['severity'] ?? '') === 'error') return 'error';
        foreach ($issues as $i) if (($i['severity'] ?? '') === 'warning') return 'warning';
        return $score >= 78 ? 'ok' : 'info';
    }
}

if (!function_exists('universal_seo_status_label')) {
    function universal_seo_status_label(string $status): string { return match($status){'error'=>'Prioritas','warning'=>'Perlu Dipoles','ok'=>'Siap Index',default=>'Info'}; }
}

if (!function_exists('universal_seo_status_class')) {
    function universal_seo_status_class(string $status): string { return 'admin-status-pill admin-status-pill--' . (in_array($status, ['ok','warning','error','info'], true) ? $status : 'info'); }
}

if (!function_exists('universal_seo_type_label')) {
    function universal_seo_type_label(string $type): string { return match($type){'product'=>business_label('product','Produk'),'service'=>business_label('service','Layanan'),'article'=>business_label('article','Artikel'),'landing_page'=>'Landing Page','seo_landing'=>'SEO Landing','portfolio'=>'Portfolio',default=>'Halaman'}; }
}

if (!function_exists('universal_seo_blocks_text')) {
    function universal_seo_blocks_text(mixed $data): string {
        $out=[]; $walk=function(mixed $v) use (&$walk,&$out): void { if (is_array($v)) { foreach($v as $k=>$c){ if(in_array((string)$k,['image','url','button_url','primary_url','secondary_url'],true)) continue; $walk($c);} return;} if(is_scalar($v)){ $t=universal_seo_text($v); if($t!=='' && !preg_match('#^(https?:)?//#i',$t) && !str_starts_with($t,'#')) $out[]=$t; } };
        $walk($data); return implode(' ', array_unique($out));
    }
}

if (!function_exists('universal_seo_internal_links')) {
    function universal_seo_internal_links(string $html): int { preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/i', $html, $m); $n=0; foreach(($m[1]??[]) as $h){$h=trim((string)$h); if(str_starts_with($h,'/')||str_starts_with($h,SITE_URL)) $n++;} return $n; }
}

if (!function_exists('universal_seo_items')) {
    function universal_seo_items(bool $fresh=false): array {
        static $cache=null; if(!$fresh && is_array($cache)) return $cache; $items=[];
        foreach (function_exists('all_products') ? all_products() : [] as $p) {
            $seo=is_array($p['seo']??null)?$p['seo']:[]; $slug=(string)($p['slug']??''); $cat=strtolower((string)($p['category']??'').' '.(string)($p['item_type_key']??'')); $service=str_contains($cat,'jasa')||str_contains($cat,'layanan')||str_contains($cat,'service');
            $items[]=['type'=>$service?'service':'product','id'=>(string)($p['id']??''),'title'=>(string)($p['title']??''),'slug'=>$slug,'url'=>$slug?product_url($slug):url('katalog'),'edit_url'=>url('admin/produk?action=edit&id='.(int)($p['id']??0)),'source'=>(string)($p['source']??'admin'),'status_raw'=>(string)($p['status']??'published'),'indexable'=>(string)($p['status']??'published')!=='draft','meta_title'=>(string)($seo['title']??$p['meta_title']??''),'meta_description'=>(string)($seo['description']??$p['meta_description']??''),'keywords'=>universal_seo_keywords($seo['keywords']??$p['keywords']??[]),'body'=>universal_seo_text(($p['excerpt']??'').' '.($p['description']??'').' '.($p['content']??'').' '.implode(' ',(array)($p['features']??[]))),'image'=>(string)($p['image']??''),'image_alt'=>(string)($p['image_alt']??''),'schema_type'=>(string)($seo['schema_type']??($service?'Service':'Product')),'canonical'=>$slug?product_url($slug):'','internal_link_count'=>universal_seo_internal_links((string)($p['content']??''))];
        }
        foreach (function_exists('all_articles') ? all_articles() : [] as $a) {
            $slug=(string)($a['slug']??''); $items[]=['type'=>'article','id'=>(string)($a['id']??''),'title'=>(string)($a['title']??''),'slug'=>$slug,'url'=>$slug?article_url($slug):url('artikel'),'edit_url'=>url('admin/artikel?action=edit&id='.(int)($a['id']??0)),'source'=>(string)($a['source']??'admin'),'status_raw'=>'published','indexable'=>(string)($a['robots']??'index, follow')!=='noindex','meta_title'=>(string)($a['meta_title']??''),'meta_description'=>(string)($a['meta_description']??$a['excerpt']??''),'keywords'=>universal_seo_keywords($a['focus_keyword']??$a['meta_keywords']??$a['keywords']??[]),'body'=>universal_seo_text(($a['excerpt']??'').' '.($a['content']??'')),'image'=>(string)($a['image']??''),'image_alt'=>(string)($a['image_alt']??''),'schema_type'=>(string)($a['schema_type']??'Article'),'canonical'=>$slug?article_url($slug):'','internal_link_count'=>universal_seo_internal_links((string)($a['content']??''))];
        }
        foreach (function_exists('landing_page_all') ? landing_page_all($fresh) : [] as $lp) {
            $slug=(string)($lp['slug']??''); $items[]=['type'=>'landing_page','id'=>(string)($lp['id']??''),'title'=>(string)($lp['title']??''),'slug'=>$slug,'url'=>$slug?landing_page_url($slug):url('landing'),'edit_url'=>url('admin/landing-pages?builder='.rawurlencode((string)($lp['id']??$slug))),'source'=>'landing-builder','status_raw'=>(string)($lp['status']??'draft'),'indexable'=>(string)($lp['status']??'draft')==='published'&&!empty($lp['indexable']),'meta_title'=>(string)($lp['meta_title']??''),'meta_description'=>(string)($lp['meta_description']??''),'keywords'=>universal_seo_keywords($lp['meta_keywords']??''),'body'=>universal_seo_blocks_text($lp['blocks']??[]),'image'=>(string)($lp['og_image']??''),'image_alt'=>(string)($lp['title']??''),'schema_type'=>'WebPage','canonical'=>$slug?landing_page_url($slug):'','internal_link_count'=>0];
        }
        foreach (function_exists('seo_landing_public_records') ? seo_landing_public_records(true) : [] as $l) {
            $items[]=['type'=>'seo_landing','id'=>(string)($l['key']??''),'title'=>(string)($l['title']??$l['h1']??''),'slug'=>(string)($l['path']??''),'url'=>(string)($l['url']??url((string)($l['path']??''))),'edit_url'=>url('admin/seo-landings'),'source'=>(string)($l['source']??'generated'),'status_raw'=>!empty($l['enabled'])?'published':'disabled','indexable'=>!empty($l['indexable'])&&!empty($l['enabled']),'meta_title'=>(string)($l['title']??''),'meta_description'=>(string)($l['description']??''),'keywords'=>universal_seo_keywords([$l['prefix']??'', $l['slug']??'']),'body'=>universal_seo_text(($l['description']??'').' '.($l['summary']??'')),'image'=>'','image_alt'=>'','schema_type'=>'CollectionPage','canonical'=>(string)($l['canonical']??$l['url']??''),'internal_link_count'=>count((array)($l['product_slugs']??[])),'product_count'=>(int)($l['product_count']??0)];
        }
        $items[]=['type'=>'static_page','id'=>'home','title'=>SITE_NAME,'slug'=>'','url'=>url(''),'edit_url'=>url('admin/homepage'),'source'=>'core-page','status_raw'=>'published','indexable'=>true,'meta_title'=>DEFAULT_META_TITLE,'meta_description'=>DEFAULT_META_DESCRIPTION,'keywords'=>universal_seo_keywords(DEFAULT_META_KEYWORDS),'body'=>SITE_TAGLINE.' '.DEFAULT_META_DESCRIPTION,'image'=>DEFAULT_OG_IMAGE,'image_alt'=>SITE_NAME,'schema_type'=>(string)(business_settings()['schema_profile']??'LocalBusiness'),'canonical'=>url(''),'internal_link_count'=>4];
        foreach ([['katalog',business_label('catalog','Katalog'),'CollectionPage'],['artikel',business_label('article','Artikel'),'Blog'],['portfolio','Portfolio / Showcase','CollectionPage']] as $sp) $items[]=['type'=>$sp[0]==='portfolio'?'portfolio':'static_page','id'=>$sp[0],'title'=>$sp[1],'slug'=>$sp[0],'url'=>url($sp[0]),'edit_url'=>url($sp[0]==='artikel'?'admin/artikel':($sp[0]==='katalog'?'admin/produk':'admin/business')),'source'=>'core-page','status_raw'=>'published','indexable'=>true,'meta_title'=>$sp[1].' - '.SITE_NAME,'meta_description'=>$sp[1].' dari '.SITE_NAME.'.','keywords'=>[$sp[1]],'body'=>$sp[1].' untuk membangun trust, trafik, dan conversion.','image'=>DEFAULT_OG_IMAGE,'image_alt'=>$sp[1],'schema_type'=>$sp[2],'canonical'=>url($sp[0]),'internal_link_count'=>2];
        $seen=[]; $cache=array_values(array_filter($items, static function($i) use (&$seen){$u=(string)($i['url']??''); if(isset($seen[$u])) return false; $seen[$u]=1; return true;})); return $cache;
    }
}

if (!function_exists('universal_seo_audit_item')) {
    function universal_seo_audit_item(array $item): array {
        $issues=[]; $type=(string)($item['type']??''); $title=trim((string)($item['title']??'')); $mt=trim((string)($item['meta_title']??'')); $md=trim((string)($item['meta_description']??'')); $words=universal_seo_words($item['body']??''); $min=match($type){'article'=>120,'landing_page','seo_landing'=>80,'product','service'=>45,default=>35};
        if(empty($item['indexable'])) $issues[]=universal_seo_issue('info','indexable','Belum indexable','Halaman ini draft/disabled/noindex atau belum disiapkan untuk Google.','Publish dan aktifkan indexable hanya jika kontennya sudah siap.',0);
        if($title==='') $issues[]=universal_seo_issue('error','title','Judul kosong','Halaman wajib punya judul yang jelas.','Isi judul sesuai keyword dan value utama.',22);
        $l=universal_seo_len($mt ?: $title); if($mt==='') $issues[]=universal_seo_issue('warning','meta_title','Meta title belum khusus','Halaman masih mengandalkan judul/default.','Buat meta title 45-65 karakter.',10); elseif($l<35) $issues[]=universal_seo_issue('warning','meta_title','Meta title terlalu pendek','Meta title sekitar '.$l.' karakter.','Tambahkan manfaat/lokasi/konteks niche.',6); elseif($l>70) $issues[]=universal_seo_issue('info','meta_title','Meta title agak panjang','Meta title sekitar '.$l.' karakter.','Ringkas agar tidak terpotong.',3);
        $dl=universal_seo_len($md); if($md==='') $issues[]=universal_seo_issue('warning','meta_description','Meta description kosong','Deskripsi pencarian belum disiapkan.','Isi 120-160 karakter berisi manfaat dan CTA ringan.',12); elseif($dl<90) $issues[]=universal_seo_issue('warning','meta_description','Meta description terlalu pendek','Meta description sekitar '.$dl.' karakter.','Tambahkan manfaat, bukti, area layanan, atau CTA.',8); elseif($dl>170) $issues[]=universal_seo_issue('info','meta_description','Meta description agak panjang','Meta description sekitar '.$dl.' karakter.','Usahakan 120-160 karakter.',3);
        if($words<$min) $issues[]=universal_seo_issue('warning','content','Konten masih tipis','Konten sekitar '.$words.' kata, ideal minimal '.$min.' kata.','Tambahkan manfaat, detail, FAQ, bukti, dan link internal.',10);
        if(trim((string)($item['canonical']??''))==='') $issues[]=universal_seo_issue('warning','canonical','Canonical belum jelas','URL canonical membantu Google mengenali halaman utama.','Pastikan canonical sama dengan URL publik.',7);
        if(trim((string)($item['schema_type']??''))==='') $issues[]=universal_seo_issue('warning','schema','Schema belum dipilih','Schema membantu Google memahami tipe halaman.','Pilih Product, Service, Article, Person, LocalBusiness, atau CollectionPage.',8);
        if(!universal_seo_keywords($item['keywords']??[])) $issues[]=universal_seo_issue('info','keywords','Keyword internal kosong','Keyword internal membantu menjaga fokus konten lintas niche.','Tambahkan 2-5 keyword utama.',2);
        if(in_array($type,['product','service','article','landing_page'],true)){ if(trim((string)($item['image']??''))===''||str_contains((string)($item['image']??''),'placeholder')) $issues[]=universal_seo_issue('warning','image','Gambar utama belum spesifik','Halaman masih tanpa gambar khusus atau memakai placeholder.','Upload gambar asli/brand.',8); if(trim((string)($item['image_alt']??''))==='') $issues[]=universal_seo_issue('warning','image_alt','Alt gambar kosong','Alt gambar membantu aksesibilitas dan SEO image.','Isi alt natural sesuai gambar.',6); }
        if(in_array($type,['article','landing_page','seo_landing'],true) && (int)($item['internal_link_count']??0)<1) $issues[]=universal_seo_issue('info','internal_links','Internal link bisa diperkuat','Halaman belum terlihat menautkan ke halaman penting lain.','Tambahkan link ke produk, layanan, artikel, form, atau LP utama.',3);
        usort($issues,static fn($a,$b)=>(['ok'=>1,'info'=>2,'warning'=>3,'error'=>4][$b['severity']??'info']??2)<=> (['ok'=>1,'info'=>2,'warning'=>3,'error'=>4][$a['severity']??'info']??2)); $score=max(0,min(100,100-array_sum(array_map(static fn($i)=>(int)($i['penalty']??0),$issues))));
        return $item+['score'=>$score,'grade'=>universal_seo_grade($score),'status'=>universal_seo_status($issues,$score),'issues'=>$issues,'issue_count'=>count($issues),'meta'=>['meta_title_length'=>$l,'meta_description_length'=>$dl,'body_words'=>$words,'schema_type'=>(string)($item['schema_type']??''),'keyword_count'=>count(universal_seo_keywords($item['keywords']??[]))]];
    }
}

if (!function_exists('universal_seo_summary')) {
    function universal_seo_summary(string $type='all'): array { $items=array_map('universal_seo_audit_item', universal_seo_items(true)); if($type!=='all') $items=array_values(array_filter($items,static fn($i)=>(string)$i['type']===$type)); $counts=['total'=>count($items),'indexable'=>0,'error'=>0,'warning'=>0,'info'=>0,'ok'=>0,'schema_ready'=>0]; $sum=0; foreach($items as $i){$sum+=(int)$i['score']; if(!empty($i['indexable']))$counts['indexable']++; $counts[(string)$i['status']]++; if(trim((string)($i['schema_type']??''))!=='')$counts['schema_ready']++;} $avg=$items?(int)round($sum/count($items)):100; return ['generated_at'=>date('Y-m-d H:i:s'),'score_average'=>$avg,'grade_average'=>universal_seo_grade($avg),'counts'=>$counts,'items'=>$items,'action_plan'=>universal_seo_action_plan($items,$counts,$avg)]; }
}



if (!function_exists('universal_seo_issue_has_field')) {
    function universal_seo_issue_has_field(array $item, string $field): bool {
        foreach ((array)($item['issues'] ?? []) as $issue) {
            if ((string)($issue['field'] ?? '') === $field) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('universal_seo_opportunity_summary')) {
    function universal_seo_opportunity_summary(array $items): array {
        $typeMap = [];
        $topIssues = [];
        $quickWins = [];
        $contentGaps = [];
        $internalLinkTargets = [];

        foreach ($items as $item) {
            $type = (string)($item['type'] ?? 'static_page');
            $typeLabel = universal_seo_type_label($type);
            if (!isset($typeMap[$type])) {
                $typeMap[$type] = ['type' => $type, 'label' => $typeLabel, 'total' => 0, 'score_sum' => 0, 'warning' => 0, 'error' => 0, 'ok' => 0, 'info' => 0];
            }

            $typeMap[$type]['total']++;
            $typeMap[$type]['score_sum'] += (int)($item['score'] ?? 0);
            $status = (string)($item['status'] ?? 'info');
            if (isset($typeMap[$type][$status])) {
                $typeMap[$type][$status]++;
            }

            foreach ((array)($item['issues'] ?? []) as $issue) {
                $key = (string)($issue['field'] ?? 'other') . '|' . (string)($issue['title'] ?? 'Catatan SEO');
                if (!isset($topIssues[$key])) {
                    $topIssues[$key] = [
                        'field' => (string)($issue['field'] ?? 'other'),
                        'title' => (string)($issue['title'] ?? 'Catatan SEO'),
                        'severity' => (string)($issue['severity'] ?? 'info'),
                        'count' => 0,
                    ];
                }
                $topIssues[$key]['count']++;
            }

            $score = (int)($item['score'] ?? 0);
            $issueCount = (int)($item['issue_count'] ?? 0);
            if (!empty($item['indexable']) && $score >= 70 && $score < 95 && $issueCount <= 4) {
                $quickWins[] = $item;
            }
            if (universal_seo_issue_has_field($item, 'content')) {
                $contentGaps[] = $item;
            }
            if (universal_seo_issue_has_field($item, 'internal_links')) {
                $internalLinkTargets[] = $item;
            }
        }

        foreach ($typeMap as &$row) {
            $row['score_average'] = $row['total'] > 0 ? (int)round($row['score_sum'] / $row['total']) : 100;
            unset($row['score_sum']);
        }
        unset($row);

        usort($quickWins, static fn(array $a, array $b): int => ((int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0)) ?: ((int)($a['issue_count'] ?? 0) <=> (int)($b['issue_count'] ?? 0)));
        usort($contentGaps, static fn(array $a, array $b): int => ((int)($a['meta']['body_words'] ?? 0) <=> (int)($b['meta']['body_words'] ?? 0)) ?: ((int)($a['score'] ?? 0) <=> (int)($b['score'] ?? 0)));
        usort($internalLinkTargets, static fn(array $a, array $b): int => ((int)($a['internal_link_count'] ?? 0) <=> (int)($b['internal_link_count'] ?? 0)) ?: ((int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0)));
        usort($topIssues, static fn(array $a, array $b): int => ((int)($b['count'] ?? 0) <=> (int)($a['count'] ?? 0)) ?: strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
        usort($typeMap, static fn(array $a, array $b): int => ((int)($a['score_average'] ?? 0) <=> (int)($b['score_average'] ?? 0)) ?: ((int)($b['total'] ?? 0) <=> (int)($a['total'] ?? 0)));

        $focus = 'Fondasi SEO stabil. Lanjutkan ekspansi konten niche, internal link, dan landing page per kategori agar traffic makin siap dikonversi.';
        if ($contentGaps) {
            $focus = 'Prioritas minggu ini: tambah kedalaman konten pada halaman yang masih tipis agar trust dan ranking lebih kuat.';
        } elseif ($internalLinkTargets) {
            $focus = 'Prioritas minggu ini: sambungkan artikel/landing page ke produk, layanan, form, dan halaman penawaran utama.';
        } elseif ($quickWins) {
            $focus = 'Prioritas minggu ini: poles quick win SEO karena beberapa halaman sudah dekat ke grade A.';
        }

        return [
            'recommended_focus' => $focus,
            'quick_wins' => array_slice($quickWins, 0, 5),
            'content_gaps' => array_slice($contentGaps, 0, 5),
            'internal_link_targets' => array_slice($internalLinkTargets, 0, 5),
            'top_issues' => array_slice(array_values($topIssues), 0, 6),
            'type_map' => array_slice(array_values($typeMap), 0, 8),
        ];
    }
}

if (!function_exists('universal_seo_action_plan')) {
    function universal_seo_action_plan(array $items,array $counts,int $avg): array { $plan=[]; if(($counts['error']??0)>0)$plan[]='Prioritaskan halaman berstatus Prioritas sebelum promosi/iklan.'; if(($counts['warning']??0)>0)$plan[]='Poles meta title, meta description, konten tipis, gambar, dan alt text.'; if(($counts['schema_ready']??0)<($counts['total']??0))$plan[]='Lengkapi schema sesuai tipe bisnis: Product, Service, Article, Person, LocalBusiness, atau CollectionPage.'; if($avg>=85)$plan[]='Fondasi SEO sudah bagus. Next: tambah artikel pendukung, internal link, dan landing page per kategori/area.'; return $plan?:['Mantap. Lanjut tambah konten niche, FAQ, studi kasus, dan tracking conversion agar SEO makin berdampak ke penjualan.']; }
}

if (!function_exists('universal_seo_sitemap_urls')) {
    function universal_seo_sitemap_urls(array $base=[]): array { $urls=$base; foreach(universal_seo_items(true) as $i){ if(empty($i['indexable'])) continue; $loc=(string)($i['canonical']?:$i['url']??''); if($loc==='') continue; $urls[]=['loc'=>$loc,'changefreq'=>in_array($i['type'],['product','service','seo_landing'],true)?'weekly':'monthly','priority'=>in_array($i['type'],['static_page'],true)?'0.8':'0.7']; } $seen=[]; return array_values(array_filter($urls,static function($u)use(&$seen){$loc=(string)($u['loc']??''); if($loc===''||isset($seen[$loc]))return false; $seen[$loc]=1; return true;})); }
}

if (!function_exists('universal_seo_business_schema')) {
    function universal_seo_business_schema(): void { if(!function_exists('add_schema')) return; $settings=function_exists('business_settings')?business_settings():[]; $profile=(string)($settings['schema_profile']??'LocalBusiness'); if($profile==='Person'){ add_schema(['@context'=>'https://schema.org','@type'=>'Person','name'=>SITE_NAME,'url'=>SITE_URL,'image'=>DEFAULT_OG_IMAGE,'description'=>DEFAULT_META_DESCRIPTION,'sameAs'=>array_values(function_exists('theme_social_links')?theme_social_links():[])]); } elseif($profile==='Service'){ add_schema(['@context'=>'https://schema.org','@type'=>'Service','name'=>SITE_NAME,'provider'=>['@type'=>'Organization','name'=>SITE_NAME],'areaServed'=>'Indonesia','description'=>DEFAULT_META_DESCRIPTION]); } elseif(function_exists('local_business_schema')) { local_business_schema(['name'=>SITE_NAME,'description'=>DEFAULT_META_DESCRIPTION,'image'=>DEFAULT_OG_IMAGE,'url'=>SITE_URL,'phone'=>SITE_PHONE,'email'=>SITE_EMAIL]); } }
}
