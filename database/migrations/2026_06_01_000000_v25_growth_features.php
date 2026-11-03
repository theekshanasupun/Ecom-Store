<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** v2.5 — advanced coupons, SEO/GEO fields, popups, page builder, email automations, social login. */
return new class extends Migration
{
    private function addCols(string $table, callable $cb): void
    {
        Schema::table($table, $cb);
    }

    public function up(): void
    {
        // ---- coupons ----
        foreach ([
            'scope' => fn (Blueprint $t) => $t->string('scope', 12)->default('cart'),          // cart | products | categories
            'product_ids' => fn (Blueprint $t) => $t->text('product_ids')->nullable(),
            'category_ids' => fn (Blueprint $t) => $t->text('category_ids')->nullable(),
            'exclude_sale' => fn (Blueprint $t) => $t->boolean('exclude_sale')->default(false),
            'per_user_limit' => fn (Blueprint $t) => $t->unsignedInteger('per_user_limit')->default(0),
            'first_order_only' => fn (Blueprint $t) => $t->boolean('first_order_only')->default(false),
            'free_shipping' => fn (Blueprint $t) => $t->boolean('free_shipping')->default(false),
            'max_discount' => fn (Blueprint $t) => $t->unsignedInteger('max_discount')->default(0),
            'min_qty' => fn (Blueprint $t) => $t->unsignedInteger('min_qty')->default(0),
            'auto_apply' => fn (Blueprint $t) => $t->boolean('auto_apply')->default(false),
            'note' => fn (Blueprint $t) => $t->string('note', 190)->nullable(),
        ] as $col => $def) {
            if (! Schema::hasColumn('coupons', $col)) $this->addCols('coupons', $def);
        }

        // ---- SEO fields ----
        foreach (['products', 'categories', 'pages'] as $tbl) {
            foreach (['seo_title' => 190, 'seo_description' => 320] as $col => $len) {
                if (! Schema::hasColumn($tbl, $col)) Schema::table($tbl, fn (Blueprint $t) => $t->string($col, $len)->nullable());
            }
            if (! Schema::hasColumn($tbl, 'seo_noindex')) Schema::table($tbl, fn (Blueprint $t) => $t->boolean('seo_noindex')->default(false));
        }
        if (! Schema::hasColumn('pages', 'builder')) Schema::table('pages', fn (Blueprint $t) => $t->longText('builder')->nullable());
        if (! Schema::hasColumn('pages', 'use_builder')) Schema::table('pages', fn (Blueprint $t) => $t->boolean('use_builder')->default(false));

        // ---- popups ----
        if (! Schema::hasTable('popups')) {
            Schema::create('popups', function (Blueprint $t) {
                $t->id();
                $t->string('name', 150);
                $t->string('status', 10)->default('draft');         // active | draft
                $t->string('layout', 12)->default('modal');         // modal | slide | bar | fullscreen
                $t->string('position', 16)->default('center');
                $t->string('title', 190)->nullable();
                $t->text('body')->nullable();
                $t->string('image')->nullable();
                $t->string('button_text', 60)->nullable();
                $t->string('button_url', 255)->nullable();
                $t->boolean('capture_email')->default(false);
                $t->string('coupon_code', 40)->nullable();
                $t->string('trigger', 12)->default('delay');        // delay | exit | scroll | click | load
                $t->unsignedInteger('trigger_value')->default(5);
                $t->string('frequency', 12)->default('once');       // always | session | once | days
                $t->unsignedInteger('frequency_days')->default(7);
                $t->string('devices', 10)->default('all');          // all | desktop | mobile
                $t->string('audience', 10)->default('all');         // all | guests | customers
                $t->string('pages', 10)->default('all');            // all | home | products | cart | custom
                $t->text('page_rules')->nullable();
                $t->string('bg', 9)->default('#16161b');
                $t->string('fg', 9)->default('#f3f1ec');
                $t->string('btn_bg', 9)->default('#c6ff3d');
                $t->string('btn_fg', 9)->default('#0b0b0d');
                $t->unsignedTinyInteger('overlay')->default(60);
                $t->unsignedSmallInteger('width')->default(480);
                $t->unsignedTinyInteger('radius')->default(22);
                $t->string('success_text', 190)->nullable();
                $t->timestamp('starts_at')->nullable();
                $t->timestamp('ends_at')->nullable();
                $t->unsignedInteger('views')->default(0);
                $t->unsignedInteger('conversions')->default(0);
                $t->timestamps();
            });
        }

        // ---- subscribers ----
        if (! Schema::hasTable('subscribers')) {
            Schema::create('subscribers', function (Blueprint $t) {
                $t->id();
                $t->string('email', 190)->unique();
                $t->string('name', 150)->nullable();
                $t->string('source', 40)->nullable();
                $t->string('status', 14)->default('subscribed');    // subscribed | unsubscribed
                $t->string('token', 40)->index();
                $t->timestamps();
            });
        }

        // ---- saved (abandoned) carts ----
        if (! Schema::hasTable('saved_carts')) {
            Schema::create('saved_carts', function (Blueprint $t) {
                $t->id();
                $t->string('token', 40)->unique();
                $t->string('session_id', 100)->nullable()->index();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->string('email', 190)->nullable()->index();
                $t->string('name', 150)->nullable();
                $t->longText('data');                                // json {lines, coupon}
                $t->unsignedInteger('items')->default(0);
                $t->unsignedInteger('total')->default(0);
                $t->string('status', 12)->default('open');          // open | recovered | ordered
                $t->unsignedTinyInteger('reminders')->default(0);
                $t->timestamp('last_reminder_at')->nullable();
                $t->timestamp('last_activity_at')->nullable()->index();
                $t->timestamp('recovered_at')->nullable();
                $t->unsignedBigInteger('order_id')->nullable();
                $t->timestamps();
            });
        }

        // ---- automations ----
        if (! Schema::hasTable('automations')) {
            Schema::create('automations', function (Blueprint $t) {
                $t->id();
                $t->string('key', 40)->unique();
                $t->string('name', 150);
                $t->string('trigger_label', 190)->nullable();
                $t->boolean('enabled')->default(false);
                $t->unsignedInteger('delay_hours')->default(1);
                $t->string('subject', 190);
                $t->text('body');
                $t->string('coupon_code', 40)->nullable();
                $t->unsignedInteger('sent')->default(0);
                $t->unsignedInteger('recovered')->default(0);
                $t->unsignedBigInteger('revenue')->default(0);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $t) {
                $t->id();
                $t->string('automation', 40)->index();
                $t->string('email', 190)->index();
                $t->string('ref', 60)->nullable();
                $t->string('subject', 190)->nullable();
                $t->boolean('ok')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->unique(['automation', 'email', 'ref']);
            });
        }

        // ---- social login ----
        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->string('provider', 20);
                $t->string('provider_id', 190);
                $t->string('email', 190)->nullable();
                $t->timestamps();
                $t->unique(['provider', 'provider_id']);
            });
        }

        // default automations (disabled until the owner switches them on)
        $now = now();
        foreach (\App\Support\Marketing::defaultAutomations() as $a) {
            if (! DB::table('automations')->where('key', $a['key'])->exists()) {
                DB::table('automations')->insert($a + ['created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        foreach (['social_accounts', 'email_logs', 'automations', 'saved_carts', 'subscribers', 'popups'] as $t) Schema::dropIfExists($t);
    }
};
