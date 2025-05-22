CREATE TABLE food_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL, -- e.g., 'Món khai vị', 'Trái cây'
    description TEXT NULL, -- Can store longer descriptions or 'n' or filenames
    price INT UNSIGNED NOT NULL DEFAULT 0, -- Assuming whole number currency
    discount_price INT UNSIGNED NULL,
    discount_percent TINYINT UNSIGNED NULL, -- Assuming percentage 0-100
    image VARCHAR(255) NULL, -- For storing image path/filename
    rate TINYINT UNSIGNED NULL, -- Assuming a rating like 1-5, NULL if not rated
    is_available BOOLEAN NOT NULL DEFAULT TRUE, -- Or TINYINT(1)
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) NULL,
    user_id INT UNSIGNED NULL, -- Assuming user IDs are positive integers; NULL for guest users
    username VARCHAR(255) NULL, -- Can store username or email, or be NULL for anonymous guests
    food_id INT UNSIGNED NOT NULL, -- Assuming food IDs are positive integers
    food_name VARCHAR(255) NOT NULL, -- Denormalized: name of the food at the time of adding
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    price_at_add INT UNSIGNED NOT NULL, -- Denormalized: price per unit at the time of adding
    status VARCHAR(50) NOT NULL DEFAULT 'cart', -- e.g., 'cart', 'pending', 'ordered'
    transaction_id VARCHAR(255) NULL, -- For linking to a payment transaction
    recipient_name VARCHAR(255) NULL,
    recipient_phone VARCHAR(30) NULL, -- Allows for various phone number formats
    recipient_address TEXT NULL, -- Addresses can be long
    payment_method VARCHAR(50) NULL, -- e.g., 'online', 'cod'
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    order_total INT UNSIGNED NULL -- Assuming this might be the total for an entire order these items belong to, populated later
);
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NULL, -- Storing hashed passwords, can be NULL for social logins
    role VARCHAR(50) NOT NULL DEFAULT 'customer', -- e.g., 'customer', 'admin'
    full_name VARCHAR(255) NULL,
    phone_number VARCHAR(30) NULL,
    address TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    google_id VARCHAR(255) UNIQUE NULL, -- For Google OAuth sign-in
    reset_token_hash VARCHAR(255) NULL, -- For password reset functionality
    reset_token_expires_at DATETIME NULL -- Expiry for the reset token
);
CREATE TABLE user_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    dish_id INT UNSIGNED NULL, -- Foreign key to a 'dishes' or 'foods' table
    dish_name_manual VARCHAR(255) NULL, -- If the dish name is entered manually or dish_id is not available
    rating TINYINT UNSIGNED NULL, -- Assuming a rating scale, e.g., 1-5
    feedback TEXT NULL, -- For the textual feedback content
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN NOT NULL DEFAULT FALSE, -- Or TINYINT(1) DEFAULT 0
    is_approved BOOLEAN NOT NULL DEFAULT FALSE -- Or TINYINT(1) DEFAULT 0
);