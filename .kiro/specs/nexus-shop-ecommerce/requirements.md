# Requirements Document

## Introduction

Nexus Shop is a small e-commerce web application built for a student assignment using XAMPP (HTML, PHP, JavaScript, CSS, MySQL). The site lets visitors browse computer hardware products (GPUs, CPUs, mice, headsets, RAM, SSDs, cooling), search and filter them by category, view product details, add items to a cart, and place a simulated order. Registered users get a database-backed cart that follows them across sessions, while guests rely on cookies and the active session. An admin panel, protected by a separate login, provides CRUD operations for products and categories. The implementation uses procedural PHP with prepared statements (mysqli or PDO), a simple white-background layout, and product images already present under `assets/images/products/`.

## Glossary

- **Shop**: The public-facing storefront pages of Nexus Shop (product listing, product detail, cart, checkout).
- **Admin_Panel**: The protected back-office area under `/admin/` used to manage products and categories.
- **Product**: A row in the `products` table representing one item for sale (image, name, description, price, category).
- **Category**: A row in the `categories` table grouping products (e.g., GPU, CPU, Mouse, Headset, RAM, SSD, Cooling).
- **Cart**: The collection of products and quantities a visitor intends to buy.
- **Guest**: A visitor who is not logged in as a user.
- **User**: A visitor authenticated via the `users` table.
- **Admin**: A staff account authenticated via the `admins` table, separate from `users`.
- **Order**: A row in the `orders` table created at checkout, with line items in `order_items`.
- **Image_Folder**: The directory `assets/images/products/` containing product images, including the fallback `default.png`.

## Requirements

### Requirement 1: Product Listing

**User Story:** As a Guest or User, I want to see all available products on the shop page, so that I can browse what is for sale.

#### Acceptance Criteria

1. WHEN the shop page is requested, THE Shop SHALL display every Product from the `products` table with image, name, description, and price.
2. WHEN a Product has no image file in the Image_Folder, THE Shop SHALL display `default.png` in place of the missing image.
3. THE Shop SHALL render all product data using prepared statements when reading from the database.

### Requirement 2: Search and Category Filter

**User Story:** As a Guest or User, I want to search products and filter by category, so that I can find specific items quickly.

#### Acceptance Criteria

1. WHEN a search term is submitted in the search bar, THE Shop SHALL display only Products whose name or description contains the search term (case-insensitive).
2. WHEN a Category is selected from the category dropdown, THE Shop SHALL display only Products assigned to that Category.
3. WHEN both a search term and a Category are applied together, THE Shop SHALL display only Products that match both conditions.
4. IF no Product matches the active search and filter, THEN THE Shop SHALL display a "No products found" message.

### Requirement 3: Product Detail Page

**User Story:** As a Guest or User, I want to open a product detail page, so that I can read the full description before adding it to my cart.

#### Acceptance Criteria

1. WHEN a Product is opened from the listing, THE Shop SHALL display the full description, price, and image of that Product on a dedicated detail page.
2. THE Shop SHALL display an "Add to cart" button on the product detail page.
3. IF the requested Product ID does not exist, THEN THE Shop SHALL display a "Product not found" message.

### Requirement 4: User Registration, Login, Logout

**User Story:** As a Guest, I want to register, log in, and log out, so that I can have a personal account with a saved cart.

#### Acceptance Criteria

1. WHEN a Guest submits the registration form with a username, email, and password, THE Shop SHALL store the new account in the `users` table with the password hashed.
2. IF the submitted email or username is already registered, THEN THE Shop SHALL display an error message and SHALL NOT create a duplicate account.
3. WHEN a Guest submits valid login credentials, THE Shop SHALL start a User session and redirect to the shop page.
4. IF login credentials are invalid, THEN THE Shop SHALL display an "Invalid email or password" message.
5. WHEN a User clicks logout, THE Shop SHALL destroy the session and return the visitor to a Guest state.

### Requirement 5: Admin Login

**User Story:** As an Admin, I want a separate admin login page, so that staff accounts are kept apart from regular users.

#### Acceptance Criteria

1. WHEN an Admin submits valid credentials at `/admin/login.php`, THE Admin_Panel SHALL authenticate against the `admins` table and start an admin session.
2. IF an unauthenticated visitor requests any admin page other than the login page, THEN THE Admin_Panel SHALL redirect to `/admin/login.php`.
3. THE Admin_Panel SHALL keep admin sessions separate from User sessions so that logging in as a User does not grant admin access.

### Requirement 6: Cart Operations

**User Story:** As a Guest or User, I want to add, update, remove, and view items in my cart, so that I can decide what to buy.

#### Acceptance Criteria

