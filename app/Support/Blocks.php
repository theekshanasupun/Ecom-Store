<?php

namespace App\Support;

use App\Models\Category;
use App\Services\Catalog;

/**
 * Block registry for the visual page builder (pages + home page).
 * A layout is a JSON array of blocks: {id, type, props{…}, style{bg,color,pad,align,width,hide}}.
 * Every output value is escaped here; "html" and "text" blocks accept trusted admin markup only.
 */
final class Blocks
{
    /** type => [label, icon, fields[], defaults] ; field = [key,label,type,(options)] */
    public static function registry(): array
    {
        return [
            'hero' => ['Hero', '▣', [['eyebrow', 'Small label', 'text'], ['title', 'Headline', 'text'], ['accent', 'Accent words', 'text'], ['text', 'Paragraph', 'area'], ['btn', 'Button text', 'text'], ['url', 'Button link', 'text'], ['btn2', 'Second button', 'text'], ['url2', 'Second link', 'text'], ['image', 'Background image URL', 'text']],
                ['eyebrow' => 'New season', 'title' => 'Make a bold first impression', 'accent' => 'bold', 'text' => 'Tell visitors what makes your store different.', 'btn' => 'Shop now', 'url' => '/', 'btn2' => '', 'url2' => '', 'image' => '']],
            'home_hero' => ['Home hero (current)', '★', [['line1', 'Headline — line 1', 'text'], ['line2', 'Headline — line 2 (accent)', 'text'], ['text', 'Intro paragraph', 'area']],
                ['line1' => (string) setting('hero_line1'), 'line2' => (string) setting('hero_line2'), 'text' => (string) setting('hero_text')]],
            'home_panels' => ['Department panels (current)', '▥', [], []],
            'home_trending' => ['Trending products (current)', '▦', [['title', 'Heading (line break allowed)', 'area'], ['count', 'How many products', 'number']], ['title' => "Trending\nright now", 'count' => '8']],
            'home_mixit' => ['Mix-it-all-up (current)', '◫', [['eyebrow', 'Small label', 'text'], ['title', 'Heading', 'area'], ['text', 'Paragraph', 'area'], ['btn', 'Button text', 'text'], ['url', 'Button link', 'text']],
                ['eyebrow' => 'One cart, one checkout', 'title' => "Mix it\nall up.", 'text' => 'A toy, a tee and a game key can ride in the same order. Keys arrive instantly, physical items ship, and you pay once.', 'btn' => 'See your cart →', 'url' => 'cart']],
            'text' => ['Rich text', '¶', [['title', 'Heading', 'text'], ['body', 'Content (HTML allowed)', 'area']], ['title' => 'Heading', 'body' => '<p>Write something here.</p>']],
            'image_text' => ['Image + text', '◨', [['image', 'Image URL', 'text'], ['title', 'Heading', 'text'], ['body', 'Text', 'area'], ['btn', 'Button text', 'text'], ['url', 'Button link', 'text'], ['side', 'Image side', 'select', ['left' => 'Left', 'right' => 'Right']]],
                ['image' => '', 'title' => 'A story worth telling', 'body' => 'Pair a picture with a short description.', 'btn' => '', 'url' => '', 'side' => 'left']],
            'products' => ['Product grid', '▦', [['title', 'Heading', 'text'], ['source', 'Show', 'select', ['featured' => 'Featured / trending', 'new' => 'Newest', 'sale' => 'On sale', 'category' => 'From a department']], ['category', 'Department (when chosen above)', 'category'], ['count', 'How many', 'number'], ['cols', 'Columns', 'select', ['2' => '2', '3' => '3', '4' => '4']]],
                ['title' => 'Trending now', 'source' => 'featured', 'category' => '', 'count' => '8', 'cols' => '4']],
            'categories' => ['Department tiles', '▤', [['title', 'Heading', 'text']], ['title' => 'Shop by department']],
            'banner' => ['Banner / CTA', '▬', [['title', 'Headline', 'text'], ['text', 'Text', 'text'], ['btn', 'Button text', 'text'], ['url', 'Button link', 'text']], ['title' => 'Free delivery over $75', 'text' => 'Limited time — stock up today.', 'btn' => 'Start shopping', 'url' => '/']],
            'features' => ['Feature icons', '✦', [['title', 'Heading', 'text'], ['items', 'One per line: Icon | Title | Text', 'area']], ['title' => '', 'items' => "⚡ | Instant delivery | Digital keys arrive in seconds\n🚚 | Fast shipping | Ships in 2 days\n🔒 | Secure checkout | Encrypted & protected"]],
            'testimonials' => ['Testimonials', '❝', [['title', 'Heading', 'text'], ['items', 'One per line: Quote | Name', 'area']], ['title' => 'Loved by customers', 'items' => "Fast, easy and exactly as described. | Sam R.\nKey arrived instantly — will buy again. | Jordan K.\nGreat prices and friendly support. | Priya M."]],
            'faq' => ['FAQ', '？', [['title', 'Heading', 'text'], ['items', 'One per line: Question | Answer', 'area']], ['title' => 'Questions, answered', 'items' => "How fast is delivery? | Digital items are delivered right after payment.\nCan I return an item? | Yes — contact us within 14 days."]],
            'countdown' => ['Countdown', '⏱', [['title', 'Heading', 'text'], ['date', 'Ends at (YYYY-MM-DD HH:MM)', 'text'], ['btn', 'Button text', 'text'], ['url', 'Button link', 'text']], ['title' => 'Sale ends in', 'date' => '', 'btn' => 'Shop the sale', 'url' => '/']],
            'video' => ['Video embed', '▶', [['url', 'YouTube / Vimeo URL', 'text'], ['title', 'Heading', 'text']], ['url' => '', 'title' => '']],
            'newsletter' => ['Email signup', '✉', [['title', 'Heading', 'text'], ['text', 'Text', 'text'], ['btn', 'Button text', 'text']], ['title' => 'Join the list', 'text' => 'Deals and new drops, straight to your inbox.', 'btn' => 'Subscribe']],
            'gallery' => ['Image gallery', '▩', [['title', 'Heading', 'text'], ['items', 'One per line: Image URL | Caption | Link (optional)', 'area'], ['cols', 'Columns', 'select', ['2' => '2', '3' => '3', '4' => '4']]], ['title' => 'Gallery', 'items' => "https://picsum.photos/seed/k1/600/600 | First\nhttps://picsum.photos/seed/k2/600/600 | Second\nhttps://picsum.photos/seed/k3/600/600 | Third", 'cols' => '3']],
            'logos' => ['Logo / brand strip', '◌', [['title', 'Heading', 'text'], ['items', 'One per line: Image URL or plain name | Link (optional)', 'area']], ['title' => 'As seen in', 'items' => "BRAND ONE\nBRAND TWO\nBRAND THREE\nBRAND FOUR"]],
            'pricing' => ['Pricing / plans table', '☰', [['title', 'Heading', 'text'], ['items', 'One per line: Plan | Price | Features (; separated) | Button | Link | featured', 'area']], ['title' => 'Choose your plan', 'items' => "Starter | \$9 | 1 item; Email support | Choose | / |\nPro | \$29 | Unlimited; Priority support; Gift wrap | Choose | / | featured\nTeam | \$79 | Everything in Pro; Dedicated manager | Contact | / |"]],
            'strip' => ['Announcement strip', '▭', [['text', 'Message', 'text'], ['btn', 'Link text', 'text'], ['url', 'Link', 'text']], ['text' => 'Free delivery on orders over $75', 'btn' => 'Shop now', 'url' => '/']],
            'spacer' => ['Spacer', '↕', [['h', 'Height (px)', 'number']], ['h' => '48']],
            'html' => ['Custom HTML', '</>', [['code', 'HTML', 'area']], ['code' => '<p>Custom HTML</p>']],
        ];
    }

