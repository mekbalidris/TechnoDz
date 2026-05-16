# Design Document

## Overview

Nexus Shop is implemented as a small, procedural PHP application running on XAMPP (Apache + MySQL + PHP). There is no framework and no Composer; every file is a plain `.php` script that uses shared `include`/`require_once` files for the database connection, headers, footers, and small helper functions. The site is split into two areas:

- A public **Shop** under the project root (`/`) — product listing, search, product detail, cart, checkout, login, register.
- An **Admin Panel** under `/admin/` — admin login, dashboard, product CRUD, category management.

Data lives in a single MySQL database. Every query uses prepared statements (mysqli with `prepare`/`bind_param`/`execute`). User passwords are stored with `password_hash` (PHP's bcrypt) and verified with `password_verify`. Sessions and cookies are used as described in the requirements: PHP sessions for the active visit, a cookie for guest cart persistence between browser sessions, and the database for logged-in users.

Visual style is intentionally simple: a single `assets/css/style.css` stylesheet with a white background, basic spacing, and lightly styled cards, tables, and forms. JavaScript is minimal — used only for small UX touches (a confirm dialog before delete, the search form auto-submit on category change).

## Architecture

The application is a classic LAMP/XAMPP three-tier setup, kept deliberately flat for a student-level codebase:

```
Browser (HTML/CSS + tiny JS)
        │  HTTP (GET/POST)
        ▼
Apache  →  PHP scripts (procedural)
                │
                ├── includes/  (shared helpers: db, auth, cart, header/footer)
                │
                ▼
            MySQL (nexus_shop DB, prepared statements only)
```

Key architectural choices:

- **No framework, no Composer.** Each page is a standalone PHP script that pulls in shared logic via `require_once`. Routing is done by Apache mapping URLs straight to files (`/cart.php`, `/admin/products.php`, etc.).
- **Two logical surfaces, one codebase.** Public pages live at the project root; admin pages live under `/admin/`. They share the database, helpers, and stylesheet, but each surface has its own header/footer include and its own session key.
- **Procedural request flow.** Every page follows the same shape: include shared files → check auth → handle POST (if any) → run prepared SELECTs → render HTML. There is no controller layer or templating engine.
- **Three-tier cart storage.** Session is the working copy during a request; cookie persists guest carts between visits; database persists user carts across devices. `cart_load()` rehydrates the session from the right tier on every request.
- **Strict separation of user vs admin sessions.** They live in the same `PHPSESSID` but under different keys (`user_id` vs `admin_id`), so neither can elevate into the other.

## Project Structure

The whole project lives under the existing XAMPP project folder `c:\xampp\htdocs\nexus_shop\`. Files are flat where possible to keep navigation simple for a student-level codebase.

```
nexus_shop/
├── index.php                  # Shop landing / product listing (with search + category filter)
├── product.php                # Product detail page (?id=...)
├── cart.php                   # View/update/remove cart lines
├── cart_action.php            # POST handler: add / update_qty / remove
├── checkout.php               # Place order (login required)
├── order_confirm.php          # Confirmation page (?order_id=...)
├── login.php                  # User login
├── register.php               # User registration
├── logout.php                 # Destroy user session
│
├── includes/
│   ├── config.php             # DB credentials + base URL constants
│   ├── db.php                 # Opens mysqli connection ($conn)
│   ├── auth.php               # Helpers: current_user(), require_user(), require_admin(), is_admin()
│   ├── cart.php               # Cart helpers (session/cookie/DB read+write, merge on login)
│   ├── helpers.php            # h() escaper, product_image_url(), money(), redirect()
│   ├── header.php             # Public site header (logo + nav + search hook)
│   └── footer.php             # Public site footer
│
├── admin/
│   ├── login.php              # Admin login
│   ├── logout.php             # Destroy admin session
│   ├── index.php              # Admin dashboard (counts, links)
│   ├── products.php           # Product list (table)
│   ├── product_add.php        # Add product (form + handler)
│   ├── product_edit.php       # Edit product (?id=...) (form + handler)
│   ├── product_delete.php     # Delete product (POST id)
│   ├── categories.php         # List + add + delete categories
│   └── includes/
│       ├── admin_header.php   # Admin header + nav
│       └── admin_footer.php   # Admin footer
│
├── assets/
│   ├── css/
│   │   └── style.css          # Single stylesheet for both public + admin
│   ├── js/
│   │   └── app.js             # Tiny JS (delete confirm, filter form helpers)
│   └── images/
│       └── products/          # Existing product PNGs + default.png (already present)
│
└── sql/
    ├── schema.sql             # CREATE TABLE statements
    └── seed.sql               # INSERT categories + 10 products mapped to existing images
```

The `includes/` folder is the shared layer. Every page starts with the same boilerplate:

```php
<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';
?>
```

Admin pages do the same but `require_admin()` immediately after, so an unauthenticated visitor cannot reach any admin page other than `/admin/login.php`.

## Components and Interfaces

The system has a small number of clearly named components, all implemented as plain PHP files. Each component has a thin "interface" in the form of a few exported helper functions that other pages call.

### Public page components

| Component | Entry file | POST actions it accepts | Reads from | Writes to |
|-----------|-----------|--------------------------|-----------|-----------|
| Listing | `index.php` | — (GET only with `q`, `category_id`) | `products`, `categories` | — |
| Product detail | `product.php?id=N` | — | `products`, `categories` | — |
| Cart view | `cart.php` | — | session/cookie/`cart_items` | — |
| Cart action | `cart_action.php` | `action=add|update|remove`, `product_id`, `quantity` | `products` | session, cookie, `cart_items` |
| Checkout | `checkout.php` | `place_order` | session/`cart_items`, `products` | `orders`, `order_items`, clears cart |
| Order confirmation | `order_confirm.php?order_id=N` | — | `orders`, `order_items` | — |
| Login | `login.php` | `email`, `password` | `users` | session, runs cart merge |
| Register | `register.php` | `username`, `email`, `password` | `users` | `users` |
| Logout | `logout.php` | (GET) | session | clears session + cookie cart |

### Admin page components

| Component | Entry file | POST actions | Reads from | Writes to |
|-----------|-----------|---------------|-----------|-----------|
| Admin login | `admin/login.php` | `username`, `password` | `admins` | session |
| Admin logout | `admin/logout.php` | (GET) | session | clears `admin_id` |
| Dashboard | `admin/index.php` | — | `products`, `categories`, `orders` | — |
| Products list | `admin/products.php` | — | `products`, `categories` | — |
| Product add | `admin/product_add.php` | `name`, `description`, `price`, `category_id`, `image` (upload) | `categories` | `products`, image folder |
| Product edit | `admin/product_edit.php?id=N` | same as add (+ optional new image) | `products`, `categories` | `products`, image folder |
| Product delete | `admin/product_delete.php` | `id` | — | `products` |
| Categories | `admin/categories.php` | `add_name`, `delete_id` | `categories` | `categories`, indirectly `products` (FK SET NULL) |

### Shared interfaces (functions exported by `includes/`)

```php
// includes/auth.php
current_user_id(): int
is_logged_in(): bool
require_user(): void          // redirects to /login.php if not logged in
current_admin_id(): int
is_admin(): bool
require_admin(): void         // redirects to /admin/login.php if not admin

// includes/cart.php
cart_load(): void             // hydrates $_SESSION['cart'] from DB or cookie
cart_add(int $product_id, int $delta = 1): void
cart_set_qty(int $product_id, int $qty): void
cart_remove(int $product_id): void
cart_persist(): void          // writes session cart to DB (user) or cookie (guest)
cart_load_from_cookie(): array
cart_load_from_db(int $user_id): array
cart_save_to_db(int $user_id, array $cart): void
cart_clear_cookie(): void
cart_merge_on_login(int $user_id): void

// includes/helpers.php
h(string $s): string                      // HTML escape
money(float $n): string                   // "$1,234.56"
redirect(string $path): void
product_image_url(string $filename): string
```

These are the only entry points that page files call into. Pages never touch `$_SESSION` directly for cart state — they always go through the cart helpers.

## Data Models

The application uses six MySQL tables plus two ephemeral PHP-level structures (the session cart and the cookie cart). All persistent storage is in MySQL; the session and cookie just buffer the in-flight cart.

### PHP-level data shapes

```php
// $_SESSION['cart']  — used everywhere during a request
// Map of product_id => quantity. Both keys and values are positive integers.
[
  3 => 1,
  7 => 2,
]

// nexus_cart cookie — JSON-encoded version of the same shape
'{"3":1,"7":2}'

// Product row (as fetched from DB and passed to templates)
[
  'id'          => int,
  'name'        => string,
  'description' => string,
  'price'       => string (decimal),  // mysqli returns DECIMAL as string
  'image'       => string,            // filename only, e.g. 'rtx5090.png'
  'category_id' => int|null,
  'category_name' => string|null,     // joined when needed
]

// Order row + items (used on the confirmation page)
[
  'id'         => int,
  'user_id'    => int,
  'total'      => string,
  'created_at' => string,
  'items' => [
    ['product_id' => int, 'product_name' => string,
     'unit_price' => string, 'quantity' => int],
    ...
  ],
]
```

### MySQL tables

The full DDL is in the next section (`Database Schema`). At a glance:

| Table | Primary purpose | Key constraints |
|-------|-----------------|-----------------|
| `users` | Public site accounts | `username` UNIQUE, `email` UNIQUE |
| `admins` | Staff accounts (separate table) | `username` UNIQUE |
| `categories` | Product groupings | `name` UNIQUE |
| `products` | Catalog | FK `category_id → categories.id` ON DELETE SET NULL |
| `cart_items` | Persistent user carts | UNIQUE `(user_id, product_id)`, FKs cascade |
| `orders` | Order header | FK `user_id → users.id` |
| `order_items` | Order lines (snapshot of name + price) | FK to `orders` (CASCADE) and `products` (RESTRICT) |

The snapshot fields on `order_items` (`product_name`, `unit_price`) are intentional: orders must remain stable even if the catalog later changes (Req 8.2).

## Database Schema

The database is named `nexus_shop`. All tables use InnoDB and `utf8mb4`. Foreign keys keep referential integrity simple; deletes use `ON DELETE SET NULL` for the products → categories link so deleting a category unassigns it from products (per requirement 10.4).

### `sql/schema.sql`

```sql
CREATE DATABASE IF NOT EXISTS nexus_shop
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nexus_shop;

CREATE TABLE users (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(50)  NOT NULL UNIQUE,
  email        VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admins (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE products (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150)  NOT NULL,
  description TEXT          NOT NULL,
  price       DECIMAL(10,2) NOT NULL,
  image       VARCHAR(255)  NOT NULL DEFAULT 'default.png',
  category_id INT UNSIGNED  NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cart_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity   INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_user_product (user_id, product_id),
  CONSTRAINT fk_cart_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  total       DECIMAL(10,2) NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  product_name VARCHAR(150) NOT NULL,    -- snapshot at checkout
  unit_price  DECIMAL(10,2) NOT NULL,    -- snapshot at checkout
  quantity    INT UNSIGNED  NOT NULL,
  CONSTRAINT fk_oi_order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
```

Notes:
- `users.email` and `users.username` are both `UNIQUE`, so duplicate-registration checks (Req 4.2) can rely on a single insert with caught duplicate-key errors, plus a friendly pre-check.
- `cart_items` has a unique `(user_id, product_id)` pair so the "increment qty if already in cart" logic can use `INSERT ... ON DUPLICATE KEY UPDATE quantity = quantity + 1`.
- `order_items.unit_price` and `order_items.product_name` are deliberate snapshots so later product edits don't change historical orders (Req 8.2).

## Page List

### Public pages

| Page | File | Purpose |
|------|------|---------|
| Shop / Listing | `index.php` | Shows all products. Accepts `?q=` and `?category_id=` for search + filter. |
| Product Detail | `product.php?id=N` | Full description, price, image, "Add to cart" form. |
| Cart | `cart.php` | Lists cart lines with qty inputs and remove buttons; shows total. |
| Cart Action | `cart_action.php` | POST endpoint: `action=add|update|remove`. Redirects back. |
| Checkout | `checkout.php` | If guest → redirect to login. Else creates order from cart. |
| Order Confirmation | `order_confirm.php?order_id=N` | Shows order id + purchased lines. |
| Login | `login.php` | User login form + handler. |
| Register | `register.php` | User registration form + handler. |
| Logout | `logout.php` | Destroys user session and clears session cart. |

### Admin pages (all require admin session except `login.php`)

| Page | File | Purpose |
|------|------|---------|
| Admin Login | `admin/login.php` | Admin login form + handler. |
| Admin Logout | `admin/logout.php` | Destroys admin session. |
| Dashboard | `admin/index.php` | Counts of products, categories, orders. Quick links. |
| Products List | `admin/products.php` | Table of all products with edit/delete actions. |
| Add Product | `admin/product_add.php` | Form + handler for creating a product (uploads image). |
| Edit Product | `admin/product_edit.php?id=N` | Form + handler for updating a product. |
| Delete Product | `admin/product_delete.php` | POST handler that deletes a product after confirm. |
| Categories | `admin/categories.php` | Lists categories, add form, delete buttons. |

## Shared Includes

### `includes/config.php`

Defines the DB credentials and the base URL. Kept separate so the student can change DB password in one place.

```php
<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'nexus_shop');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', '/nexus_shop'); // adjust if served from different path
define('PRODUCT_IMG_DIR', __DIR__ . '/../assets/images/products');
define('PRODUCT_IMG_URL', BASE_URL . '/assets/images/products');
```

### `includes/db.php`

Opens a single mysqli connection. Every page reuses `$conn`.

```php
<?php
require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('DB connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Make sure the session is started for every page that includes db.php.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

### `includes/auth.php`

Small helpers for both user and admin auth. Keys are deliberately different (`user_id` vs `admin_id`) so the two sessions never interfere.

```php
<?php
function current_user_id() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function is_logged_in() {
    return current_user_id() > 0;
}

function require_user() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function current_admin_id() {
    return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
}

function is_admin() {
    return current_admin_id() > 0;
}

function require_admin() {
    if (!is_admin()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}
```

### `includes/helpers.php`

```php
<?php
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function money($n) { return '$' . number_format((float)$n, 2); }

function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

// Resolve product image: returns URL to file in PRODUCT_IMG_DIR, or default.png if missing.
function product_image_url($filename) {
    $filename = (string)$filename;
    if ($filename === '' || !is_file(PRODUCT_IMG_DIR . '/' . $filename)) {
        $filename = 'default.png';
    }
    return PRODUCT_IMG_URL . '/' . $filename;
}
```

### `includes/header.php` and `includes/footer.php`

Standard HTML5 wrapper with a white-background body, a top nav (Home, Cart, Login/Register or Hello user / Logout), and a search bar that posts back to `index.php`.

### `admin/includes/admin_header.php` and `admin_footer.php`

Same idea but with admin nav (Dashboard, Products, Categories, Logout).

## Cart Persistence Design

The cart has three storage tiers:

1. **Session** (`$_SESSION['cart']`) — always reflects the active visit's cart.
2. **Cookie** (`nexus_cart`) — guest-only; stores cart contents so the cart survives browser restarts.
3. **Database** (`cart_items`) — logged-in users only; survives across devices.

The session is the single source of truth during a request. On every page load, `cart.php` (the include) hydrates `$_SESSION['cart']` from whichever persistent layer applies:

- If a user is logged in: load from `cart_items WHERE user_id = ?` and overwrite the session cart.
- Else if no session cart yet but a `nexus_cart` cookie is set: decode the cookie JSON and put it into the session.

Cart shape in PHP (used everywhere):

```php
// $_SESSION['cart'] = [ product_id => quantity, ... ]
$_SESSION['cart'] = [
  3  => 1,
  7  => 2,
];
```

### `includes/cart.php` (key functions)

```php
<?php
const CART_COOKIE = 'nexus_cart';
const CART_COOKIE_TTL = 60 * 60 * 24 * 30; // 30 days

function cart_load() {
    // Hydrate $_SESSION['cart'] from DB (user) or cookie (guest)
    if (is_logged_in()) {
        $_SESSION['cart'] = cart_load_from_db(current_user_id());
        return;
    }
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = cart_load_from_cookie();
    }
}

function cart_load_from_cookie() {
    if (empty($_COOKIE[CART_COOKIE])) return [];
    $data = json_decode($_COOKIE[CART_COOKIE], true);
    if (!is_array($data)) return [];
    $clean = [];
    foreach ($data as $pid => $qty) {
        $pid = (int)$pid; $qty = (int)$qty;
        if ($pid > 0 && $qty > 0) $clean[$pid] = $qty;
    }
    return $clean;
}

function cart_save_cookie(array $cart) {
    setcookie(CART_COOKIE, json_encode($cart), time() + CART_COOKIE_TTL, '/');
}

function cart_clear_cookie() {
    setcookie(CART_COOKIE, '', time() - 3600, '/');
    unset($_COOKIE[CART_COOKIE]);
}

function cart_load_from_db($user_id) {
    global $conn;
    $stmt = $conn->prepare('SELECT product_id, quantity FROM cart_items WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $cart = [];
    while ($row = $res->fetch_assoc()) {
        $cart[(int)$row['product_id']] = (int)$row['quantity'];
    }
    return $cart;
}

function cart_add($product_id, $delta = 1) {
    $product_id = (int)$product_id;
    if ($product_id <= 0) return;
    $_SESSION['cart'][$product_id] =
        (int)($_SESSION['cart'][$product_id] ?? 0) + (int)$delta;
    cart_persist();
}

function cart_set_qty($product_id, $qty) {
    $product_id = (int)$product_id; $qty = (int)$qty;
    if ($qty < 1) { cart_remove($product_id); return; }
    $_SESSION['cart'][$product_id] = $qty;
    cart_persist();
}

function cart_remove($product_id) {
    unset($_SESSION['cart'][(int)$product_id]);
    cart_persist();
}

function cart_persist() {
    if (is_logged_in()) {
        cart_save_to_db(current_user_id(), $_SESSION['cart']);
    } else {
        cart_save_cookie($_SESSION['cart']);
    }
}

function cart_save_to_db($user_id, array $cart) {
    global $conn;
    $conn->begin_transaction();
    $del = $conn->prepare('DELETE FROM cart_items WHERE user_id = ?');
    $del->bind_param('i', $user_id);
    $del->execute();
    if (!empty($cart)) {
        $ins = $conn->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)'
        );
        foreach ($cart as $pid => $qty) {
            $ins->bind_param('iii', $user_id, $pid, $qty);
            $ins->execute();
        }
    }
    $conn->commit();
}

// Called from login.php after a successful authentication
function cart_merge_on_login($user_id) {
    $guest = $_SESSION['cart'] ?? cart_load_from_cookie();
    $userCart = cart_load_from_db($user_id);
    foreach ($guest as $pid => $qty) {
        $userCart[$pid] = (int)($userCart[$pid] ?? 0) + (int)$qty;
    }
    cart_save_to_db($user_id, $userCart);
    $_SESSION['cart'] = $userCart;
    cart_clear_cookie();
}
```

`cart_load()` is called once near the top of every page (right after the includes block). The session is therefore always in sync with the persistent layer for the request.

## Session and Cookie Strategy

| Key | Where | Lifetime | Purpose |
|-----|-------|----------|---------|
| `$_SESSION['user_id']` | PHP session | Until logout / session expiry | Identifies the logged-in user. |
| `$_SESSION['admin_id']` | PHP session | Until admin logout | Identifies the logged-in admin (different key, same PHP session). |
| `$_SESSION['cart']` | PHP session | Active visit | Working copy of the cart used during a request. |
| `$_SESSION['flash']` | PHP session | One render | Short success/error messages for redirects. |
| `nexus_cart` cookie | Browser cookie | 30 days | Guest cart contents (JSON of `pid => qty`). Cleared on login + logout. |
| PHP session cookie | Browser cookie | Browser session | Standard `PHPSESSID`, managed by PHP. |

User and admin sessions live under different keys (`user_id` vs `admin_id`), so even though they share a single `PHPSESSID`, neither can grant the other privileges. A logged-in user with no `admin_id` is treated as a guest by `require_admin()`, and a logged-in admin with no `user_id` is treated as a guest by `require_user()`.

On user logout (`logout.php`):
1. Unset `$_SESSION['user_id']` and `$_SESSION['cart']`.
2. Clear the `nexus_cart` cookie too, so the next guest starts empty (Req 7.5).

On admin logout (`admin/logout.php`):
1. Unset `$_SESSION['admin_id']`.
2. Leave the user session and cart untouched.

## Security

- **SQL injection**: every query uses `$conn->prepare(...)` + `bind_param(...)`. No user input is ever string-concatenated into a SQL statement, including the search query (which uses `LIKE ?` with `'%' . $term . '%'` bound as a string).
- **Password storage**: registration calls `password_hash($plain, PASSWORD_DEFAULT)`; login calls `password_verify($plain, $row['password_hash'])`. Plain text passwords are never stored or logged.
- **Output escaping**: every variable rendered into HTML goes through `h()` (`htmlspecialchars` with `ENT_QUOTES`, UTF-8) to block reflected XSS.
- **Admin route guard**: every admin page begins with `require_once __DIR__ . '/../includes/db.php'; require_once __DIR__ . '/../includes/auth.php'; require_admin();` (login page excluded). Without an `admin_id` in the session, the page redirects to `/admin/login.php` before any HTML is sent.
- **Form submissions**: all writes (cart actions, login, register, checkout, admin CRUD) require `POST`. GET requests to action endpoints are rejected with a redirect.
- **File upload validation**: `admin/product_add.php` and `product_edit.php` only accept image MIME types (`image/png`, `image/jpeg`, `image/webp`) using `finfo_file`, generate a random server-side filename (`uniqid('prod_', true) . '.' . $ext`), and refuse anything else.
- **Price validation**: the price field is checked with `is_numeric` and `> 0` before insert/update; on failure the form re-renders with an error.
- **Session hardening**: `session.cookie_httponly = 1` and `session.use_strict_mode = 1` are set at the top of `db.php` via `ini_set` before `session_start()`.

## Seed Data Approach

`sql/seed.sql` is run once after `schema.sql`. It inserts categories, then products that point at the existing PNGs under `assets/images/products/`.

Categories: `GPU`, `CPU`, `Mouse`, `Headset`, `RAM`, `SSD`, `Cooling`.

Products (10, mapped to existing images in `assets/images/products/`):

| # | Name | Category | Image |
|---|------|----------|-------|
| 1 | NVIDIA GeForce RTX 5090 | GPU | `rtx5090.png` |
| 2 | AMD Radeon RX 9070 XT | GPU | `rx9070xt.png` |
| 3 | AMD Ryzen 9 9950X | CPU | `ryzen9950x.png` |
| 4 | Intel Core i9-285K | CPU | `i9-285k.png` |
| 5 | Logitech G Pro X Superlight | Mouse | `gpro-superlight.png` |
| 6 | SteelSeries Arctis Nova Pro | Headset | `arctis-nova.png` |
| 7 | Corsair iCUE H150i Elite LCD | Cooling | `corsair-h150i.png` |
| 8 | G.Skill Trident Z5 DDR5-6400 32GB | RAM | `gskill-ddr5.png` |
| 9 | Samsung 990 Pro 2TB NVMe | SSD | `samsung990pro.png` |
| 10 | WD Black SN850X 2TB NVMe | SSD | `wd-sn850x.png` |

The seed file also inserts one default admin (`admin` / `admin123`) with a bcrypt hash, so the assignment can be demoed immediately:

```sql
INSERT INTO admins (username, password_hash) VALUES
  ('admin', '$2y$10$REPLACE_WITH_BCRYPT_HASH');
```

The README in the repo (or a comment at the top of `seed.sql`) explains that the hash should be generated locally with `php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"` and pasted in. Plaintext passwords are never written into the SQL file.

Setup steps the student runs once:
1. Open phpMyAdmin → run `sql/schema.sql`.
2. Generate the admin hash with the PHP one-liner above and paste it into `sql/seed.sql`.
3. Run `sql/seed.sql`.
4. Browse to `http://localhost/nexus_shop/`.

## Simple White CSS Approach

Single file: `assets/css/style.css`. No frameworks, no preprocessor. Both the public site and the admin panel link the same file.

Rules of thumb baked into the stylesheet:

- `body { background: #ffffff; color: #1a1a1a; font-family: system-ui, Arial, sans-serif; margin: 0; }`
- A simple header with a centered max-width container (`.container { max-width: 1100px; margin: 0 auto; padding: 1rem; }`).
- Product grid uses CSS Grid: `.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }`.
- Cards: white background with a thin grey border (`#e5e5e5`), small radius, modest hover shadow.
- Buttons: black text on light grey, with a primary variant (white text on `#111`).
- Forms: stacked labels, full-width inputs with a 1px border.
- Tables (admin): zebra rows with `tr:nth-child(even) { background: #fafafa; }`.
- Flash messages: `.flash.ok` (green text) and `.flash.err` (red text), no fancy animation.

The whole stylesheet is kept under ~150 lines so it stays understandable for a student-level submission.

## Error Handling

- **Database errors**: queries that fail in normal operation (e.g., login lookup not found) return empty results; the page renders an inline error like "Invalid email or password". Truly unexpected errors (DB connection failure, prepare failure) call `die()` with a generic message — acceptable for an assignment, and avoids leaking SQL.
- **Missing product**: `product.php` checks the lookup result and renders "Product not found" with the public header/footer (Req 3.3).
- **Empty cart on checkout**: `checkout.php` renders "Your cart is empty" and does not insert any rows (Req 8.4).
- **Unauthorized access**: `require_user()` and `require_admin()` redirect; they never expose protected data.
- **Form re-display**: when a form fails validation (registration duplicate, invalid price, empty fields), the page re-renders with the previously entered values (except passwords) and a `.flash.err` message at the top.
- **File upload errors**: invalid MIME or move failure → form re-renders with an error and the DB is not touched.

## Testing Strategy

The assignment is small, so testing focuses on the parts where bugs would actually hurt: cart math, cart persistence, auth, and the admin guard. The strategy combines example-based tests for specific scenarios and property-based tests for the universal rules listed in the Correctness Properties section.

**Test layers**

- **Unit tests (example-based)** for fixed scenarios: empty cart message, "Product not found" page, logout clears session, place-order while empty.
- **Property tests** for universal rules: cart operations (add/update/remove/total), cookie round-trip, guest→user merge, password-hashing invariants, login iff correct password, image fallback, combined search+category filter, CRUD round-trips, category uniqueness, order-item price snapshot.
- **Integration tests** for thin DB-touching seams: listing returns all seeded products, categories listing, checkout writes the right row count.
- **Smoke / code review** for the non-runtime requirements: prepared statements everywhere, white-background CSS, procedural style.

**Tooling**

PHP property-based testing is done with [Eris](https://github.com/giorgiosironi/eris) running under PHPUnit. Both can be installed without Composer for an assignment context by vendoring the PHARs into a `tests/` folder; if Composer is acceptable to the grader, `composer require --dev eris/eris phpunit/phpunit` is the simpler route. A small `tests/bootstrap.php` opens a dedicated `nexus_shop_test` database, loads `schema.sql`, and resets it between tests.

**Test configuration**

- Each property test runs a minimum of 100 iterations.
- Each property test is tagged with the feature name and property number, e.g. `@group nexus-shop-ecommerce` plus a docblock comment `Property 14: Guest-to-user cart merge sums quantities and clears the cookie`.
- DB-touching property tests use the test database and wrap each iteration in a transaction that is rolled back, so generators can produce hundreds of inserts cheaply.

**Mocking**

- The image-fallback property mocks `is_file` (or uses a temp directory with controlled contents) so the property exercises both branches without depending on the real product image folder.
- The cart-cookie round-trip property tests the pure encode/decode logic without touching the browser.

**What is intentionally not property-tested**

- Apache routing, CSS appearance, the look of error pages, file upload UI behavior, and "prepared statements are used" — these are checked by smoke tests or code review, per the prework classification.

## Acceptance Criteria Testing Prework

(Stored via the `prework` tool. Summary below for traceability — the full step-by-step analysis is preserved in workflow context.)

- 1.1 INTEGRATION; 1.2 PROPERTY; 1.3 SMOKE
- 2.1 PROPERTY; 2.2 PROPERTY; 2.3 PROPERTY; 2.4 EXAMPLE
- 3.1 PROPERTY; 3.2 EXAMPLE; 3.3 EDGE_CASE
- 4.1 PROPERTY; 4.2 PROPERTY; 4.3 PROPERTY; 4.4 PROPERTY; 4.5 EXAMPLE
- 5.1 PROPERTY; 5.2 PROPERTY; 5.3 PROPERTY
- 6.1 PROPERTY; 6.2 PROPERTY; 6.3 PROPERTY; 6.4 PROPERTY; 6.5 EDGE_CASE
- 7.1 PROPERTY; 7.2 PROPERTY (round-trip); 7.3 PROPERTY; 7.4 PROPERTY; 7.5 EXAMPLE
- 8.1 PROPERTY; 8.2 PROPERTY; 8.3 EXAMPLE; 8.4 EDGE_CASE; 8.5 PROPERTY
- 9.1 PROPERTY; 9.2 PROPERTY; 9.3 PROPERTY; 9.4 PROPERTY; 9.5 SMOKE
- 10.1 INTEGRATION; 10.2 PROPERTY; 10.3 PROPERTY; 10.4 PROPERTY; 10.5 SMOKE
- 11.1 SMOKE; 11.2 SMOKE; 11.3 covered by 1.2; 11.4 covered by 7.1/7.2

After reflection, redundancies were merged: 2.1+2.2+2.3 → one combined-filter property; 4.3+4.4 → one login-iff-correct-password property; 5.1+5.2+5.3 → one admin auth-and-guard property; 9.1+9.2+9.3 → one product CRUD round-trip property; 10.2+10.3 → one category uniqueness property; 11.3 and 11.4 dropped as duplicates.

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Product image fallback

For any product image filename, `product_image_url(filename)` returns the URL to that filename when the file exists in the product image directory, and the URL to `default.png` otherwise.

**Validates: Requirements 1.2, 11.3**

### Property 2: Combined search and category filter

For any list of products, any search term (possibly empty), and any selected category id (possibly null), the filtered listing contains exactly those products whose name or description contains the search term case-insensitively AND (if a category is selected) whose `category_id` equals the selected category.

**Validates: Requirements 2.1, 2.2, 2.3**

### Property 3: Product detail rendering contains product fields

For any product row, the rendered product detail page contains the product's name, description, formatted price, and resolved image URL.

**Validates: Requirements 3.1**

### Property 4: Registration stores a verifiable bcrypt hash, never plaintext

For any valid registration input `(username, email, password)`, after registration the stored `password_hash` differs from the plaintext password and `password_verify(password, stored_hash)` returns true.

**Validates: Requirements 4.1**

### Property 5: Duplicate registration is rejected

For any existing user with username `u` and email `e`, any subsequent registration attempt using the same `u` or the same `e` fails and the `users` table size is unchanged.

**Validates: Requirements 4.2**

### Property 6: Login succeeds iff credentials match

For any registered user with password `p`, login with the correct `(email, p)` succeeds and sets `$_SESSION['user_id']`; login with any other password fails and leaves `$_SESSION['user_id']` unset.

**Validates: Requirements 4.3, 4.4**

### Property 7: Admin authentication and session isolation

For any admin record, valid admin credentials set `$_SESSION['admin_id']` and `require_admin()` allows the request through; for any session that has `user_id` but no `admin_id` (or no session at all), `require_admin()` redirects to `/admin/login.php`.

**Validates: Requirements 5.1, 5.2, 5.3**

### Property 8: Add to cart increments quantity by one

For any cart and any product id, after `cart_add(product_id)` the quantity for `product_id` is `previous_quantity + 1` (where missing entries count as 0), and the quantity of every other line is unchanged.

**Validates: Requirements 6.1**

### Property 9: Cart update sets the requested quantity

For any cart line and any integer quantity `n >= 1`, after `cart_set_qty(product_id, n)` the quantity for `product_id` equals `n`, and other lines are unchanged.

**Validates: Requirements 6.2**

### Property 10: Cart remove deletes the line

For any cart and any product id, after `cart_remove(product_id)` the cart contains no entry for `product_id`, and every other line is unchanged.

**Validates: Requirements 6.3**

### Property 11: Cart total equals sum of line totals

For any cart, the displayed cart total equals `sum(unit_price(pid) * qty)` over all `(pid, qty)` lines in the cart.

**Validates: Requirements 6.4**

### Property 12: Guest cart cookie round-trip

For any guest cart `c`, decoding the JSON written to the `nexus_cart` cookie yields a cart equal to `c` (after dropping non-positive ids/quantities).

**Validates: Requirements 7.2**

### Property 13: Session cart reflects the persistent layer

For any sequence of cart operations, after `cart_load()` the session cart equals the persistent cart for the current actor: the database `cart_items` rows for a logged-in user, or the `nexus_cart` cookie contents for a guest.

**Validates: Requirements 7.1, 7.3**

### Property 14: Guest-to-user cart merge sums quantities and clears the cookie

For any guest cart `g` and any existing user cart `u` for user `id`, after `cart_merge_on_login(id)` the user cart in the database equals, per product, `g[pid] + u[pid]` (treating missing entries as 0), and the `nexus_cart` cookie is cleared.

**Validates: Requirements 7.4**

### Property 15: Checkout creates one order plus one item per cart line and empties the cart

For any logged-in user with a non-empty cart of `N` distinct products, after a successful `checkout` exactly one new row exists in `orders` for that user, exactly `N` new rows exist in `order_items` linked to that order with the same `(product_id, quantity)` pairs, and `cart_items` for that user is empty.

**Validates: Requirements 8.1**

### Property 16: order_items snapshots the unit price at checkout

For any cart line checked out at price `p`, the corresponding `order_items.unit_price` equals `p` even if the product's price is later changed in the `products` table.

**Validates: Requirements 8.2**

### Property 17: Order confirmation lists the order id and every purchased item

For any successful order, the rendered order confirmation page contains the order's id and the name of every line item from `order_items`.

**Validates: Requirements 8.5**

### Property 18: Product CRUD round-trip

For any valid product input `p`, after creating it the products table contains a row equal to `p` on the saved fields; after editing it with input `p'`, the row equals `p'`; after deleting it, no row with that id exists.

**Validates: Requirements 9.1, 9.2, 9.3**

### Property 19: Invalid product price is rejected

For any submitted price that is not numeric or is `<= 0`, the admin product create/edit handler rejects the submission and the products table is unchanged.

**Validates: Requirements 9.4**

### Property 20: Category names are unique on insert

For any pair of category insertions, the second insertion succeeds iff its name (case-sensitive, after trimming) does not already exist in `categories`; on rejection the table is unchanged.

**Validates: Requirements 10.2, 10.3**

### Property 21: Deleting a category unassigns it from products

For any category `c` and any set of products with `category_id = c.id`, after deleting `c` no row in `categories` has `id = c.id` and every previously linked product has `category_id` equal to `NULL`.

**Validates: Requirements 10.4**