1. WHEN "Add to cart" is clicked for a Product, THE Shop SHALL add that Product to the Cart with quantity 1, or increase the quantity by 1 if the Product is already in the Cart.
2. WHEN the quantity for a Cart line is updated to a value of 1 or greater, THE Shop SHALL save the new quantity for that line.
3. WHEN "Remove" is clicked for a Cart line, THE Shop SHALL delete that line from the Cart.
4. WHEN the cart page is opened, THE Shop SHALL display each Cart line with name, unit price, quantity, line total, and an overall total.
5. IF the Cart is empty, THEN THE Shop SHALL display an "Your cart is empty" message.

### Requirement 7: Cart Persistence

**User Story:** As a visitor, I want my cart to persist between page loads and visits, so that I do not lose my selections.

#### Acceptance Criteria

1. WHILE a Guest is browsing, THE Shop SHALL keep the Cart in the PHP session for the active visit.
2. WHEN a Guest closes and reopens the browser, THE Shop SHALL restore the Cart from a cookie that stores the Cart contents.
3. WHILE a User is logged in, THE Shop SHALL keep the Cart in the database (`cart_items` table keyed by user ID).
4. WHEN a Guest with a non-empty Cart logs in, THE Shop SHALL merge the Guest cart into the User cart in the database and clear the cookie copy.
5. WHEN a User logs out, THE Shop SHALL clear the session cart so the next Guest on the same browser starts with an empty Cart.

### Requirement 8: Checkout

**User Story:** As a User, I want to place an order from my cart, so that the assignment can demonstrate a complete purchase flow.

#### Acceptance Criteria

1. WHEN a User clicks "Place order" with a non-empty Cart, THE Shop SHALL insert one row into the `orders` table and one row per Cart line into the `order_items` table, then empty the Cart.
2. THE Shop SHALL store the unit price of each Product on the `order_items` row at the moment of checkout.
3. IF a Guest clicks "Place order", THEN THE Shop SHALL redirect to the login page before creating any order.
4. IF the Cart is empty when "Place order" is clicked, THEN THE Shop SHALL display a "Your cart is empty" message and SHALL NOT create an order.
5. WHEN an order is placed successfully, THE Shop SHALL display an order confirmation page showing the order ID and the purchased items.

### Requirement 9: Admin Product CRUD

**User Story:** As an Admin, I want to create, edit, and delete products, so that I can keep the catalog up to date.

#### Acceptance Criteria

1. WHEN an Admin submits the new-product form with name, description, price, category, and image, THE Admin_Panel SHALL insert the Product into the `products` table and save the uploaded image into the Image_Folder.
2. WHEN an Admin submits the edit-product form, THE Admin_Panel SHALL update the matching row in the `products` table with the new values.
3. WHEN an Admin clicks delete for a Product, THE Admin_Panel SHALL remove the Product from the `products` table after a confirmation prompt.
4. IF a submitted price is not a positive number, THEN THE Admin_Panel SHALL reject the submission and display a validation message.
5. THE Admin_Panel SHALL use prepared statements for every product create, update, and delete query.

### Requirement 10: Category Management

**User Story:** As an Admin, I want to manage product categories, so that products can be grouped into GPU, CPU, Mouse, Headset, RAM, SSD, and Cooling.

#### Acceptance Criteria

1. WHEN an Admin opens the categories page, THE Admin_Panel SHALL list every Category from the `categories` table.
2. WHEN an Admin adds a new Category with a unique name, THE Admin_Panel SHALL insert the Category into the `categories` table.
3. IF an Admin tries to add a Category whose name already exists, THEN THE Admin_Panel SHALL reject the submission and display a "Category already exists" message.
4. WHEN an Admin deletes a Category, THE Admin_Panel SHALL remove it from the `categories` table and unassign that Category from any Products that referenced it.
5. THE Admin_Panel SHALL allow each Product to be assigned to exactly one Category from the existing list.

### Requirement 11: Visual and Implementation Style

**User Story:** As the assignment reviewer, I want the project to follow the agreed simple style and tech choices, so that it matches the brief.

#### Acceptance Criteria

1. THE Shop SHALL use a simple white-background layout consistent across pages.
2. THE Shop and Admin_Panel SHALL be implemented in procedural PHP using mysqli or PDO with prepared statements for every database query.
3. THE Shop SHALL load product images from the Image_Folder, reusing the existing files (`rtx5090.png`, `rx9070xt.png`, `ryzen9950x.png`, `i9-285k.png`, `arctis-nova.png`, `corsair-h150i.png`, `gpro-superlight.png`, `gskill-ddr5.png`, `samsung990pro.png`, `wd-sn850x.png`) and `default.png` as the fallback.
4. THE Shop SHALL use PHP sessions for active-visit state and cookies for guest cart persistence, in line with the assignment requirement.