    /** type => palette group. */
    public static function groups(): array
    {
        return ['Current home' => ['home_hero', 'home_panels', 'home_trending', 'home_mixit'], 'Hero & content' => ['hero', 'text', 'image_text', 'banner', 'strip', 'features', 'video', 'gallery'],
            'Store' => ['products', 'categories', 'countdown', 'newsletter'], 'Social proof' => ['testimonials', 'faq', 'logos', 'pricing'], 'Layout' => ['spacer', 'html']];
    }

    /** Default block styling per type (home_* blocks bring their own spacing). */
    public static function defaultStyle(string $type): array
    {
        $base = ['bg' => '', 'color' => '', 'pad' => 'md', 'align' => 'left', 'width' => 'box', 'hide' => ''];

        return str_starts_with($type, 'home_') ? ['pad' => 'none', 'width' => 'full'] + $base : ($type === 'strip' ? ['pad' => 'sm', 'width' => 'full', 'align' => 'center', 'bg' => '#c6ff3d', 'color' => '#0b0b0d'] + $base : $base);
    }

    /** The home page exactly as it is rendered today, expressed as blocks. */
    public static function currentHome(): array
    {
        $out = [];
        foreach (['home_hero', 'home_panels', 'home_trending', 'home_mixit'] as $t) {
            $out[] = ['id' => 'cur'.substr(md5($t), 0, 6), 'type' => $t, 'props' => self::registry()[$t][3], 'style' => self::defaultStyle($t), 'current' => true];
        }

        return $out;
    }

