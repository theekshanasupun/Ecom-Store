<?php
use App\Core\Crypto;
use App\Core\Db;

/** Demo content that mirrors the store concept: Toys, Clothing and Digital keys. */
return function (): void {
    $t = now();
    $cat = [];
    foreach ([
        ['Toys', 'toys', 'TOYS', 'DEPARTMENT 01 · SQUISHY, STACKABLE, BEEPY', 'Picked by people who still play. Choose who it\'s for on the right and the shelf reshuffles.', '#ff5aa5', 1],
        ['Clothing', 'clothing', 'WEAR', 'DEPARTMENT 02 · HEAVYWEIGHT, SMALL RUNS', 'Boxy, heavy, made in small batches. Pick a colour and a size and the piece changes in front of you.', '#ff7a2f', 2],
        ['Digital keys', 'keys', 'KEYS', 'DEPARTMENT 03 · DELIVERED IN SECONDS', 'Games, software and gift cards. Pay, and the key lands on screen before you blink.', '#8b7bff', 3],
    ] as [$n, $s, $h, $e, $tg, $c, $o]) {
        $cat[$s] = Db::insert('categories', ['name' => $n, 'slug' => $s, 'headline' => $h, 'eyebrow' => $e, 'tagline' => $tg, 'description' => $tg, 'color' => $c, 'sort_order' => $o, 'status' => 'active', 'created_at' => $t]);
    }

    $opt = fn(array $a) => json_encode($a, JSON_UNESCAPED_UNICODE);
    $sizes = [['name' => 'Size', 'values' => ['XS', 'S', 'M', 'L', 'XL']]];
    $tee = fn(array $colors) => $opt([['name' => 'Colour', 'values' => $colors]] + [1 => $sizes[0]]);

    // [cat, name, tagline, type, price, compare, stock, art, color, badge, tags, options, featured, sales, desc]
    $products = [
        ['toys', 'Giggle Bot', 'Soft beeping robot', 'physical', 2900, 0, 40, 'robot', '', 'AGES 1-3', 'Ages 1-3', '', 1, 120, 'A squishy little robot that beeps and giggles when you hug it. Machine washable, battery-free and safe from the first birthday.'],
        ['toys', 'Stack Pals', 'Soft stacking friends', 'physical', 2400, 0, 35, 'stack', '', 'AGES 1-3', 'Ages 1-3', '', 0, 90, 'Three soft blocks that stack, squeeze and rattle. Wipe clean.'],
        ['toys', 'Chomp Ball', 'Bouncy, squeaky, washable', 'physical', 1800, 0, 60, 'ball', '', 'AGES 1-3', 'Ages 1-3', '', 0, 140, 'A ball with a squeak. Bounces higher than it should.'],
        ['toys', 'Beep Bot', 'Build it, then program it', 'physical', 4800, 0, 22, 'robot', '', 'AGES 4-7', 'Ages 4-7', '', 0, 75, 'A build-it-yourself robot with tap-to-program buttons. No screens.'],
        ['toys', 'Rocket Racer', 'Pull back and launch', 'physical', 3600, 0, 18, 'ball', '', 'AGES 4-7', 'Ages 4-7', '', 0, 50, 'Pull back, let go, watch it go. Survives stairs.'],
        ['toys', 'Synth Cube', 'Tap, loop and remix', 'physical', 6400, 0, 14, 'dice', '', 'AGES 8-12', 'Ages 8-12', '', 0, 44, 'A pocket synth cube with six pads and a looper. Remix everything.'],
        ['toys', 'Mega Bricks', 'A very big build', 'physical', 7200, 0, 9, 'stack', '', 'AGES 8-12', 'Ages 8-12', '', 0, 31, 'Over 900 bricks, one very big build.'],
        ['toys', 'Zen Blocks', 'Desk toy for grown-ups', 'physical', 3900, 0, 25, 'dice', '', 'GROWN-UPS', 'Grown-ups', '', 0, 60, 'Satisfying, silent, desk-friendly clicky blocks.'],

        ['clothing', 'Boxy Tee', 'Heavyweight · 4 colours', 'physical', 6800, 0, 80, 'tee', '#f3f1ec', 'NEW', 'Tees', $opt([['name' => 'Colour', 'values' => ['Bone', 'Black', 'Moss', 'Sky', 'Tomato']], $sizes[0]]), 1, 210, 'A 280gsm boxy tee, garment dyed, made in small runs. Cut wide with a dropped shoulder.'],
        ['clothing', 'Heavy Hoodie', 'Brushed fleece', 'physical', 14800, 0, 30, 'hoodie', '#e4dcc8', 'LIMITED', 'Hoodies', $opt($sizes), 0, 66, '480gsm brushed fleece. Double-lined hood. Built to last a decade.'],
        ['clothing', 'Cargo Pant', 'Relaxed, washed', 'physical', 12800, 0, 28, 'pants', '#7f9460', 'NEW', 'Bottoms', $opt($sizes), 0, 42, 'Relaxed cargo with deep pockets and a washed finish.'],
        ['clothing', 'Work Tee', 'Garment dyed', 'physical', 7200, 0, 3, 'tee', '#8db7ff', 'LAST FEW', 'Tees', $opt($sizes), 0, 88, 'Pocketless and plain. Gets better every wash.'],
        ['clothing', 'Zip Hoodie', 'Heavy, boxy', 'physical', 15800, 0, 20, 'hoodie', '#ff7a2f', 'NEW', 'Hoodies', $opt($sizes), 0, 35, 'Full zip, heavy and boxy.'],
        ['clothing', 'Sweat Short', 'Mid-length', 'physical', 7800, 0, 26, 'pants', '#e4dcc8', 'NEW', 'Bottoms', $opt($sizes), 0, 29, 'Mid-length sweat shorts in heavy loopback.'],
        ['clothing', 'Long Sleeve', 'Dropped shoulder', 'physical', 8800, 0, 33, 'tee', '#4b5b3d', 'NEW', 'Tees', $opt($sizes), 0, 38, 'Long sleeve with a dropped shoulder.'],
        ['clothing', 'Fleece Pant', 'Tapered', 'physical', 11800, 0, 12, 'pants', '#8db7ff', 'LIMITED', 'Bottoms', $opt($sizes), 0, 19, 'Tapered fleece pants. Soft as it gets.'],

        ['keys', 'Starfall Tactics', 'PC · Global key', 'key', 1999, 3999, 0, 'key', '#7a62ff', '-50%', 'PC', $opt([['name' => 'Platform', 'values' => ['PC']]]), 1, 310, 'A tactical space strategy game. Activate on the platform of your choice with the key we show you right after payment.'],
        ['keys', 'Neon Drift', 'PC · EU key', 'key', 1449, 2499, 0, 'key', '#10c9a8', '-42%', 'PC', '', 0, 150, 'Arcade racing at the speed of light.'],
        ['keys', 'Iron Tide', 'Console · Global key', 'key', 2999, 5999, 0, 'key', '#e05a2b', '-50%', 'Console', '', 0, 98, 'Naval warfare on a grand scale.'],
        ['keys', 'Pocket Kingdoms', 'Mobile · Global key', 'key', 499, 999, 0, 'key', '#e0a010', '-50%', 'Mobile', '', 0, 77, 'Build your kingdom in your pocket.'],
        ['keys', 'Last Signal', 'PC · Global key', 'key', 2200, 3499, 0, 'key', '#3d6bff', '-37%', 'PC', '', 0, 64, 'A survival mystery in a dying radio tower.'],
        ['keys', 'Grid Runner', 'Console · EU key', 'key', 1749, 2999, 0, 'key', '#d6335f', '-42%', 'Console', '', 0, 49, 'Run the grid. Beat the clock.'],
        ['keys', 'Mythwood', 'Mobile · Global key', 'key', 649, 1299, 0, 'key', '#35b559', '-50%', 'Mobile', '', 0, 58, 'A cosy adventure in a living forest.'],
        ['keys', 'Gift Card 25', 'Gift card · Instant', 'key', 2500, 0, 0, 'gift', '#4a4a66', 'Gift', 'Gift cards', '', 0, 120, 'A 25 credit gift card. Delivered instantly by email.'],
    ];
    $n = 0;
    foreach ($products as [$c, $name, $tag, $type, $price, $cmp, $stock, $art, $col, $badge, $tags, $opts, $feat, $sales, $desc]) {
        $id = Db::insert('products', [
            'category_id' => $cat[$c], 'name' => $name, 'slug' => unique_slug('products', $name), 'tagline' => $tag, 'description' => $desc,
            'type' => $type, 'price' => $price, 'compare_price' => $cmp, 'stock' => $stock, 'unlimited' => 0,
            'sku' => strtoupper(substr($c, 0, 2)) . '-' . str_pad((string) (++$n), 3, '0', STR_PAD_LEFT), 'art' => $art, 'art_color' => $col,
            'badge' => $badge, 'tags' => $tags, 'options' => $opts, 'featured' => $feat, 'status' => 'active', 'sales' => $sales,
            'details' => $type === 'key' ? "Instant delivery on screen + email\nRedeem on the official platform\nRegion as listed" : "Free shipping over " . money((int) setting('free_shipping_min', 7500)) . "\nShips in 2 days\n30-day returns",
            'delivery_note' => $type === 'key' ? 'Instant · key on screen + email' : '', 'created_at' => $t, 'updated_at' => $t,
        ]);
        if ($type === 'key') {
            $count = $name === 'Gift Card 25' ? 6 : 8;
            for ($i = 0; $i < $count; $i++) {
                $key = strtoupper(implode('-', [bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2))]));
                Db::insert('product_keys', ['product_id' => $id, 'payload' => Crypto::encrypt($key), 'status' => 'available', 'created_at' => $t]);
            }
        }
    }

    Db::insert('coupons', ['code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'min_total' => 0, 'max_uses' => 0, 'status' => 'active', 'created_at' => $t]);

    foreach ([
        ['About', 'about', "<p>We started this store because we wanted one cart for everything: a toy, a tee and a game key, paid once.</p><p>Edit this page from Admin → Pages.</p>"],
        ['Shipping & returns', 'shipping-returns', "<h2>Shipping</h2><p>Physical orders ship within 2 business days. Free shipping over the threshold shown in your cart.</p><h2>Returns</h2><p>30-day returns on unworn, unused physical items. Digital keys are non-refundable once delivered, unless faulty.</p>"],
        ['Terms', 'terms', '<p>Add your terms of service here.</p>'],
        ['Privacy', 'privacy', '<p>Add your privacy policy here.</p>'],
    ] as [$title, $slug, $body]) {
        Db::insert('pages', ['title' => $title, 'slug' => $slug, 'body' => $body, 'status' => 'published', 'in_footer' => 1, 'created_at' => $t]);
    }
};
