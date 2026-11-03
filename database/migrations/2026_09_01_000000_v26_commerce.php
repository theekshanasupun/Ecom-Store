<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v2.6 — variants, discount rules, upsell/cross-sell/bundles, store credit, gift cards, manual orders, shipping details. */
return new class extends Migration
{
    private function col(string $table, string $name, callable $def): void
    {
        if (! Schema::hasColumn($table, $name)) Schema::table($table, $def);
    }

    public function up(): void
    {
        // ---- users: store credit ----
        $this->col('users', 'credit', fn (Blueprint $t) => $t->unsignedBigInteger('credit')->default(0));

        // ---- products: advanced variants (JSON) ----
        $this->col('products', 'variants', fn (Blueprint $t) => $t->longText('variants')->nullable());

        // ---- order items: variant + gift card meta + original price ----
        $this->col('order_items', 'variant_key', fn (Blueprint $t) => $t->string('variant_key', 190)->nullable());
        $this->col('order_items', 'meta', fn (Blueprint $t) => $t->text('meta')->nullable());
        $this->col('order_items', 'orig_price', fn (Blueprint $t) => $t->unsignedInteger('orig_price')->default(0));

        // ---- orders: tenders, shipping details, source ----
        $this->col('orders', 'credit_used', fn (Blueprint $t) => $t->unsignedInteger('credit_used')->default(0));
        $this->col('orders', 'giftcard_used', fn (Blueprint $t) => $t->unsignedInteger('giftcard_used')->default(0));
        $this->col('orders', 'giftcard_code', fn (Blueprint $t) => $t->string('giftcard_code', 24)->nullable());
        $this->col('orders', 'carrier', fn (Blueprint $t) => $t->string('carrier', 80)->nullable());
        $this->col('orders', 'tracking_url', fn (Blueprint $t) => $t->string('tracking_url', 255)->nullable());
        $this->col('orders', 'ship_method', fn (Blueprint $t) => $t->string('ship_method', 80)->nullable());
        $this->col('orders', 'savings', fn (Blueprint $t) => $t->unsignedInteger('savings')->default(0));
        $this->col('orders', 'source', fn (Blueprint $t) => $t->string('source', 12)->default('web'));
        $this->col('orders', 'shipped_at', fn (Blueprint $t) => $t->timestamp('shipped_at')->nullable());

        // ---- store credit ledger ----
        if (! Schema::hasTable('credit_log')) {
            Schema::create('credit_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->bigInteger('amount');                       // signed cents
                $t->bigInteger('balance');                      // balance after this entry
                $t->string('reason', 190)->nullable();
                $t->unsignedBigInteger('order_id')->nullable();
                $t->string('actor', 100)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        // ---- gift cards ----
        if (! Schema::hasTable('gift_cards')) {
            Schema::create('gift_cards', function (Blueprint $t) {
                $t->id();
                $t->string('code', 24)->unique();
                $t->unsignedInteger('initial')->default(0);
                $t->unsignedInteger('balance')->default(0);
                $t->string('status', 10)->default('active');    // active | void | used | pending
                $t->timestamp('expires_at')->nullable();
                $t->string('design', 20)->default('classic');
                $t->string('recipient_name', 150)->nullable();
                $t->string('recipient_email', 190)->nullable();
                $t->string('sender_name', 150)->nullable();
                $t->text('message')->nullable();
                $t->timestamp('deliver_at')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->unsignedBigInteger('purchaser_user_id')->nullable();
                $t->unsignedBigInteger('order_id')->nullable()->index();
                $t->string('note', 190)->nullable();
                $t->string('created_via', 10)->default('admin'); // admin | purchase | import
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('gift_card_log')) {
            Schema::create('gift_card_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('gift_card_id')->index();
                $t->bigInteger('amount');
                $t->bigInteger('balance');
                $t->string('reason', 190)->nullable();
                $t->unsignedBigInteger('order_id')->nullable();
                $t->string('actor', 100)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        // ---- automatic discount rules (product / category / sitewide / quantity tiers) ----
        if (! Schema::hasTable('discount_rules')) {
            Schema::create('discount_rules', function (Blueprint $t) {
                $t->id();
                $t->string('name', 150);
                $t->string('status', 10)->default('active');     // active | draft
                $t->string('scope', 12)->default('all');         // all | products | categories
                $t->text('product_ids')->nullable();
                $t->text('category_ids')->nullable();
                $t->string('type', 10)->default('percent');      // percent | fixed
                $t->unsignedInteger('value')->default(0);
                $t->unsignedInteger('min_qty')->default(1);      // >1 = quantity tier
                $t->string('audience', 10)->default('all');      // all | guests | customers
                $t->boolean('skip_sale')->default(false);        // ignore items that already have a compare-at price
                $t->string('label', 40)->nullable();             // badge text, e.g. "Summer sale"
                $t->unsignedInteger('priority')->default(0);
                $t->timestamp('starts_at')->nullable();
                $t->timestamp('ends_at')->nullable();
                $t->timestamps();
            });
        }

        // ---- upsell / cross-sell / bundles ----
        if (! Schema::hasTable('offers')) {
            Schema::create('offers', function (Blueprint $t) {
                $t->id();
                $t->string('name', 150);
                $t->string('kind', 10)->default('crosssell');    // upsell | crosssell | bundle
                $t->string('status', 10)->default('active');
                $t->text('trigger_product_ids')->nullable();
                $t->text('trigger_category_ids')->nullable();
                $t->text('offer_product_ids')->nullable();
                $t->string('discount_type', 10)->default('none'); // none | percent | fixed
                $t->unsignedInteger('discount_value')->default(0);
                $t->string('placement', 40)->default('product,cart'); // csv of product | cart | checkout
                $t->string('headline', 120)->nullable();
                $t->string('subtext', 190)->nullable();
                $t->unsignedInteger('sort')->default(0);
                $t->unsignedInteger('views')->default(0);
                $t->unsignedInteger('adds')->default(0);
                $t->timestamps();
            });
        }

        // ---- housekeeping: keep only the 3 most recent updates in the history ----
        try {
            $row = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'update_history')->first();
            if ($row) {
                $h = json_decode((string) $row->value, true);
                if (is_array($h) && count($h) > 3) {
                    \Illuminate\Support\Facades\DB::table('settings')->where('key', 'update_history')->update(['value' => json_encode(array_slice($h, 0, 3))]);
                }
            }
            $log = storage_path('logs/updates.log');
            if (is_file($log)) {
                $lines = array_values(array_filter(file($log, FILE_IGNORE_NEW_LINES) ?: []));
                if (count($lines) > 3) file_put_contents($log, implode("\n", array_slice($lines, -3))."\n");
            }
            $zips = glob(storage_path('app/backups/pre-update-*.zip')) ?: [];
            usort($zips, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            foreach (array_slice($zips, 3) as $z) @unlink($z);
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        foreach (['offers', 'discount_rules', 'gift_card_log', 'gift_cards', 'credit_log'] as $t) Schema::dropIfExists($t);
    }
};