    public static function decode(?string $json): array
    {
        $a = json_decode((string) $json, true);

        return is_array($a) ? array_values(array_filter($a, fn ($b) => is_array($b) && isset(self::registry()[$b['type'] ?? ''] ))) : [];
    }

    /** Validate / normalise posted JSON before saving. */
    public static function clean(string $json): string
    {
        $out = [];
        foreach (self::decode($json) as $b) {
            $def = self::registry()[$b['type']];
            $props = [];
            foreach ($def[2] as $f) $props[$f[0]] = mb_substr((string) ($b['props'][$f[0]] ?? ($def[3][$f[0]] ?? '')), 0, 20000);
            $st = (array) ($b['style'] ?? []) + ['bg' => '', 'color' => '', 'pad' => 'md', 'align' => 'left', 'width' => 'box', 'hide' => ''];
            $out[] = ['id' => preg_replace('/[^a-z0-9]/i', '', (string) ($b['id'] ?? '')) ?: bin2hex(random_bytes(4)), 'type' => $b['type'], 'props' => $props, 'style' => [
                'bg' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($st['bg'] ?? '')) ? $st['bg'] : '',
                'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($st['color'] ?? '')) ? $st['color'] : '',
                'pad' => in_array($st['pad'] ?? 'md', ['none', 'sm', 'md', 'lg', 'xl'], true) ? $st['pad'] : 'md',
                'align' => in_array($st['align'] ?? 'left', ['left', 'center', 'right'], true) ? $st['align'] : 'left',
                'width' => in_array($st['width'] ?? 'box', ['box', 'full', 'narrow'], true) ? $st['width'] : 'box',
                'hide' => in_array($st['hide'] ?? '', ['', 'mobile', 'desktop'], true) ? $st['hide'] : '',
            ]];
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function href(string $u): string
    {
        $u = trim($u);
        if ($u === '') return '#';
        if (preg_match('#^(https?:)?//#i', $u) || str_starts_with($u, 'mailto:') || str_starts_with($u, 'tel:')) return $u;
        if (preg_match('/^\s*(javascript|data):/i', $u)) return '#';

        return url(ltrim($u, '/'));
    }

    private static function lines(string $s): array
    {
        $o = [];
        foreach (preg_split('/\R/', trim($s)) as $l) if (trim($l) !== '') $o[] = array_map('trim', explode('|', $l));

        return $o;
    }

    public static function renderAll(array $blocks): string
    {
        $h = '';
        foreach ($blocks as $b) $h .= self::render($b);

        return $h;
    }

    public static function render(array $b): string
    {
        $type = $b['type'] ?? '';
        $reg = self::registry();
        if (! isset($reg[$type])) return '';
        $p = (array) ($b['props'] ?? []) + $reg[$type][3];
        $st = (array) ($b['style'] ?? []) + ['bg' => '', 'color' => '', 'pad' => 'md', 'align' => 'left', 'width' => 'box', 'hide' => ''];
        $inner = self::{'b_'.$type}($p);
        if ($inner === '') return '';
        $css = ($st['bg'] ? 'background:'.$st['bg'].';' : '').($st['color'] ? 'color:'.$st['color'].';' : '');
        $cls = 'bb bb-'.$type.' pad-'.$st['pad'].' al-'.$st['align'].' w-'.$st['width'].($st['hide'] ? ' hide-'.$st['hide'] : '');

        return '<section class="'.e($cls).'" data-bid="'.e((string) ($b['id'] ?? '')).'"'.(! empty(Meta::$data['builder_preview']) ? ' data-bt="'.e($reg[$type][0]).'"' : '').($css ? ' style="'.e($css).'"' : '').'><div class="bb-in">'.$inner.'</div></section>'."\n";
    }

    private static function btn(string $t, string $u, string $cls = 'btn'): string
    {
        return $t === '' ? '' : '<a class="'.$cls.'" href="'.e(self::href($u)).'">'.e($t).'</a>';
    }

    private static function h(string $t): string
    {
        return $t === '' ? '' : '<h2 class="bb-h">'.e($t).'</h2>';
    }

    private static function b_hero(array $p): string
    {
        $t = e($p['title']);
        if ($p['accent'] !== '' && str_contains($p['title'], $p['accent'])) $t = str_replace(e($p['accent']), '<em>'.e($p['accent']).'</em>', $t);
        $bg = $p['image'] ? '<img class="bb-bgimg" src="'.e(self::href($p['image'])).'" alt="">' : '';

        return $bg.($p['eyebrow'] ? '<p class="eyebrow lime">'.e($p['eyebrow']).'</p>' : '').'<h1 class="mega bb-mega">'.$t.'</h1>'.($p['text'] ? '<p class="lede">'.e($p['text']).'</p>' : '')
            .'<div class="bb-btns">'.self::btn($p['btn'], $p['url']).self::btn($p['btn2'], $p['url2'], 'btn btn-ghost').'</div>';
    }

    private static function b_text(array $p): string
    {
        return self::h($p['title']).'<div class="prose">'.$p['body'].'</div>';
    }

    private static function b_image_text(array $p): string
    {
        $img = $p['image'] ? '<div class="bb-img"><img src="'.e(self::href($p['image'])).'" alt="'.e($p['title']).'" loading="lazy"></div>' : '';
        $txt = '<div class="bb-txt">'.self::h($p['title']).'<div class="prose">'.nl2br(e($p['body'])).'</div>'.self::btn($p['btn'], $p['url']).'</div>';

        return '<div class="bb-split side-'.e($p['side']).'">'.$img.$txt.'</div>';
    }

    private static function b_products(array $p): string
    {
        $n = max(1, min(24, (int) $p['count']));
        $q = Catalog::query();
        match ($p['source']) {
            'new' => $q->orderByDesc('products.id'),
            'sale' => $q->whereColumn('compare_price', '>', 'price')->orderByDesc('products.id'),
            'category' => $q->where('category_id', (int) $p['category'])->orderByDesc('featured')->orderByDesc('sales'),
            default => $q->orderByDesc('featured')->orderByDesc('sales')->orderByDesc('products.id'),
        };
        $items = $q->limit($n)->get();
        if ($items->isEmpty()) return self::h($p['title']).'<p class="muted">No products to show yet.</p>';
        $cols = in_array((string) $p['cols'], ['2', '3', '4'], true) ? $p['cols'] : '4';
        $h = self::h($p['title']).'<div class="pgrid bb-grid cols-'.$cols.'">';
        foreach ($items as $pr) $h .= partial('partials/product_card', ['p' => $pr]);

        return $h.'</div>';
    }

    private static function b_categories(array $p): string
    {
        $h = self::h($p['title']).'<div class="bb-tiles">';
        foreach (Catalog::categories() as $c) {
            $h .= '<a class="bb-tile" href="'.e(url('shop/'.$c->slug)).'" style="--c:'.e($c->color).';--ink:'.e(Art::ink($c->color)).'"><b>'.e($c->name).'</b><span>'.e((string) $c->tagline).'</span></a>';
        }

        return $h.'</div>';
    }

    private static function b_banner(array $p): string
    {
        return '<div class="bb-banner"><div><h2 class="bb-h">'.e($p['title']).'</h2>'.($p['text'] ? '<p>'.e($p['text']).'</p>' : '').'</div>'.self::btn($p['btn'], $p['url']).'</div>';
    }

    private static function b_features(array $p): string
    {
        $h = self::h($p['title']).'<div class="bb-feat">';
        foreach (self::lines($p['items']) as $l) $h .= '<div><span class="ico">'.e($l[0] ?? '').'</span><b>'.e($l[1] ?? '').'</b><p>'.e($l[2] ?? '').'</p></div>';

        return $h.'</div>';
    }

    private static function b_testimonials(array $p): string
    {
        $h = self::h($p['title']).'<div class="bb-quotes">';
        foreach (self::lines($p['items']) as $l) $h .= '<figure><blockquote>“'.e($l[0] ?? '').'”</blockquote><figcaption>— '.e($l[1] ?? '').'</figcaption></figure>';

        return $h.'</div>';
    }

    private static function b_faq(array $p): string
    {
        $h = self::h($p['title']).'<div class="bb-faq">';
        $ld = [];
        foreach (self::lines($p['items']) as $l) {
            $h .= '<details><summary>'.e($l[0] ?? '').'</summary><p>'.e($l[1] ?? '').'</p></details>';
            $ld[] = ['@type' => 'Question', 'name' => $l[0] ?? '', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $l[1] ?? '']];
        }
        if ($ld) $h .= '<script type="application/ld+json">'.json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).'</script>';

