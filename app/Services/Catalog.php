<?php
namespace App\Services;

use App\Core\Art;
use App\Core\Db;

final class Catalog
{
    public static function categories(bool $onlyActive = true): array
    {
        static $cache = [];
        $k = (int) $onlyActive;
        if (!isset($cache[$k])) {
            $cache[$k] = Db::all('SELECT * FROM categories' . ($onlyActive ? " WHERE status = 'active'" : '') . ' ORDER BY sort_order, id');
        }
        return $cache[$k];
    }

    public static function category(int $id): ?array
    {
        foreach (self::categories(false) as $c) {
            if ((int) $c['id'] === $id) return $c;
        }
        return null;
    }

    /** Product with hydrated extras. */
    public static function hydrate(array $p): array
    {
        $cat = self::category((int) $p['category_id']);
        $p['cat'] = $cat;
        $p['color'] = $cat['color'] ?? '#c6ff3d';
        $p['options_list'] = self::options($p);
        $p['gallery_list'] = $p['gallery'] ? (json_decode($p['gallery'], true) ?: []) : [];
        $p['tag_list'] = array_values(array_filter(array_map('trim', explode(',', (string) $p['tags']))));
        $p['in_stock'] = self::available($p) > 0;
        $p['instant'] = in_array($p['type'], ['key', 'account', 'file'], true);
        return $p;
    }

    public static function options(array $p): array
    {
        $raw = $p['options'] ?? '';
        if (!$raw) return [];
        $o = json_decode($raw, true);
        return is_array($o) ? $o : [];
    }

    /** Available units: pool size for keys/accounts, stock for physical. */
    public static function available(array $p): int
    {
        if (!empty($p['unlimited']) || in_array($p['type'], ['file', 'service'], true)) {
            return PHP_INT_MAX;
        }
        if (in_array($p['type'], ['key', 'account'], true)) {
            return (int) Db::val("SELECT COUNT(*) FROM product_keys WHERE product_id = ? AND status = 'available'", [(int) $p['id']]);
        }
        return max(0, (int) $p['stock']);
    }

    public static function find(string $slug): ?array
    {
        $p = Db::one("SELECT * FROM products WHERE slug = ? AND status = 'active'", [$slug]);
        return $p ? self::hydrate($p) : null;
    }

    public static function byId(int $id, bool $onlyActive = true): ?array
    {
        $p = Db::one('SELECT * FROM products WHERE id = ?' . ($onlyActive ? " AND status = 'active'" : ''), [$id]);
        return $p ? self::hydrate($p) : null;
    }

    public static function list(array $where = [], string $order = 'p.id DESC', int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT p.* FROM products p JOIN categories c ON c.id = p.category_id AND c.status = 'active' WHERE p.status = 'active'";
        $params = [];
        foreach ($where as $cond => $val) {
            $sql .= " AND $cond";
            if ($val !== null) $params[] = $val;
        }
        $sql .= " ORDER BY $order LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset;
        return array_map([self::class, 'hydrate'], Db::all($sql, $params));
    }

    public static function search(string $q, int $limit = 24): array
    {
        $like = '%' . $q . '%';
        $rows = Db::all(
            "SELECT p.* FROM products p JOIN categories c ON c.id = p.category_id AND c.status = 'active'
             WHERE p.status = 'active' AND (p.name LIKE ? OR p.tagline LIKE ? OR p.tags LIKE ? OR p.sku LIKE ?)
             ORDER BY p.sales DESC, p.id DESC LIMIT " . (int) $limit,
            [$like, $like, $like, $like]
        );
        return array_map([self::class, 'hydrate'], $rows);
    }

    /** Media markup: uploaded photo or vector art. */
    public static function media(array $p, bool $lazy = true): string
    {
        if (!empty($p['image'])) {
            return '<img src="' . e(upload_url($p['image'])) . '" alt="' . e($p['name']) . '"' . ($lazy ? ' loading="lazy"' : '') . '>';
        }
        $art = $p['art'] ?: match ($p['type'] ?? '') {
            'key' => 'key', 'account' => 'account', default => 'box',
        };
        $col = $p['art_color'] ?: ($p['color'] ?? '#c6ff3d');
        return Art::svg($art, $col, $p['name']);
    }

    public static function priceHtml(array $p, bool $short = true): string
    {
        $h = '<b>' . money((int) $p['price'], $short) . '</b>';
        if ((int) $p['compare_price'] > (int) $p['price']) {
            $h .= ' <s>' . money((int) $p['compare_price'], $short) . '</s>';
        }
        return $h;
    }

    public static function discountPct(array $p): int
    {
        return (int) $p['compare_price'] > (int) $p['price'] ? (int) round(100 - $p['price'] / $p['compare_price'] * 100) : 0;
    }

    public static function related(array $p, int $n = 4): array
    {
        return self::list(['p.category_id = ?' => (int) $p['category_id'], 'p.id <> ?' => (int) $p['id']], 'p.sales DESC, p.id DESC', $n);
    }
}
