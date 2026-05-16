-- Nexus Shop database schema
-- All tables use InnoDB and utf8mb4. The products -> categories link uses
-- ON DELETE SET NULL so deleting a category unassigns it from products
-- (per requirement 10.4). order_items snapshots product_name and unit_price
-- so historical orders remain stable after later catalog edits (requirement 8.2).

CREATE DATABASE IF NOT EXISTS nexus_shop
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nexus_shop;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
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
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  total      DECIMAL(10,2) NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  product_name VARCHAR(150) NOT NULL,    -- snapshot at checkout
  unit_price   DECIMAL(10,2) NOT NULL,   -- snapshot at checkout
  quantity     INT UNSIGNED NOT NULL,
  CONSTRAINT fk_oi_order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
