<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Base schema. Money is stored as integer minor units (cents). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('email', 190)->unique();
            $t->string('password');
            $t->string('role', 20)->default('customer');
            $t->string('phone', 40)->nullable();
            $t->string('status', 20)->default('active');
            $t->rememberToken();
            $t->timestamp('last_login_at')->nullable();
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->text('value')->nullable();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('slug', 150)->unique();
            $t->string('headline', 60)->nullable();
            $t->string('eyebrow', 160)->nullable();
            $t->string('tagline', 255)->nullable();
            $t->text('description')->nullable();
            $t->string('color', 9)->default('#c6ff3d');
            $t->integer('sort_order')->default(0);
            $t->string('status', 12)->default('active');
            $t->string('image')->nullable();
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('category_id')->default(0)->index();
            $t->string('name', 190);
            $t->string('slug', 220)->unique();
            $t->string('tagline', 255)->nullable();
            $t->text('description')->nullable();
            $t->string('type', 12)->default('physical');
            $t->unsignedInteger('price')->default(0);
            $t->unsignedInteger('compare_price')->default(0);
            $t->integer('stock')->default(0);
            $t->boolean('unlimited')->default(false);
            $t->string('sku', 80)->nullable();
            $t->string('image')->nullable();
            $t->text('gallery')->nullable();
            $t->string('art', 20)->nullable();
            $t->string('art_color', 9)->nullable();
            $t->string('badge', 30)->nullable();
            $t->string('tags')->nullable();
            $t->text('options')->nullable();
            $t->text('details')->nullable();
            $t->string('file_path')->nullable();
            $t->string('delivery_note')->nullable();
            $t->boolean('featured')->default(false);
            $t->string('status', 12)->default('active');
            $t->unsignedInteger('sales')->default(0);
            $t->timestamps();
        });

        Schema::create('product_keys', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id')->index();
            $t->text('payload');
            $t->string('status', 12)->default('available');
            $t->unsignedBigInteger('order_item_id')->nullable()->index();
            $t->timestamp('sold_at')->nullable();
            $t->timestamps();
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('type', 10)->default('percent');
            $t->unsignedInteger('value')->default(0);
            $t->unsignedInteger('min_total')->default(0);
            $t->unsignedInteger('max_uses')->default(0);
            $t->unsignedInteger('used')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->string('status', 10)->default('active');
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->string('token', 64);
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('email', 190)->index();
            $t->string('name', 150);
            $t->string('phone', 40)->nullable();
            $t->string('status', 16)->default('pending')->index();
            $t->string('payment_status', 12)->default('unpaid');
            $t->string('payment_method', 20)->nullable();
            $t->string('payment_ref', 190)->nullable();
            $t->unsignedInteger('subtotal')->default(0);
            $t->unsignedInteger('shipping')->default(0);
            $t->unsignedInteger('tax')->default(0);
            $t->unsignedInteger('discount')->default(0);
            $t->unsignedInteger('total')->default(0);
            $t->string('currency', 8)->default('USD');
            $t->string('coupon_code', 40)->nullable();
            $t->boolean('has_physical')->default(false);
            foreach (['ship_name' => 150, 'ship_address' => 255, 'ship_address2' => 255, 'ship_city' => 100, 'ship_state' => 100, 'ship_zip' => 20, 'ship_country' => 100, 'tracking' => 190] as $c => $len) {
                $t->string($c, $len)->nullable();
            }
            $t->text('notes')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->index();
            $t->unsignedBigInteger('product_id');
            $t->string('name', 190);
            $t->string('type', 12);
            $t->string('options')->nullable();
            $t->unsignedInteger('qty')->default(1);
            $t->unsignedInteger('price')->default(0);
            $t->unsignedInteger('total')->default(0);
            $t->string('art', 20)->nullable();
            $t->string('art_color', 9)->nullable();
            $t->string('image')->nullable();
            $t->string('color', 9)->nullable();
            $t->string('category', 120)->nullable();
            $t->string('file_path')->nullable();
        });

        Schema::create('order_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->index();
            $t->string('message', 500);
            $t->string('actor', 100)->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('title', 190);
            $t->string('slug', 190)->unique();
            $t->longText('body')->nullable();
            $t->string('status', 10)->default('published');
            $t->boolean('in_footer')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['pages', 'order_events', 'order_items', 'orders', 'coupons', 'product_keys', 'products', 'categories', 'settings', 'users'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
