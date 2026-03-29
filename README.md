# Ashesi Market — Student Marketplace

A campus e-commerce web app for Ashesi University students to buy and sell.

---

## Tech Stack

- **Backend:** PHP 8+ with MySQLi (prepared statements)
- **Database:** MySQL 8
- **Frontend:** HTML5, CSS3, Vanilla JS
- **Auth:** Sessions + `password_hash()` + CSRF tokens

---

## Setup Instructions

### 1. Requirements
- PHP 8.0+
- MySQL 8.0+
- Apache/Nginx with `mod_rewrite` (or XAMPP/Laragon/MAMP)

### 2. Database
```sql
-- Run schema file:
mysql -u root -p < sql/schema.sql
```

### 3. Configuration
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'ashesi_market');
```

Edit `config/config.php`:
```php
define('BASE_URL', 'http://localhost/ashesi_market');
```

### 4. Upload Folder Permissions
```bash
chmod -R 755 assets/uploads/
```
Or ensure your web server can write to `assets/uploads/`.

### 5. Place in web root
```
htdocs/ashesi_market/   ← XAMPP
www/ashesi_market/      ← Laragon
```

---

## Project Structure

```
ashesi_market/
├── index.php                  ← Homepage
├── config/
│   ├── config.php             ← App constants
│   └── db.php                 ← DB connection
├── includes/
│   ├── functions.php          ← CSRF, auth, helpers
│   ├── header.php             ← Navbar partial
│   └── footer.php             ← Footer partial
├── pages/
│   ├── register.php           ← Registration
│   ├── login.php              ← Login
│   ├── logout.php             ← Logout
│   ├── reset_password.php     ← Password reset
│   ├── setup_profile.php      ← Complete profile
│   ├── profile.php            ← Public profile + listings + reviews
│   ├── product.php            ← Product detail
│   ├── product_form.php       ← Add / Edit product
│   ├── delete_product.php     ← Delete product handler
│   ├── delete_image.php       ← Delete image handler
│   ├── search.php             ← Search + filter browse
│   ├── cart.php               ← Cart view
│   ├── cart_action.php        ← Add/update/remove cart (POST)
│   ├── checkout.php           ← Checkout flow
│   ├── order_confirm.php      ← Order confirmation + WhatsApp links
│   ├── orders.php             ← Order history (buyer + seller views)
│   └── submit_review.php      ← Submit review handler
├── assets/
│   ├── css/main.css           ← Styles
│   ├── js/main.js             ← Client JS
│   └── uploads/
│       ├── products/          ← Product images
│       └── id_images/         ← ID verification images
└── sql/
    ├── schema.sql             ← Full DB schema with seed data
    └── erd_plantuml.puml      ← PlantUML ER diagram source
```

---

## Database Tables

| Table | Purpose |
|---|---|
| `users` | All users (buyers & sellers), hashed passwords, WhatsApp number, ratings |
| `categories` | Product categories (pre-seeded with 8 categories) |
| `products` | Product listings with price, qty, condition, location |
| `product_images` | Multiple images per product; `is_primary` flags the cover |
| `cart` | One cart row per user |
| `cart_items` | Items in each cart |
| `orders` | Order header with status lifecycle |
| `order_items` | Line items per order, references seller_id for sales tracking |
| `reviews` | 1-5 star reviews tied to `order_item_id` (enforces purchase gate) |

---

## Security Notes

- All queries use **MySQLi prepared statements** — no raw interpolation
- Passwords use **`password_hash()` with BCRYPT**
- Every POST form has a **CSRF token** checked with `hash_equals()`
- Session regenerated on login (`session_regenerate_id(true)`)
- File uploads: type-checked (JPEG/PNG/WebP), size-capped (3 MB), renamed to random hex
- Sellers cannot buy their own products (checked server-side)
- Reviews gated behind completed order + `order_item_id UNIQUE` (no duplicates)
- Self-rating prevented (server-side check)

---

## WhatsApp Integration

Buyer contacts seller via pre-filled WhatsApp URL:
```
https://wa.me/{phone}?text={encoded_message}
```
- Product detail page → contact seller about listing
- Order confirmation → contact each seller per order item
- Orders page (sales tab) → seller contacts buyer

No server messages, no notifications needed.

---

## Known Limitations (for class scope)
- No email verification (uses name+email for password reset)
- No admin panel (can extend later)
- Payment is cash on pickup (no payment gateway)
- No real-time stock reservation (race condition on very high traffic — acceptable for campus scale)

---

## Railway Deployment (Step by Step)

This project is now ready for Railway deployment using Docker and Railway MySQL.

### Stage 1: Push code to GitHub
- Commit and push this repository to GitHub.
- Ensure these deployment files are in the root:
    - `Dockerfile`
    - `.dockerignore`
    - `.env.example`

### Stage 2: Create Railway project and web service
- In Railway, create a new project.
- Choose **Deploy from GitHub Repo**.
- Select this repository.
- Railway will detect `Dockerfile` and build the app automatically.

### Stage 3: Add Railway MySQL service
- Inside the same Railway project, add a **MySQL** service.
- Railway creates credentials as service variables, including:
    - `MYSQLHOST`
    - `MYSQLPORT`
    - `MYSQLUSER`
    - `MYSQLPASSWORD`
    - `MYSQLDATABASE`

### Stage 4: Set web service environment variables
In the Railway web service variables tab, set:

- `DB_HOST=${{MySQL.MYSQLHOST}}`
- `DB_PORT=${{MySQL.MYSQLPORT}}`
- `DB_USER=${{MySQL.MYSQLUSER}}`
- `DB_PASS=${{MySQL.MYSQLPASSWORD}}`
- `DB_NAME=${{MySQL.MYSQLDATABASE}}`

Set BASE_URL after first deploy when Railway gives your public domain.

### Stage 5: Import the schema
- Connect to Railway MySQL using MySQL Workbench, DBeaver, or mysql CLI.
- Run `sql/schema.sql`.
- If your DB already exists, remove or skip these two lines before import:
    - `CREATE DATABASE IF NOT EXISTS ashesi_market ...;`
    - `USE ashesi_market;`

### Stage 6: Set BASE_URL and redeploy
- Copy your Railway public URL.
- Set:
    - `BASE_URL=https://your-app-domain.up.railway.app`
- Trigger a redeploy.

### Stage 7: Verify production flow
- Register and login.
- Create a listing with image upload.
- Search products, add to cart, checkout.
- Confirm orders and reviews.

### Stage 8: Handle upload persistence
- Upload files are stored in `assets/uploads/`.
- For production persistence, use a Railway volume or move uploads to object storage.
