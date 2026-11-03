<?php
namespace App\Services;

use App\Core\Db;

final class Coupons
{
    /** @return array{0:?array,1:?string} */
    public static function check(string $code, int $subtotal): array
    {
        $c = Db::one('SELECT * FROM coupons WHERE code = ?', [strtoupper(trim($code))]);
        if (!$c || $c['status'] !== 'active') {
            return [null, 'That code is not valid.'];
        }
        $now = now();
        if ($c['starts_at'] && $c['starts_at'] > $now) return [null, 'That code is not active yet.'];
        if ($c['ends_at'] && $c['ends_at'] < $now) return [null, 'That code has expired.'];
        if ((int) $c['max_uses'] > 0 && (int) $c['used'] >= (int) $c['max_uses']) return [null, 'That code has been fully used.'];
        if ((int) $c['min_total'] > $subtotal) return [null, 'Spend ' . money((int) $c['min_total']) . ' or more to use this code.'];
        return [$c, null];
    }

    public static function amount(array $c, int $subtotal): int
    {
        $d = $c['type'] === 'percent' ? (int) round($subtotal * min(100, (int) $c['value']) / 100) : (int) $c['value'];
        return max(0, min($d, $subtotal));
    }
}
