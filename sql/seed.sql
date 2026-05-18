-- =====================================================================
-- Nexus Shop — Seed data
-- =====================================================================
-- Run this AFTER sql/schema.sql.
--
-- This file:
--   1. Inserts the 7 product categories (GPU, CPU, Mouse, Headset, RAM,
--      SSD, Cooling).
--   2. Inserts 10 demo products that point at the PNGs already present
--      in assets/images/products/.
--   3. Inserts one default admin account (username: admin / password:
--      admin123) with a pre-generated bcrypt hash so you can log into
--      /admin/ immediately.
--
-- The admin password hash below was generated with:
--   php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
-- If you'd like to use a different password, run that command with your
-- own plaintext, copy the output (a string starting with $2y$10$...)
-- and replace the hash on the INSERT INTO admins line near the bottom
-- of this file. NEVER store the plaintext password in this file.
-- =====================================================================

USE techno_dz;

-- ---------------------------------------------------------------------
-- Categories
-- ---------------------------------------------------------------------
INSERT IGNORE INTO categories (name) VALUES
  ('GPU'),
  ('CPU'),
  ('Mouse'),
  ('Headset'),
  ('RAM'),
  ('SSD'),
  ('Cooling');

-- ---------------------------------------------------------------------
-- Products
-- ---------------------------------------------------------------------
-- Each row uses a sub-SELECT on categories.name so the seed isn't tied
-- to the auto-increment order of the inserts above.
-- Image filenames must match files in assets/images/products/.

INSERT INTO products (name, description, price, image, category_id) VALUES
  (
    'NVIDIA GeForce RTX 5090',
    'Flagship Blackwell GPU with 32GB GDDR7, advanced ray tracing, and DLSS 4 for 4K and 8K gaming.',
    1999.99,
    'rtx5090.png',
    (SELECT id FROM categories WHERE name = 'GPU')
  ),
  (
    'AMD Radeon RX 9070 XT',
    'High-performance RDNA 4 graphics card with 16GB GDDR6 memory, tuned for smooth 1440p and 4K gameplay.',
    599.99,
    'rx9070xt.png',
    (SELECT id FROM categories WHERE name = 'GPU')
  ),
  (
    'AMD Ryzen 9 9950X',
    '16-core, 32-thread Zen 5 desktop processor with boost clocks up to 5.7 GHz, ideal for gaming and content creation.',
    649.99,
    'ryzen9950x.png',
    (SELECT id FROM categories WHERE name = 'CPU')
  ),
  (
    'Intel Core i9-285K',
    '24-core hybrid Arrow Lake processor with up to 5.7 GHz boost and integrated graphics, built for the LGA1851 platform.',
    589.99,
    'i9-285k.png',
    (SELECT id FROM categories WHERE name = 'CPU')
  ),
  (
    'Logitech G Pro X Superlight',
    'Ultra-lightweight 63g wireless esports mouse with HERO 25K sensor and up to 70 hours of battery life.',
    149.99,
    'gpro-superlight.png',
    (SELECT id FROM categories WHERE name = 'Mouse')
  ),
  (
    'SteelSeries Arctis Nova Pro',
    'Premium wired gaming headset with Hi-Res Audio drivers, active noise cancellation, and a multi-system GameDAC.',
    349.99,
    'arctis-nova.png',
    (SELECT id FROM categories WHERE name = 'Headset')
  ),
  (
    'Corsair iCUE H150i Elite LCD',
    '360mm all-in-one liquid CPU cooler with a customizable IPS LCD pump display and three ML120 RGB fans.',
    279.99,
    'corsair-h150i.png',
    (SELECT id FROM categories WHERE name = 'Cooling')
  ),
  (
    'G.Skill Trident Z5 DDR5-6400 32GB',
    '32GB (2 x 16GB) DDR5-6400 CL32 memory kit with a brushed aluminum heatspreader for high-end gaming rigs.',
    129.99,
    'gskill-ddr5.png',
    (SELECT id FROM categories WHERE name = 'RAM')
  ),
  (
    'Samsung 990 Pro 2TB NVMe',
    '2TB PCIe 4.0 NVMe M.2 SSD delivering up to 7,450 MB/s sequential reads for fast game loads and content workflows.',
    189.99,
    'samsung990pro.png',
    (SELECT id FROM categories WHERE name = 'SSD')
  ),
  (
    'WD Black SN850X 2TB NVMe',
    '2TB PCIe 4.0 NVMe M.2 SSD with up to 7,300 MB/s reads and Game Mode 2.0 for low-latency gaming.',
    179.99,
    'wd-sn850x.png',
    (SELECT id FROM categories WHERE name = 'SSD')
  );

-- ---------------------------------------------------------------------
-- Default admin account
-- ---------------------------------------------------------------------
-- Username: admin
-- Password: admin123  (verified against the hash below with password_verify)
--
-- To regenerate the hash, run:
--   php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
-- and paste the output (a string starting with $2y$10$...) below.
INSERT IGNORE INTO admins (username, password_hash) VALUES
  ('admin', '$2y$10$2olZHK7.c0ewGXGivaBqe.tN9y8IOZjNQgeYe5HXQi9Vdvs121VEO');
