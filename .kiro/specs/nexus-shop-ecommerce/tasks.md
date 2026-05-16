# Implementation Plan: Nexus Shop E-commerce

## Overview

Build a small XAMPP-based e-commerce site in procedural PHP with mysqli prepared statements. Work proceeds bottom-up: database schema and seed first, then shared includes (db, auth, helpers, cart, header/footer), then public pages (listing, product detail, cart, auth, checkout), then the admin panel (login, dashboard, product CRUD, categories), then styling, and finally property/unit tests for the rules in the design's Correctness Properties section.

Each task references specific acceptance criteria from `requirements.md`. Property test tasks reference properties from the `Correctness Properties` section of `design.md`. All test sub-tasks are marked optional (`*`).

## Tasks

- [x] 1. Database schema and seed data
  - [x] 1.1 Create `sql/schema.sql` with all six tables
    - Create `nexus_shop` database with utf8mb4
    - Define `users`, `admins`, `categories`, `products`, `cart_items`, `orders`, `order_items` tables exactly as in the design's Database Schema section
    - Add the FK `products.category_id → categories.id ON DELETE SET NULL`, the unique `(user_id, product_id)` on `cart_items`, and the snapshot columns on `order_items`
    - _Requirements: 9.5, 10.4, 11.2_

  - [x] 1.2 Create `sql/seed.sql` with categories, products, and default admin
    - Insert the seven categories: GPU, CPU, Mouse, Headset, RAM, SSD, Cooling
    - Insert the ten products from the design's seed table, each pointing at the matching existing PNG in `assets/images/products/`
    - Insert one admin row (`admin`) with a placeholder bcrypt hash and a comment explaining how to generate it with `php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"`
    - _Requirements: 5.1, 11.3_

