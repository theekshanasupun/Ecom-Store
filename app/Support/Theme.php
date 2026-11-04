<?php

namespace App\Support;

/**
 * v3.0 storefront themes (dark = the original KIOSK look, light = editorial boutique look).
 * Admin -> Appearance -> Themes stores the light palette / fonts / roundness as settings; this class turns
 * them into the CSS variables, the no-flash boot script and the Google Fonts link.
 */
final class Theme
{
    public const MODES = ['auto' => 'Follow visitor\'s device', 'dark' => 'Dark', 'light' => 'Light'];

    /** key => [label, heading stack, body stack, google fonts query] */
    public const FONTS = [
        'fraunces' => ['Fraunces + DM Sans', "'Fraunces',Georgia,'Times New Roman',serif", "'DM Sans',system-ui,-apple-system,'Segoe UI',sans-serif", 'family=Fraunces:opsz,wght@9..144,400..800&family=DM+Sans:wght@400;500;600;700'],
        'playfair' => ['Playfair Display + Inter', "'Playfair Display',Georgia,serif", "'Inter',system-ui,-apple-system,'Segoe UI',sans-serif", 'family=Playfair+Display:wght@500..800&family=Inter:wght@400;500;600;700'],
        'cormorant' => ['Cormorant Garamond + Jost', "'Cormorant Garamond',Georgia,serif", "'Jost',system-ui,-apple-system,'Segoe UI',sans-serif", 'family=Cormorant+Garamond:wght@500;600;700&family=Jost:wght@400;500;600;700'],
        'jakarta' => ['Plus Jakarta Sans (all sans)', "'Plus Jakarta Sans',system-ui,-apple-system,'Segoe UI',sans-serif", "'Plus Jakarta Sans',system-ui,-apple-system,'Segoe UI',sans-serif", 'family=Plus+Jakarta+Sans:wght@400;500;600;700;800'],
    ];

    /** key => [label, card radius, button/input radius] */
    public const RADII = [
        'sharp' => ['Sharp', '4px', '3px'],
        'soft' => ['Soft', '14px', '10px'],
        'round' => ['Round', '22px', '16px'],
        'pill' => ['Pill', '26px', '99px'],
    ];

    public const DEFAULTS = ['bg' => '#faf7f2', 'surface' => '#ffffff', 'text' => '#1c1917'];

    public static function mode(): string
    {
        $m = (string) setting('theme_mode', 'auto');

        return isset(self::MODES[$m]) ? $m : 'auto';
    }

    public static function toggleOn(): bool
    {
        return setting('theme_toggle', '1') === '1';
    }

    public static function font(): string
    {
        $f = (string) setting('theme_light_font', 'fraunces');

        return isset(self::FONTS[$f]) ? $f : 'fraunces';
    }

    public static function radius(): string
    {
        $r = (string) setting('theme_light_radius', 'soft');

        return isset(self::RADII[$r]) ? $r : 'soft';
    }

    public static function hex(string $key, string $default): string
    {
        $v = (string) setting($key, $default);

        return preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : $default;
    }

    public static function fontsHref(?string $font = null): string
    {
        return 'https://fonts.googleapis.com/css2?' . self::FONTS[$font ?: self::font()][3] . '&display=swap';
    }

    /** Light-mode accent: the admin's pick, else the store accent darkened until it reads on the light background. */
    public static function lightAccent(): string
    {
        $c = self::hex('theme_light_accent', '');
        if ($c !== '') return $c;
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('accent')) ? strtolower(setting('accent')) : '#c6ff3d';
        $bg = self::hex('theme_light_bg', self::DEFAULTS['bg']);
        for ($t = 0; $t <= 0.9; $t += 0.05) {
            $c = self::mix($accent, '#000000', $t);
            if (self::contrast($c, $bg) >= 4.5) return $c;
        }

        return '#000000';
    }

    /** The light-theme variable block (and the dark-theme override hooks). */
    public static function css(): string
    {
        $bg = self::hex('theme_light_bg', self::DEFAULTS['bg']);
        $sf = self::hex('theme_light_surface', self::DEFAULTS['surface']);
        $tx = self::hex('theme_light_text', self::DEFAULTS['text']);
        $ac = self::lightAccent();
        [, $rc, $rb] = self::RADII[self::radius()];
        [, $fd, $fb] = self::FONTS[self::font()];
        $v = [
            'color-scheme' => 'light', '--bg' => $bg, '--card' => $sf, '--card2' => self::mix($bg, $tx, 0.04), '--well' => $sf,
            '--line' => self::mix($bg, $tx, 0.12), '--line2' => self::mix($bg, $tx, 0.22),
            '--tx' => $tx, '--tx2' => self::mix($tx, $bg, 0.22), '--mut' => self::mix($tx, $bg, 0.42), '--inv' => $bg,
            '--accent' => $ac, '--accent-ink' => Art::ink($ac), '--c' => $ac, '--ink' => Art::ink($ac),
            '--fd' => $fd, '--fb' => $fb, '--fm' => $fb, '--r' => $rc, '--rb' => $rb,
        ];
        $o = '';
        foreach ($v as $k => $val) $o .= $k . ':' . $val . ';';

        return 'html[data-theme=light]{' . $o . '}';
    }

    /** Runs before first paint: picks the theme (saved choice > admin default > device), loads light fonts when needed. */
    public static function bootScript(): string
    {
        $cfg = json_encode(['mode' => self::mode(), 'toggle' => self::toggleOn(), 'fonts' => self::fontsHref(), 'dark' => '#0b0b0d', 'light' => self::hex('theme_light_bg', self::DEFAULTS['bg'])], JSON_UNESCAPED_SLASHES);

        return '<script>(function(){var C=' . $cfg . ',d=document.documentElement,s=null;window.KIOSK_THEME=C;'
            . 'try{if(C.toggle)s=localStorage.getItem("kiosk_theme")}catch(e){}'
            . 'var v=(s==="light"||s==="dark")?s:C.mode;'
            . 'if(v==="auto")v=(window.matchMedia&&matchMedia("(prefers-color-scheme: light)").matches)?"light":"dark";'
            . 'd.setAttribute("data-theme",v);d.setAttribute("data-theme-mode",C.mode);'
            . 'var m=document.querySelector("meta[name=theme-color]");if(m)m.setAttribute("content",v==="light"?C.light:C.dark);'
            . 'if(v==="light"){var l=document.createElement("link");l.rel="stylesheet";l.id="kiosk-light-fonts";l.href=C.fonts;document.head.appendChild(l)}'
            . '})();</script>';
    }

    public static function mix(string $a, string $b, float $t): string
    {
        $x = self::rgb($a);
        $y = self::rgb($b);
        $r = [];
        foreach ([0, 1, 2] as $i) $r[] = (int) round($x[$i] + ($y[$i] - $x[$i]) * $t);

        return sprintf('#%02x%02x%02x', ...$r);
    }

    public static function contrast(string $a, string $b): float
    {
        $l = function (string $h): float {
            $c = array_map(fn ($n) => ($n /= 255) <= 0.03928 ? $n / 12.92 : (($n + 0.055) / 1.055) ** 2.4, self::rgb($h));

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$p, $q] = [$l($a), $l($b)];

        return (max($p, $q) + 0.05) / (min($p, $q) + 0.05);
    }

    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }
}