        return $h.'</div>';
    }

    private static function b_countdown(array $p): string
    {
        $ts = strtotime((string) $p['date']);

        return self::h($p['title']).'<div class="bb-count" data-end="'.($ts ? $ts * 1000 : 0).'"><span data-u="d">0</span><span data-u="h">0</span><span data-u="m">0</span><span data-u="s">0</span></div>'.self::btn($p['btn'], $p['url']);
    }

    private static function b_video(array $p): string
    {
        $u = (string) $p['url'];
        $src = '';
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([\w-]{6,15})#', $u, $m)) $src = 'https://www.youtube-nocookie.com/embed/'.$m[1];
        elseif (preg_match('#vimeo\.com/(\d+)#', $u, $m)) $src = 'https://player.vimeo.com/video/'.$m[1];
        if ($src === '') return self::h($p['title']).'<p class="muted">Add a YouTube or Vimeo link.</p>';

        return self::h($p['title']).'<div class="bb-video"><iframe src="'.e($src).'" loading="lazy" allowfullscreen title="Video"></iframe></div>';
    }

    private static function b_newsletter(array $p): string
    {
        return '<div class="bb-news"><div>'.self::h($p['title']).'<p>'.e($p['text']).'</p></div><form data-news class="news-form">'.\App\Http\Controllers\NewsletterController::fields().'<input type="email" name="email" placeholder="you@example.com" required aria-label="Email"><button class="btn">'.e($p['btn'] ?: 'Subscribe').'</button><small class="sub-msg" hidden></small></form></div>';
    }

    private static function b_home_hero(array $p): string
    {
        return partial('partials/home_hero', ['props' => $p]);
    }

    private static function b_home_panels(array $p): string
    {
        return partial('partials/home_panels');
    }

    private static function b_home_trending(array $p): string
    {
        return partial('partials/home_trending', ['props' => $p]);
    }

    private static function b_home_mixit(array $p): string
    {
        return partial('partials/home_mixit', ['props' => $p]);
    }

    private static function b_gallery(array $p): string
    {
        $cols = in_array((string) $p['cols'], ['2', '3', '4'], true) ? $p['cols'] : '3';
        $h = self::h($p['title']).'<div class="bb-gal cols-'.$cols.'">';
        foreach (self::lines($p['items']) as $l) {
            if (($l[0] ?? '') === '') continue;
            $img = '<img src="'.e(self::href($l[0])).'" alt="'.e($l[1] ?? '').'" loading="lazy">'.(($l[1] ?? '') !== '' ? '<figcaption>'.e($l[1]).'</figcaption>' : '');
            $h .= '<figure>'.(($l[2] ?? '') !== '' ? '<a href="'.e(self::href($l[2])).'">'.$img.'</a>' : $img).'</figure>';
        }

        return $h.'</div>';
    }

    private static function b_logos(array $p): string
    {
        $h = self::h($p['title']).'<div class="bb-logos">';
        foreach (self::lines($p['items']) as $l) {
            $a = $l[0] ?? '';
            if ($a === '') continue;
            $inner = preg_match('#^(https?:)?//|^/#', $a) ? '<img src="'.e(self::href($a)).'" alt="" loading="lazy">' : '<span>'.e($a).'</span>';
            $h .= ($l[1] ?? '') !== '' ? '<a href="'.e(self::href($l[1])).'">'.$inner.'</a>' : '<div>'.$inner.'</div>';
        }

        return $h.'</div>';
    }

    private static function b_pricing(array $p): string
    {
        $h = self::h($p['title']).'<div class="bb-plans">';
        foreach (self::lines($p['items']) as $l) {
            $feat = array_filter(array_map('trim', explode(';', $l[2] ?? '')));
            $h .= '<div class="bb-plan'.((($l[5] ?? '') === 'featured') ? ' on' : '').'"><h3>'.e($l[0] ?? '').'</h3><div class="pr">'.e($l[1] ?? '').'</div><ul>'
                .implode('', array_map(fn ($f) => '<li>'.e($f).'</li>', $feat)).'</ul>'.self::btn($l[3] ?? '', $l[4] ?? '', (($l[5] ?? '') === 'featured') ? 'btn' : 'btn btn-ghost').'</div>';
        }

        return $h.'</div>';
    }

    private static function b_strip(array $p): string
    {
        return '<div class="bb-strip"><span>'.e($p['text']).'</span>'.($p['btn'] !== '' ? '<a href="'.e(self::href($p['url'])).'">'.e($p['btn']).' →</a>' : '').'</div>';
    }

    private static function b_spacer(array $p): string
    {
        return '<div style="height:'.max(4, min(400, (int) $p['h'])).'px"></div>';
    }

    private static function b_html(array $p): string
    {
        return (string) $p['code'];
    }
}
