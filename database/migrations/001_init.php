<?php
/** Base schema. Money is stored as integer minor units (cents). */
return function (App\Core\Migrator $m) {
    $m->sql('CREATE TABLE IF NOT EXISTS settings (id {PK}, k VARCHAR(100) NOT NULL UNIQUE, v TEXT) {OPTS}');

    $m->sql('CREATE TABLE IF NOT EXISTS users (
        id {PK}, name VARCHAR(150) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT \'customer\', phone VARCHAR(40), status VARCHAR(20) NOT NULL DEFAULT \'active\',
        last_login VARCHAR(19), created_at VARCHAR(19) NOT NULL
    ) {OPTS}');

    $m->sql('CREATE TABLE IF NOT EXISTS attempts (id {PK}, ip VARCHAR(45) NOT NULL, scope VARCHAR(30) NOT NULL, created_at VARCHAR(19) NOT NULL) {OPTS}');

    $m->sql('CREATE TABLE IF NOT EXISTS categories (
        id {PK}, name VARCHAR(120) NOT NULL, slug VARCHAR(150) NOT NULL UNIQUE, headline VARCHAR(60), eyebrow VARCHAR(160),
        tagline VARCHAR(255), description TEXT, color VARCHAR(9) NOT NULL DEFAULT \'#c6ff3d\', sort_order INT NOT NULL DEFAULT 0,
        status VARCHAR(12) NOT NULL DEFAULT \'active\', image VARCHAR(255), created_at VARCHAR(19) NOT NULL
    ) {OPTS}');

    $m->sql('CREATE TABLE IF NOT EXISTS products (
        id {PK}, category_id INT NOT NULL DEFAULT 0, name VARCHAR(190) NOT NULL, slug VARCHAR(220) NOT NULL UNIQUE,
        tagline VARCHAR(255), description TEXT, type VARCHAR(12) NOT NULL DEFAULT \'physical\',
        price INT NOT NULL DEFAULT 0, compare_price INT NOT NULL DEFAULT 0, stock INT NOT NULL DEFAULT 0, unlimited INT NOT NULL DEFAULT 0,
        sku VARCHAR(80), image VARCHAR(255), gallery TEXT, art VARCHAR(20), art_color VARCHAR(9), badge VARCHAR(30), tags VARCHAR(255),
        options TEXT, details TEXT, file_path VARCHAR(255), delivery_note VARCHAR(255), featured INT NOT NULL DEFAULT 0,
        status VARCHAR(12) NOT NULL DEFAULT \'active\', sales INT NOT NULL DEFAULT 0, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19)
    ) {OPTS}');
    $m->sql('CREATE INDEX idx_products_cat ON products (category_id, status)');

    $m->sql('CREATE TABLE IF NOT EXISTS product_keys (
        id {PK}, product_id INT NOT NULL, payload TEXT NOT NULL, status VARCHAR(12) NOT NULL DEFAULT \'available\',
        order_item_id INT, sold_at VARCHAR(19), created_at VARCHAR(19) NOT NULL
    ) {OPTS}');
    $m->sql('CREATE INDEX idx_keys_prod ON product_keys (product_id, status)');
    $m->sql('CREATE INDEX idx_keys_item ON product_keys (order_item_id)');

    $m->sql('CREATE TABLE IF NOT EXISTS orders (
        id {PK}, number VARCHAR(30) NOT NULL UNIQUE, token VARCHAR(64) NOT NULL, user_id INT, email VARCHAR(190) NOT NULL,
        name VARCHAR(150) NOT NULL, phone VARCHAR(40), status VARCHAR(16) NOT NULL DEFAULT \'pending\',
        payment_status VARCHAR(12) NOT NULL DEFAULT \'unpaid\', payment_method VARCHAR(20), payment_ref VARCHAR(190),
        subtotal INT NOT NULL DEFAULT 0, shipping INT NOT NULL DEFAULT 0, tax INT NOT NULL DEFAULT 0, discount INT NOT NULL DEFAULT 0,
        total INT NOT NULL DEFAULT 0, currency VARCHAR(8) NOT NULL DEFAULT \'USD\', coupon_code VARCHAR(40), has_physical INT NOT NULL DEFAULT 0,
        ship_name VARCHAR(150), ship_address VARCHAR(255), ship_address2 VARCHAR(255), ship_city VARCHAR(100), ship_state VARCHAR(100),
        ship_zip VARCHAR(20), ship_country VARCHAR(100), tracking VARCHAR(190), notes TEXT, ip VARCHAR(45),
        created_at VARCHAR(19) NOT NULL, paid_at VARCHAR(19)
    ) {OPTS}');
    $m->sql('CREATE INDEX idx_orders_user ON orders (user_id)');
    $m->sql('CREATE INDEX idx_orders_status ON orders (status, created_at)');

    $m->sql('CREATE TABLE IF NOT EXISTS order_items (
        id {PK}, order_id INT NOT NULL, product_id INT NOT NULL, name VARCHAR(190) NOT NULL, type VARCHAR(12) NOT NULL,
        options VARCHAR(255), qty INT NOT NULL DEFAULT 1, price INT NOT NULL DEFAULT 0, total INT NOT NULL DEFAULT 0,
        art VARCHAR(20), art_color VARCHAR(9), image VARCHAR(255), color VARCHAR(9), category VARCHAR(120), file_path VARCHAR(255)
    ) {OPTS}');
    $m->sql('CREATE INDEX idx_items_order ON order_items (order_id)');

    $m->sql('CREATE TABLE IF NOT EXISTS order_events (id {PK}, order_id INT NOT NULL, message VARCHAR(500) NOT NULL, actor VARCHAR(100), created_at VARCHAR(19) NOT NULL) {OPTS}');
    $m->sql('CREATE INDEX idx_events_order ON order_events (order_id)');

    $m->sql('CREATE TABLE IF NOT EXISTS coupons (
        id {PK}, code VARCHAR(40) NOT NULL UNIQUE, type VARCHAR(10) NOT NULL DEFAULT \'percent\', value INT NOT NULL DEFAULT 0,
        min_total INT NOT NULL DEFAULT 0, max_uses INT NOT NULL DEFAULT 0, used INT NOT NULL DEFAULT 0,
        starts_at VARCHAR(19), ends_at VARCHAR(19), status VARCHAR(10) NOT NULL DEFAULT \'active\', created_at VARCHAR(19) NOT NULL
    ) {OPTS}');

    $m->sql('CREATE TABLE IF NOT EXISTS pages (
        id {PK}, title VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE, body TEXT, status VARCHAR(10) NOT NULL DEFAULT \'published\',
        in_footer INT NOT NULL DEFAULT 1, created_at VARCHAR(19) NOT NULL
    ) {OPTS}');
};
