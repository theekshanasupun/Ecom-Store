<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Product;
use App\Services\Catalog;

/** Everything that goes into <head> for SEO + GEO (local / generative-engine) optimisation. */
final class Seo
{
    private static function json(array $d): string
    {
        return '<script type="application/ld+json">'.json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).'</script>'."\n";
    }

    public static function title(string $pageTitle, bool $home = false): string
    {
        $site = (string) setting('store_name', 'KIOSK');
        if ($home && ($t = trim((string) setting('seo_home_title')))) return $t;
        if ($pageTitle === '' || $pageTitle === $site) return $site;
        $tpl = (string) setting('seo_title_tpl', '{title} — {site}') ?: '{title} — {site}';

        return strtr($tpl, ['{title}' => $pageTitle, '{site}' => $site, '{tagline}' => (string) setting('store_tagline')]);
    }

    public static function imageUrl(?string $path): string
    {
        return $path ? (str_starts_with($path, 'http') ? $path : upload_url($path)) : '';
    }

    public static function head(array $meta, string $pageTitle, bool $home): string
    {
        $site = (string) setting('store_name', 'KIOSK');
        $desc = trim((string) ($meta['description'] ?? '')) ?: (string) setting('meta_description');
        $img = ($meta['og_image'] ?? '') ?: self::imageUrl((string) setting('seo_og_image')) ?: self::imageUrl((string) setting('logo'));
        $h = '';
        $noindex = setting('seo_noindex_site') === '1' || ! empty($meta['noindex']);
        $h .= '<meta name="robots" content="'.($noindex ? 'noindex,nofollow' : 'index,follow,max-image-preview:large,max-snippet:-1').'">'."\n";
        if ($kw = trim((string) setting('seo_keywords'))) $h .= '<meta name="keywords" content="'.e($kw).'">'."\n";
        if (setting('seo_canonical') === '1') $h .= '<link rel="canonical" href="'.e(strtok(url()->current(), '?')).'">'."\n";
        $h .= '<meta property="og:type" content="'.(! empty($meta['product']) ? 'product' : 'website').'"><meta property="og:url" content="'.e(url()->current()).'">'."\n";
        $h .= '<meta property="og:description" content="'.e($desc).'">'."\n";
        if ($img) $h .= '<meta property="og:image" content="'.e($img).'">'."\n";
        $h .= '<meta name="twitter:card" content="'.($img ? 'summary_large_image' : 'summary').'"><meta name="twitter:title" content="'.e($pageTitle).'"><meta name="twitter:description" content="'.e($desc).'">'."\n";
        if ($img) $h .= '<meta name="twitter:image" content="'.e($img).'">'."\n";
        if ($tw = trim((string) setting('seo_twitter'))) $h .= '<meta name="twitter:site" content="@'.e(ltrim($tw, '@')).'">'."\n";
        foreach (['seo_google_verify' => 'google-site-verification', 'seo_bing_verify' => 'msvalidate.01', 'seo_pinterest_verify' => 'p:domain_verify'] as $k => $n) {
            if ($v = trim((string) setting($k))) $h .= '<meta name="'.$n.'" content="'.e($v).'">'."\n";
        }

        // GEO: local-business signals
        if (setting('geo_on') === '1') {
            if ($c = trim((string) setting('geo_country'))) $h .= '<meta name="geo.region" content="'.e($c.(setting('geo_region') ? '-'.setting('geo_region') : '')).'">'."\n";
            if ($c = trim((string) setting('geo_city'))) $h .= '<meta name="geo.placename" content="'.e($c).'">'."\n";
            if (is_numeric(setting('geo_lat')) && is_numeric(setting('geo_lng'))) {
                $h .= '<meta name="geo.position" content="'.e(setting('geo_lat').';'.setting('geo_lng')).'"><meta name="ICBM" content="'.e(setting('geo_lat').', '.setting('geo_lng')).'">'."\n";
            }
        }

        // structured data
        if (setting('seo_schema_org') === '1') $h .= self::json(self::organization());
        if ($home && setting('seo_schema_search') === '1') {
            $h .= self::json(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $site, 'url' => url('/'), 'potentialAction' => ['@type' => 'SearchAction', 'target' => url('search').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']]);
        }
        if (! empty($meta['product']) && $meta['product'] instanceof Product && setting('seo_schema_product') === '1') $h .= self::json(self::product($meta['product']));
        if (! empty($meta['crumbs']) && setting('seo_breadcrumbs') === '1') {
            $items = [];
            foreach (array_values($meta['crumbs']) as $i => [$n, $u]) $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $n, 'item' => $u];
            $h .= self::json(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]);
        }

        // analytics
        if ($gtm = preg_replace('/[^A-Za-z0-9-]/', '', (string) setting('gtm_id'))) {
            $h .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','".$gtm."');</script>\n";
        }
        if ($ga = preg_replace('/[^A-Za-z0-9-]/', '', (string) setting('ga4_id'))) {
            $h .= '<script async src="https://www.googletagmanager.com/gtag/js?id='.$ga.'"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());gtag("config","'.$ga.'");</script>'."\n";
        }
        if ($px = preg_replace('/[^0-9]/', '', (string) setting('fb_pixel'))) {
            $h .= "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','".$px."');fbq('track','PageView');</script>\n";
        }
        if ($code = trim((string) setting('seo_head_code'))) $h .= $code."\n";

        return $h;
    }

    public static function bodyStart(): string
    {
        $h = '';
        if ($gtm = preg_replace('/[^A-Za-z0-9-]/', '', (string) setting('gtm_id'))) $h .= '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id='.$gtm.'" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>'."\n";
        if ($c = trim((string) setting('seo_body_code'))) $h .= $c."\n";

        return $h;
    }

    public static function organization(): array
    {
        $local = setting('geo_on') === '1';
        $d = ['@context' => 'https://schema.org', '@type' => $local ? (preg_replace('/[^A-Za-z]/', '', (string) setting('geo_type')) ?: 'Store') : 'Organization',
            'name' => trim((string) setting('geo_name')) ?: (string) setting('store_name'), 'url' => url('/')];
        if ($logo = self::imageUrl((string) setting('logo'))) $d['logo'] = $logo;
        if ($img = self::imageUrl((string) setting('seo_og_image'))) $d['image'] = $img;
        if ($v = trim((string) setting('store_email'))) $d['email'] = $v;
        if ($v = trim((string) setting('store_phone'))) $d['telephone'] = $v;
        $same = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) setting('geo_same_as').PHP_EOL.setting('social_instagram').PHP_EOL.setting('social_x').PHP_EOL.setting('social_tiktok')))));
        if ($same) $d['sameAs'] = array_values(array_unique($same));
        if ($local) {
            $addr = array_filter(['@type' => 'PostalAddress', 'streetAddress' => setting('geo_street'), 'addressLocality' => setting('geo_city'), 'addressRegion' => setting('geo_region'), 'postalCode' => setting('geo_postal'), 'addressCountry' => setting('geo_country')]);
            if (count($addr) > 1) $d['address'] = $addr;
            if (is_numeric(setting('geo_lat')) && is_numeric(setting('geo_lng'))) $d['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) setting('geo_lat'), 'longitude' => (float) setting('geo_lng')];
            if ($v = trim((string) setting('geo_price_range'))) $d['priceRange'] = $v;
            if ($areas = array_values(array_filter(array_map('trim', explode(',', (string) setting('geo_areas')))))) {
                $d['areaServed'] = array_map(fn ($a) => ['@type' => 'Place', 'name' => $a], $areas);
            }
            $hours = [];
            foreach (preg_split('/\R/', (string) setting('geo_hours')) as $line) {
                if (preg_match('/^\s*([A-Za-z,\- ]+?)\s+(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})\s*$/', $line, $m)) {
                    $hours[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => self::days($m[1]), 'opens' => $m[2], 'closes' => $m[3]];
                }
            }
            if ($hours) $d['openingHoursSpecification'] = $hours;
        }

        return $d;
    }

    private static function days(string $s): array
    {
        $all = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'];
        $keys = array_keys($all);
        $out = [];
        foreach (explode(',', $s) as $part) {
            $part = trim($part);
            if (str_contains($part, '-')) {
                [$a, $b] = array_map(fn ($x) => substr(ucfirst(strtolower(trim($x))), 0, 3), explode('-', $part, 2));
                $i = array_search($a, $keys, true);
                $j = array_search($b, $keys, true);
                if ($i !== false && $j !== false) for ($k = $i; ; $k = ($k + 1) % 7) { $out[] = $all[$keys[$k]]; if ($k === $j) break; }
            } else {
                $k = substr(ucfirst(strtolower($part)), 0, 3);
                if (isset($all[$k])) $out[] = $all[$k];
            }
        }

        return array_values(array_unique($out));
    }

    public static function product(Product $p): array
    {
        $d = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p->name, 'description' => str_limit((string) ($p->description ?: $p->tagline), 300), 'sku' => $p->sku ?: (string) $p->id,
            'brand' => ['@type' => 'Brand', 'name' => (string) setting('store_name')],
            'offers' => ['@type' => 'Offer', 'url' => url('product/'.$p->slug), 'priceCurrency' => currency_code(), 'price' => from_cents((int) $p->price),
                'availability' => $p->available() > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'itemCondition' => 'https://schema.org/NewCondition']];
        $imgs = array_filter(array_merge([$p->image], $p->gallery_list));
        if ($imgs) $d['image'] = array_map(fn ($i) => upload_url($i), array_values($imgs));

        return $d;
    }

    public static function robots(): string
    {
        $t = "User-agent: *\nDisallow: /cart\nDisallow: /checkout\nDisallow: /account\nDisallow: /order/\nDisallow: /search\n";
        if (setting('seo_noindex_site') === '1') $t = "User-agent: *\nDisallow: /\n";
        if (setting('seo_ai_allow') !== '1') {
            foreach (['GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'anthropic-ai', 'Claude-Web', 'Google-Extended', 'PerplexityBot', 'CCBot', 'Bytespider', 'Applebot-Extended', 'cohere-ai'] as $bot) $t .= "\nUser-agent: $bot\nDisallow: /\n";
        }
        if ($x = trim((string) setting('seo_robots_extra'))) $t .= "\n".$x."\n";

        return $t."\nSitemap: ".url('sitemap.xml')."\n";
    }

    /** /llms.txt — a plain-language site map for AI assistants (generative engine optimisation). */
    public static function llms(): string
    {
        $site = (string) setting('store_name');
        $o = '# '.$site."\n\n> ".(trim((string) setting('meta_description')) ?: (string) setting('store_tagline'))."\n";
        if ($x = trim((string) setting('seo_llms_extra'))) $o .= "\n".$x."\n";
        if (setting('geo_on') === '1') {
            $loc = implode(', ', array_filter([setting('geo_street'), setting('geo_city'), setting('geo_region'), setting('geo_postal'), setting('geo_country')]));
            if ($loc) $o .= "\n## Location\n\n".$loc."\n";
            if ($a = trim((string) setting('geo_areas'))) $o .= "\nService area: ".$a."\n";
            if ($h = trim((string) setting('geo_hours'))) $o .= "\nHours:\n".$h."\n";
        }
        $o .= "\n## Departments\n\n";
        foreach (Catalog::categories() as $c) $o .= '- ['.$c->name.']('.url('shop/'.$c->slug).')'.($c->tagline ? ': '.$c->tagline : '')."\n";
        $o .= "\n## Products\n\n";
        foreach (Product::live()->orderByDesc('featured')->orderByDesc('sales')->limit(60)->get(['name', 'slug', 'tagline', 'price']) as $p) {
            $o .= '- ['.$p->name.']('.url('product/'.$p->slug).'): '.($p->tagline ? $p->tagline.' — ' : '').money($p->price)."\n";
        }
        if ($pg = Page::where('status', 'published')->where('seo_noindex', false)->get(['title', 'slug'])) {
            $o .= "\n## Information\n\n";
            foreach ($pg as $p) $o .= '- ['.$p->title.']('.url('p/'.$p->slug).")\n";
        }

        return $o;
    }
}
