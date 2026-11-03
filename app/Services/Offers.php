<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Product;

/**
 * Upsells, cross-sells and bundles.
 *  - bundle    : a fixed set of products; when every product is in the cart the bundle discount applies automatically.
 *  - upsell    : "upgrade / better version" suggestions for a trigger product.
 *  - crosssell : "frequently bought together / you may also like" suggestions.
 * upsell/cross-sell offers may carry a discount that applies to the offered product while a trigger item is in the cart.
 */
final class Offers
{
    private static ?array $all = null;

    public static function all(): array
    {
        if (self::$all === null) {
            try {
                self::$all = Offer::where('status', 'active')->orderBy('sort')->orderBy('id')->get()->all();
            } catch (\Throwable) {
                self::$all = [];
            }
        }

        return self::$all;
    }

    public static function forget(): void
    {
        self::$all = null;
    }

    /** @param int[] $ids */
    private static function products(array $ids, array $exclude = []): array
    {
        $ids = array_values(array_diff($ids, $exclude));
        if (! $ids) return [];
        $map = Catalog::query()->whereIn('products.id', $ids)->get()->keyBy('id');
        $out = [];
        foreach ($ids as $id) if (isset($map[$id]) && $map[$id]->available() > 0) $out[] = $map[$id];

        return $out;
    }

    private static function triggered(Offer $o, array $productIds, array $categoryIds): bool
    {
        $tp = $o->triggerProducts();
        $tc = $o->triggerCategories();
        if (! $tp && ! $tc) return true; // no trigger = show everywhere
        return (bool) array_intersect($tp, $productIds) || (bool) array_intersect($tc, $categoryIds);
    }

    /** Offer discount on one unit (cents) for display. */
    public static function priceWith(Offer $o, int $price): int
    {
        if ($o->discount_type === 'percent') return (int) round($price * (100 - min(100, (int) $o->discount_value)) / 100);
        if ($o->discount_type === 'fixed') return max(0, $price - (int) $o->discount_value);

        return $price;
    }

    /**
     * Offers to show for a placement.
     * @return array<int,array{offer:Offer,products:Product[]}>
     */
    public static function forPlacement(string $placement, array $cartPids, array $cartCats, ?Product $current = null): array
    {
        $out = [];
        $pids = $current ? [$current->id] : $cartPids;
        $cats = $current ? [(int) $current->category_id] : $cartCats;
        foreach (self::all() as $o) {
            if (! in_array($placement, $o->placements(), true)) continue;
            if ($o->kind === 'bundle') {
                $set = $o->offerProducts();
                if ($current && ! in_array($current->id, $set, true)) continue;
                if (! $current && ! array_intersect($set, $pids)) continue;
                $prods = self::products($set);
                if (count($prods) < 2 || count($prods) < count($set)) continue;
                if (! $current && count(array_intersect($set, $pids)) === count($set)) continue; // already complete in the cart
            } else {
                if (! self::triggered($o, $pids, $cats)) continue;
                $prods = self::products($o->offerProducts(), $pids);
                if (! $prods) continue;
            }
            $out[] = ['offer' => $o, 'products' => $prods];
        }

        return $out;
    }

    /** Count an impression once per offer per request batch. */
    public static function seen(array $shown): void
    {
        $ids = array_map(fn ($x) => $x['offer']->id, $shown);
        if ($ids) {
            try { Offer::whereIn('id', $ids)->increment('views'); } catch (\Throwable) {}
        }
    }

    /**
     * Cart-level discounts from bundles and discounted upsells.
     * @param array $lines hydrated cart lines (see Cart::summary)
     * @return array{total:int,rows:array<int,array{label:string,amount:int}>}
     */
    public static function cartDiscounts(array $lines): array
    {
        $rows = [];
        $live = array_values(array_filter($lines, fn ($l) => ($l['qty'] ?? 0) > 0 && empty($l['gc'])));
        if (! $live) return ['total' => 0, 'rows' => []];
        $byPid = [];
        $pids = [];
        $cats = [];
        foreach ($live as $l) {
            $pid = (int) $l['p']->id;
            $byPid[$pid][] = $l;
            $pids[] = $pid;
            $cats[] = (int) $l['p']->category_id;
        }
        foreach (self::all() as $o) {
            if ($o->discount_type === 'none' || (int) $o->discount_value <= 0) continue;
            $set = $o->offerProducts();
            if (! $set) continue;
            if ($o->kind === 'bundle') {
                if (count($set) < 2) continue;
                $sets = PHP_INT_MAX;
                $unit = 0;
                foreach ($set as $pid) {
                    if (empty($byPid[$pid])) { $sets = 0; break; }
                    $sets = min($sets, array_sum(array_column($byPid[$pid], 'qty')));
                    $unit += (int) $byPid[$pid][0]['unit'];
                }
                if ($sets < 1 || $unit <= 0) continue;
                $per = $o->discount_type === 'percent' ? (int) round($unit * min(100, (int) $o->discount_value) / 100) : min($unit, (int) $o->discount_value);
                $amt = $per * $sets;
                if ($amt > 0) $rows[] = ['label' => $o->headline ?: $o->name, 'amount' => $amt];
            } else {
                if (! self::triggered($o, $pids, $cats) || (! $o->triggerProducts() && ! $o->triggerCategories())) continue; // untargeted offers never auto-discount
                $amt = 0;
                foreach ($set as $pid) {
                    if (in_array($pid, $o->triggerProducts(), true)) continue;
                    foreach ($byPid[$pid] ?? [] as $l) {
                        $unit = (int) $l['unit'];
                        $amt += ($unit - self::priceWith($o, $unit)) * (int) $l['qty'];
                    }
                }
                if ($amt > 0) $rows[] = ['label' => $o->headline ?: $o->name, 'amount' => $amt];
            }
        }
        $total = array_sum(array_column($rows, 'amount'));

        return ['total' => $total, 'rows' => $rows];
    }
}