- [x] 2. Shared includes (config, db, helpers, auth)
  - [x] 2.1 Create `includes/config.php`
    - Define `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `BASE_URL`, `PRODUCT_IMG_DIR`, `PRODUCT_IMG_URL`
    - _Requirements: 11.2, 11.3_

  - [x] 2.2 Create `includes/db.php`
    - Open a single mysqli connection in `$conn`, set charset to utf8mb4
    - Set `session.cookie_httponly` and `session.use_strict_mode` via `ini_set`, then call `session_start()` if no session is active
    - On `connect_error`, `die()` with a generic message
    - _Requirements: 11.2, 11.4_

  - [x] 2.3 Create `includes/helpers.php`
    - Implement `h()`, `money()`, `redirect()`, and `product_image_url()` exactly as described in the design
    - `product_image_url()` falls back to `default.png` when the file is missing in `PRODUCT_IMG_DIR`
    - _Requirements: 1.2, 11.3_

  - [x] 2.4 Create `includes/auth.php`
    - Implement `current_user_id()`, `is_logged_in()`, `require_user()`, `current_admin_id()`, `is_admin()`, `require_admin()`
    - Keep `user_id` and `admin_id` as separate session keys
    - `require_user()` redirects to `/login.php`; `require_admin()` redirects to `/admin/login.php`
    - _Requirements: 4.3, 4.5, 5.2, 5.3_

- [x] 3. Cart helpers
  - [x] 3.1 Create `includes/cart.php` with cookie + DB layer functions
    - Define `CART_COOKIE` and `CART_COOKIE_TTL` constants
    - Implement `cart_load_from_cookie()`, `cart_save_cookie()`, `cart_clear_cookie()`
    - Implement `cart_load_from_db($user_id)` and `cart_save_to_db($user_id, $cart)` using prepared statements inside a transaction (delete-then-insert)
    - _Requirements: 7.2, 7.3, 11.2_

  - [x] 3.2 Add session-level cart operations to `includes/cart.php`
    - Implement `cart_load()` (hydrates `$_SESSION['cart']` from DB for users or cookie for guests)
    - Implement `cart_add()`, `cart_set_qty()`, `cart_remove()`, `cart_persist()`
    - `cart_set_qty()` with qty < 1 must remove the line
    - Each mutator calls `cart_persist()` to write to the right tier
    - _Requirements: 6.1, 6.2, 6.3, 7.1, 7.3_

  - [x] 3.3 Add `cart_merge_on_login()` to `includes/cart.php`
    - Sum guest cart and existing user cart per product, save to DB, replace session cart, clear the cookie
    - _Requirements: 7.4_

- [x] 4. Public header, footer, and CSS skeleton
  - [x] 4.1 Create `includes/header.php`
    - Open `<html>`, link `assets/css/style.css`, render top nav (Home, Cart, Login/Register or "Hello {user}" + Logout)
    - Render the search bar form posting GET to `index.php` with `q` and `category_id` fields populated from the categories table
    - _Requirements: 2.1, 2.2, 11.1_

  - [x] 4.2 Create `includes/footer.php`
    - Close container, body, html with a small footer note
    - _Requirements: 11.1_

  - [x] 4.3 Create `assets/css/style.css` skeleton
    - White background, container (max-width 1100px), simple type, grid for product cards, basic card and button styles, table zebra rows, `.flash.ok` / `.flash.err`
    - _Requirements: 11.1_

- [x] 5. Checkpoint - shared layer is wired
  - Verify `includes/db.php`, `includes/auth.php`, `includes/cart.php`, `includes/helpers.php`, header, footer, and CSS all load without warnings on a blank `index.php` smoke page.
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Public listing and product detail pages
  - [x] 6.1 Implement `index.php` (product listing with search + category filter)
    - Include the shared layer, call `cart_load()`
    - Read `q` and `category_id` from `$_GET`
    - Build a single prepared SELECT with `LIKE ?` on name/description and optional `category_id = ?`, joining `categories`
    - Render product cards with `product_image_url()`; show "No products found" when the result set is empty
    - _Requirements: 1.1, 1.3, 2.1, 2.2, 2.3, 2.4_

  - [x] 6.2 Implement `product.php` (product detail page)
    - Include shared layer, call `cart_load()`
    - Read `id` from `$_GET`, fetch the product with a prepared statement joining the category
    - When the row is missing, render "Product not found" inside the public layout
    - When found, render image, name, description, price, and an "Add to cart" form posting to `cart_action.php` with `action=add` and the product id
    - _Requirements: 3.1, 3.2, 3.3_

- [x] 7. Cart pages and action handler
  - [x] 7.1 Implement `cart_action.php` (POST handler for cart mutations)
    - Reject non-POST with a redirect to `/cart.php`
    - Dispatch on `action` (`add`, `update`, `remove`) and call the matching cart helper with sanitized `product_id` and `quantity`
    - Redirect back to the referer or `/cart.php` after the mutation
    - _Requirements: 6.1, 6.2, 6.3_

  - [x] 7.2 Implement `cart.php` (cart view)
    - Include shared layer, call `cart_load()`
    - Fetch product info for every cart line in one prepared `SELECT ... WHERE id IN (...)` with bound integer params
    - Render a table with name, unit price, quantity input, line total, remove button, and overall total
    - Show "Your cart is empty" when the cart has no lines
    - _Requirements: 6.4, 6.5_

- [x] 8. User auth pages
  - [x] 8.1 Implement `register.php` (form + handler)
    - GET renders the form; POST validates required fields, runs prepared SELECT to detect duplicate email/username, inserts a new row with `password_hash($pwd, PASSWORD_DEFAULT)` on success
    - On duplicate, re-render the form with a flash error and the previously entered username/email (no password)
    - _Requirements: 4.1, 4.2, 11.2_

  - [x] 8.2 Implement `login.php` (form + handler)
    - GET renders the form; POST looks up the user by email with a prepared statement, verifies the password with `password_verify`
    - On success, set `$_SESSION['user_id']`, call `cart_merge_on_login()`, redirect to `index.php`
    - On failure, render "Invalid email or password" and re-render the form
    - _Requirements: 4.3, 4.4, 7.4_

  - [x] 8.3 Implement `logout.php`
    - Unset `$_SESSION['user_id']` and `$_SESSION['cart']`, call `cart_clear_cookie()`, redirect to `index.php`
    - _Requirements: 4.5, 7.5_

- [x] 9. Checkout and order confirmation
  - [x] 9.1 Implement `checkout.php`
    - When the user is not logged in, redirect to `/login.php` before any DB write
    - When the cart is empty, render "Your cart is empty" and do not insert any rows
    - On valid POST, fetch products for the cart lines, compute the total, then in a transaction insert one `orders` row and one `order_items` row per line snapshotting `product_name` and `unit_price`
    - On success, clear the cart (DB + session) and redirect to `order_confirm.php?order_id=...`
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

  - [x] 9.2 Implement `order_confirm.php`
    - Require user auth; fetch the order and its `order_items` only when `orders.user_id` matches the current user
    - Render the order id, each line item with snapshot name/price/qty, and the order total
    - _Requirements: 8.5_

- [x] 10. Checkpoint - public site flow works end-to-end
  - Manually walk: browse → search/filter → view product → add to cart → register → login → checkout → confirmation.
  - Ensure all tests pass, ask the user if questions arise.

- [x] 11. Admin panel: shared layer and auth
  - [x] 11.1 Create `admin/includes/admin_header.php` and `admin/includes/admin_footer.php`
    - Admin header links to dashboard, products, categories, logout, and uses the same `assets/css/style.css`
    - _Requirements: 11.1_

  - [x] 11.2 Implement `admin/login.php` (form + handler)
    - GET renders form; POST looks up the admin by username with a prepared statement, verifies password, sets `$_SESSION['admin_id']`, redirects to `admin/index.php`
    - On failure, render an error message and re-render the form
    - _Requirements: 5.1_

  - [x] 11.3 Implement `admin/logout.php`
    - Unset `$_SESSION['admin_id']` and redirect to `admin/login.php`
    - Leave `user_id` and the user cart untouched
    - _Requirements: 5.3_

  - [x] 11.4 Implement `admin/index.php` (dashboard)
    - Call `require_admin()` first, then render counts of products, categories, and orders with prepared `SELECT COUNT(*)` queries plus quick-link buttons
    - _Requirements: 5.2, 11.2_

- [x] 12. Admin product CRUD
  - [x] 12.1 Implement `admin/products.php` (list)
    - Call `require_admin()`; render a table of all products joined to their category, with edit and delete action buttons
    - _Requirements: 5.2, 9.5_

  - [x] 12.2 Implement `admin/product_add.php` (add form + handler)
    - Call `require_admin()`; GET renders the form populated with the categories dropdown
    - POST validates name, description, price (numeric and > 0), category, and image upload (allowed MIME via `finfo_file`, random filename `uniqid('prod_', true).'.'.$ext`); on success, INSERT with a prepared statement and move the upload into `assets/images/products/`
    - On validation failure, re-render with the previously entered values and a flash error
    - _Requirements: 9.1, 9.4, 9.5, 10.5_

  - [x] 12.3 Implement `admin/product_edit.php` (edit form + handler)
    - Call `require_admin()`; GET fetches the product row and renders the populated form with the categories dropdown
    - POST validates the same fields as add; on success, UPDATE with a prepared statement, optionally replacing the image when a new one is uploaded
    - _Requirements: 9.2, 9.4, 9.5, 10.5_

  - [x] 12.4 Implement `admin/product_delete.php` (POST-only delete)
    - Call `require_admin()`; require POST, run a prepared DELETE for the given id, redirect back to `admin/products.php`
    - The list page's delete button must use a JS `confirm()` prompt before submitting (handled in `assets/js/app.js`)
    - _Requirements: 9.3, 9.5_

- [x] 13. Admin category management
  - [x] 13.1 Implement `admin/categories.php` (list, add, delete)
    - Call `require_admin()`; GET renders the categories table and an add form
    - POST `add_name`: trim, prepared SELECT for an existing name, INSERT when unique, otherwise re-render with "Category already exists"
    - POST `delete_id`: prepared DELETE; the FK `ON DELETE SET NULL` on `products.category_id` already unassigns the category from any products
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [x] 14. Styling polish and small JS
  - [x] 14.1 Flesh out `assets/css/style.css`
    - Refine spacing, card hover, primary button variant, admin tables, flash colors so the site is presentable on a white background
    - _Requirements: 11.1_

  - [x] 14.2 Create `assets/js/app.js`
    - Add a delete-confirm handler for admin delete forms and an auto-submit hook on the category dropdown in the public header
    - _Requirements: 9.3, 11.1_

- [x] 15. Checkpoint - admin panel works end-to-end
  - Manually walk: admin login → dashboard counts → add product → edit product → delete product → add/delete category → logout.
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 16. Test setup
  - [ ]* 16.1 Create `tests/bootstrap.php` and a `nexus_shop_test` schema loader
    - Include `includes/db.php` against a `nexus_shop_test` database, run `sql/schema.sql` between tests, wrap DB-touching tests in transactions that roll back
    - Provide PHPUnit + Eris autoload (vendored PHARs or composer dev deps, per the design's Testing Strategy)
    - _Requirements: 11.2_

- [ ] 17. Property tests for helpers and cart logic
  - [ ]* 17.1 Property test for `product_image_url()` fallback
    - **Property 1: Product image fallback**
    - **Validates: Requirements 1.2, 11.3**

  - [ ]* 17.2 Property test for combined search + category filter
    - **Property 2: Combined search and category filter**
    - **Validates: Requirements 2.1, 2.2, 2.3**

  - [ ]* 17.3 Property test for product detail rendering
    - **Property 3: Product detail rendering contains product fields**
    - **Validates: Requirements 3.1**

  - [ ]* 17.4 Unit test for "Product not found" message on missing id
    - Hit `product.php` with an unused id; assert the page contains "Product not found"
    - _Requirements: 3.3_

- [ ] 18. Property tests for auth
  - [ ]* 18.1 Property test for registration storing a verifiable bcrypt hash
    - **Property 4: Registration stores a verifiable bcrypt hash, never plaintext**
    - **Validates: Requirements 4.1**

  - [ ]* 18.2 Property test for duplicate registration rejection
    - **Property 5: Duplicate registration is rejected**
    - **Validates: Requirements 4.2**

  - [ ]* 18.3 Property test for login iff correct password
    - **Property 6: Login succeeds iff credentials match**
    - **Validates: Requirements 4.3, 4.4**

  - [ ]* 18.4 Unit test for logout clearing user session and cookie cart
    - Set up a logged-in session with a cart, call `logout.php`, assert `user_id`, session cart, and `nexus_cart` cookie are all gone
    - _Requirements: 4.5, 7.5_

  - [ ]* 18.5 Property test for admin authentication and session isolation
    - **Property 7: Admin authentication and session isolation**
    - **Validates: Requirements 5.1, 5.2, 5.3**

- [ ] 19. Property tests for cart operations
  - [ ]* 19.1 Property test for `cart_add` increment
    - **Property 8: Add to cart increments quantity by one**
    - **Validates: Requirements 6.1**

  - [ ]* 19.2 Property test for `cart_set_qty`
    - **Property 9: Cart update sets the requested quantity**
    - **Validates: Requirements 6.2**

  - [ ]* 19.3 Property test for `cart_remove`
    - **Property 10: Cart remove deletes the line**
    - **Validates: Requirements 6.3**

  - [ ]* 19.4 Property test for cart total = sum of line totals
    - **Property 11: Cart total equals sum of line totals**
    - **Validates: Requirements 6.4**

  - [ ]* 19.5 Unit test for "Your cart is empty" message
    - Render `cart.php` with no cart entries and assert the empty-cart message
    - _Requirements: 6.5_

- [ ] 20. Property tests for cart persistence
  - [ ]* 20.1 Property test for guest cart cookie round-trip
    - **Property 12: Guest cart cookie round-trip**
    - **Validates: Requirements 7.2**

  - [ ]* 20.2 Property test for `cart_load()` reflecting the persistent layer
    - **Property 13: Session cart reflects the persistent layer**
    - **Validates: Requirements 7.1, 7.3**

  - [ ]* 20.3 Property test for `cart_merge_on_login()`
    - **Property 14: Guest-to-user cart merge sums quantities and clears the cookie**
    - **Validates: Requirements 7.4**

- [ ] 21. Property tests for checkout
  - [ ]* 21.1 Property test for checkout creating order + items and emptying the cart
    - **Property 15: Checkout creates one order plus one item per cart line and empties the cart**
    - **Validates: Requirements 8.1**

  - [ ]* 21.2 Property test for `order_items.unit_price` snapshot
    - **Property 16: order_items snapshots the unit price at checkout**
    - **Validates: Requirements 8.2**

  - [ ]* 21.3 Unit test for guest "Place order" redirecting to login
    - POST to `checkout.php` without a `user_id`; assert a redirect to `/login.php` and no rows added to `orders`
    - _Requirements: 8.3_

  - [ ]* 21.4 Unit test for empty-cart checkout
    - POST to `checkout.php` while logged in with an empty cart; assert "Your cart is empty" and no order rows
    - _Requirements: 8.4_

  - [ ]* 21.5 Property test for order confirmation page contents
    - **Property 17: Order confirmation lists the order id and every purchased item**
    - **Validates: Requirements 8.5**

- [ ] 22. Property tests for admin CRUD and categories
  - [ ]* 22.1 Property test for product CRUD round-trip
    - **Property 18: Product CRUD round-trip**
    - **Validates: Requirements 9.1, 9.2, 9.3**

  - [ ]* 22.2 Property test for invalid product price rejection
    - **Property 19: Invalid product price is rejected**
    - **Validates: Requirements 9.4**

  - [ ]* 22.3 Property test for category name uniqueness
    - **Property 20: Category names are unique on insert**
    - **Validates: Requirements 10.2, 10.3**

  - [ ]* 22.4 Property test for deleting a category unassigns its products
    - **Property 21: Deleting a category unassigns it from products**
    - **Validates: Requirements 10.4**

  - [ ]* 22.5 Integration test for categories listing and product-to-category assignment
    - Seed N categories and M products, hit `admin/categories.php` and `admin/products.php`, assert all rows are present and each product has at most one category
    - _Requirements: 10.1, 10.5_

- [x] 23. Final checkpoint - all tests pass
  - Run the full PHPUnit suite (PBT + unit + integration) plus a manual smoke pass of the public and admin flows.
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP — they are the unit, integration, and property tests.
- Each task references the requirement clauses it satisfies. Property tests additionally cite the property number from the `Correctness Properties` section of `design.md`.
- Checkpoints are placed after the shared layer, after the public site flow, after the admin flow, and at the very end so progress can be validated incrementally.
- All DB access uses mysqli prepared statements per Requirement 11.2; no string concatenation into SQL.
- The image fallback (`default.png`) is centralized in `product_image_url()` so listing, detail, cart, and admin pages all share the same behavior.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "2.1", "4.3"] },
    { "id": 1, "tasks": ["2.2", "2.3"] },
    { "id": 2, "tasks": ["2.4", "3.1"] },
    { "id": 3, "tasks": ["3.2", "3.3", "4.1", "4.2", "11.1"] },
    { "id": 4, "tasks": ["6.1", "6.2", "7.1", "7.2", "8.1", "8.2", "8.3", "11.2", "11.3", "11.4", "12.1", "13.1", "14.1", "14.2"] },
    { "id": 5, "tasks": ["9.1", "9.2", "12.2", "12.3", "12.4"] },
    { "id": 6, "tasks": ["16.1"] },
    { "id": 7, "tasks": ["17.1", "17.2", "17.3", "17.4", "18.1", "18.2", "18.3", "18.4", "18.5", "19.1", "19.2", "19.3", "19.4", "19.5", "20.1", "20.2", "20.3", "21.1", "21.2", "21.3", "21.4", "21.5", "22.1", "22.2", "22.3", "22.4", "22.5"] }
  ]
}
```
