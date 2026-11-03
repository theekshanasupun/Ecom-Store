<?php
namespace App\Core;

/**
 * Vector product art – used whenever a product has no uploaded photo.
 * Keeps the demo (and any quick listing) looking designed, not empty.
 */
final class Art
{
    public const TYPES = [
        'robot' => 'Robot', 'stack' => 'Stacker', 'ball' => 'Ball', 'dice' => 'Cube',
        'tee' => 'T-shirt', 'hoodie' => 'Hoodie', 'pants' => 'Pants',
        'key' => 'Game key card', 'gift' => 'Gift card', 'box' => 'Box', 'account' => 'Account',
    ];
    private static int $n = 0;

    public static function svg(string $art, string $color = '#c6ff3d', string $title = ''): string
    {
        $id = 'a' . (++self::$n);
        $c = preg_match('/^#[0-9a-f]{3,8}$/i', $color) ? $color : '#c6ff3d';
        $shade = self::mix($c, '#000000', 0.45);
        $t = e($title);
        $o = '<svg class="art art-' . e($art) . '" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . $t . '" preserveAspectRatio="xMidYMid meet">';
        switch ($art) {
            case 'robot':
                $o .= '<line x1="100" y1="22" x2="100" y2="44" stroke="#f3f1ec" stroke-width="4"/><circle cx="100" cy="20" r="8" fill="#ffd60a"/>'
                    . '<rect x="52" y="44" width="96" height="86" rx="22" fill="#f3f1ec"/><circle cx="80" cy="82" r="10" fill="#0b0b0d"/><circle cx="120" cy="82" r="10" fill="#0b0b0d"/>'
                    . '<rect x="86" y="106" width="28" height="5" rx="2.5" fill="#0b0b0d"/><rect x="64" y="134" width="20" height="26" rx="10" fill="#f3f1ec"/><rect x="116" y="134" width="20" height="26" rx="10" fill="#f3f1ec"/>';
                break;
            case 'stack':
                $o .= '<rect x="82" y="26" width="36" height="34" rx="9" fill="#3ef0b0"/><circle cx="100" cy="42" r="3" fill="#0b0b0d"/>'
                    . '<rect x="68" y="64" width="64" height="40" rx="10" fill="#ffd60a"/><rect x="54" y="108" width="92" height="46" rx="12" fill="#ff5aa5"/>';
                break;
            case 'ball':
                $o .= '<circle cx="100" cy="100" r="68" fill="#f3f1ec"/><rect x="32" y="94" width="136" height="12" fill="#0b0b0d"/><circle cx="100" cy="100" r="22" fill="#ff5aa5" stroke="#0b0b0d" stroke-width="8"/>';
                break;
            case 'dice':
                $o .= '<g transform="rotate(14 100 100)"><rect x="40" y="40" width="120" height="120" rx="26" fill="#f3f1ec"/><circle cx="72" cy="72" r="9" fill="#3ef0b0"/><circle cx="128" cy="72" r="9" fill="#ff5aa5"/><circle cx="72" cy="128" r="9" fill="#ffd60a"/><circle cx="128" cy="128" r="9" fill="#0b0b0d"/></g>';
                break;
            case 'tee':
                $o .= '<path d="M62 34 L88 26 Q100 44 112 26 L138 34 L176 72 L152 96 L140 86 L140 168 L60 168 L60 86 L48 96 L24 72 Z" fill="' . $c . '"/>'
                    . '<path d="M88 26 Q100 44 112 26" fill="none" stroke="' . $shade . '" stroke-width="5" stroke-linecap="round" opacity=".55"/>';
                break;
            case 'hoodie':
                $o .= '<path d="M60 40 L86 28 Q100 52 114 28 L140 40 L180 118 L158 128 L140 96 L140 172 L60 172 L60 96 L42 128 L20 118 Z" fill="' . $c . '"/>'
                    . '<path d="M86 28 Q100 62 114 28" fill="none" stroke="' . $shade . '" stroke-width="6" opacity=".5"/><rect x="76" y="128" width="48" height="26" rx="8" fill="' . $shade . '" opacity=".35"/>'
                    . '<line x1="94" y1="52" x2="94" y2="78" stroke="' . $shade . '" stroke-width="3" opacity=".6"/><line x1="106" y1="52" x2="106" y2="78" stroke="' . $shade . '" stroke-width="3" opacity=".6"/>';
                break;
            case 'pants':
                $o .= '<path d="M62 26 H138 L148 176 H108 L100 78 L92 176 H52 Z" fill="' . $c . '"/><rect x="62" y="26" width="76" height="12" fill="' . $shade . '" opacity=".45"/><line x1="100" y1="38" x2="100" y2="78" stroke="' . $shade . '" stroke-width="3" opacity=".5"/>';
                break;
            case 'key':
            case 'gift':
            case 'account':
                $s = 'g' . $id;
                $o = '<svg class="art art-' . e($art) . ' art-fill" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . $t . '" preserveAspectRatio="xMidYMid slice">'
                    . '<defs><linearGradient id="' . $s . '" x1="0" y1="0" x2="0.6" y2="1"><stop offset="0" stop-color="' . $c . '"/><stop offset="1" stop-color="' . $shade . '"/></linearGradient></defs>'
                    . '<rect width="200" height="200" fill="url(#' . $s . ')"/>';
                $circle = $art === 'gift' ? '#8b7bff' : ($art === 'account' ? '#3ef0b0' : '#ffb020');
                $o .= '<circle cx="158" cy="28" r="46" fill="' . $circle . '"/>';
                $words = preg_split('/\s+/', trim($title)) ?: [];
                $lines = [];
                $cur = '';
                foreach ($words as $w) {
                    if ($cur !== '' && mb_strlen($cur . ' ' . $w) > 11) {
                        $lines[] = $cur;
                        $cur = $w;
                    } else {
                        $cur = trim($cur . ' ' . $w);
                    }
                }
                if ($cur !== '') $lines[] = $cur;
                $lines = array_slice($lines, -3);
                $y = 184 - (count($lines) - 1) * 17;
                foreach ($lines as $ln) {
                    $o .= '<text x="14" y="' . $y . '" font-family="Syne,Arial Black,sans-serif" font-weight="800" font-size="17" fill="#fff">' . e($ln) . '</text>';
                    $y += 17;
                }
                return $o . '</svg>';
            default:
                $o .= '<path d="M30 70 L100 40 L170 70 L170 150 L100 180 L30 150 Z" fill="' . $c . '"/><path d="M30 70 L100 100 L170 70 M100 100 V180" fill="none" stroke="' . $shade . '" stroke-width="5" opacity=".5"/>';
        }
        return $o . '</svg>';
    }

    private static function mix(string $hex, string $with, float $t): string
    {
        $a = self::rgb($hex);
        $b = self::rgb($with);
        $r = [];
        foreach ([0, 1, 2] as $i) {
            $r[] = (int) round($a[$i] + ($b[$i] - $a[$i]) * $t);
        }
        return sprintf('#%02x%02x%02x', ...$r);
    }

    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    /** Readable text color (black/white) for a hex background. */
    public static function ink(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#0b0b0d' : '#ffffff';
    }
}
