
-- Ashesi Student Marketplace — Database Schema

CREATE DATABASE IF NOT EXISTS ashesi_market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ashesi_market;

-- CATEGORIES
CREATE TABLE categories (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(80) NOT NULL,
    slug      VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categories (name, slug) VALUES
  ('Food & Drinks',     'food-drinks'),
  ('Electronics',       'electronics'),
  ('Clothing',          'clothing'),
  ('Books & Notes',     'books-notes'),
  ('Services',          'services'),
  ('Accessories',       'accessories'),
  ('Home & Living',     'home-living'),
  ('Other',             'other');

-- USERS
CREATE TABLE users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(120)  NOT NULL,
    email            VARCHAR(180)  NOT NULL UNIQUE,
    password_hash    VARCHAR(255)  NOT NULL,
    phone_whatsapp   VARCHAR(20)   DEFAULT NULL,          -- used for WhatsApp button
    year_group       VARCHAR(20)   DEFAULT NULL,          
    bio              TEXT          DEFAULT NULL,
    id_image         VARCHAR(255)  DEFAULT NULL,          -- path to uploaded ID image
    role             ENUM('buyer','seller','both') NOT NULL DEFAULT 'buyer',
    is_verified      TINYINT(1)   NOT NULL DEFAULT 0,    -- admin can flip this later
    profile_complete TINYINT(1)   NOT NULL DEFAULT 0,
    avg_rating       DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    review_count     INT          NOT NULL DEFAULT 0,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- PRODUCTS
CREATE TABLE products (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    seller_id    INT            NOT NULL,
    category_id  INT            NOT NULL,
    title        VARCHAR(200)   NOT NULL,
    description  TEXT           NOT NULL,
    price        DECIMAL(10,2)  NOT NULL,
    quantity     INT            NOT NULL DEFAULT 1,
    `condition`  ENUM('new','like_new','good','fair') NOT NULL DEFAULT 'good',
    location     VARCHAR(120)   DEFAULT NULL,             -- e.g. "Main Cafeteria", "Block C"
    is_available TINYINT(1)    NOT NULL DEFAULT 1,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id)   REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)  ON DELETE RESTRICT
) ENGINE=InnoDB;

-- PRODUCT IMAGES
CREATE TABLE product_images (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    product_id   INT          NOT NULL,
    image_path   VARCHAR(255) NOT NULL,
    is_primary   TINYINT(1)  NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- CART & CART ITEMS (one cart per user, persisted in DB)
CREATE TABLE cart (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT       NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_items (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    cart_id    INT NOT NULL,
    product_id INT NOT NULL,
    quantity   INT NOT NULL DEFAULT 1,
    UNIQUE KEY uq_cart_product (cart_id, product_id),
    FOREIGN KEY (cart_id)    REFERENCES cart(id)     ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ORDERS & ORDER ITEMS
CREATE TABLE orders (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id     INT           NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status       ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT           NOT NULL,
    product_id  INT           NOT NULL,
    seller_id   INT           NOT NULL,
    quantity    INT           NOT NULL,
    unit_price  DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (seller_id)  REFERENCES users(id)    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- REVIEWS  (tied to order_item — enforces "must have bought")
CREATE TABLE reviews (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT  NOT NULL UNIQUE,          -- one review per order item
    reviewer_id   INT  NOT NULL,
    seller_id     INT  NOT NULL,
    rating        TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment       TEXT DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id)   REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (seller_id)     REFERENCES users(id)       ON DELETE CASCADE
) ENGINE=InnoDB;
