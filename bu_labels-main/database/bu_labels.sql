-- database/bu_labels.sql

-- Create database
CREATE DATABASE IF NOT EXISTS bu_labels;
USE bu_labels;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    campus ENUM('main', 'east', 'west', 'north', 'south') DEFAULT 'main',
    role ENUM('customer', 'admin', 'director') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category_id INT,
    sizes JSON, -- Store available sizes as JSON array
    stock_main INT DEFAULT 0,
    stock_east INT DEFAULT 0,
    stock_west INT DEFAULT 0,
    stock_north INT DEFAULT 0,
    stock_south INT DEFAULT 0,
    featured BOOLEAN DEFAULT FALSE,
    image_main VARCHAR(255),
    image_gallery JSON, -- Store multiple images as JSON array
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('gcash', 'cod', 'bank_transfer') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    order_status ENUM('processing', 'ready_for_pickup', 'picked_up', 'cancelled') DEFAULT 'processing',
    pickup_campus ENUM('main', 'east', 'west', 'north', 'south') NOT NULL,
    pickup_location VARCHAR(255),
    estimated_pickup DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Order items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT,
    product_id INT,
    size VARCHAR(10),
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Cart table (for logged-in users)
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT,
    size VARCHAR(10),
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, product_id, size)
);

-- Wishlist table
CREATE TABLE wishlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id)
);

-- Insert sample admin user
INSERT INTO users (name, email, password, role) VALUES 
('Admin User', 'admin@bulabels.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Campus Director', 'director@bulabels.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'director'),
('John Student', 'student@bulabels.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer');

-- Insert sample categories
INSERT INTO categories (name, slug, image) VALUES 
('Apparel', 'apparel', 'category-1.png'),
('Accessories', 'accessories', 'category-2.png'),
('Limited Edition', 'limited-edition', 'category-13.png'),
('Best Sellers', 'best-sellers', 'category-4.png');

-- Insert sample products
INSERT INTO products (name, slug, description, price, category_id, sizes, stock_main, featured, image_main) VALUES 
('BUnique Varsity Jacket', 'bunique-varsity-jacket', 'Premium quality varsity jacket with university colors', 50.00, 1, '["S", "M", "L", "XL"]', 50, 1, 'product-1.jpg'),
('BU Labels Hoodie', 'bu-labels-hoodie', 'Comfortable hoodie with BU Labels branding', 40.00, 1, '["S", "M", "L", "XL"]', 75, 1, 'product-2.jpg'),
('University Lanyard', 'university-lanyard', 'Official university lanyard with detachable buckle', 5.00, 2, '["One Size"]', 200, 0, 'category-2.png'),
('Limited Edition Windbreaker', 'limited-windbreaker', 'Exclusive windbreaker design', 60.00, 3, '["S", "M", "L"]', 30, 1, 'wb.png'),
('BU T-Shirt', 'bu-t-shirt', 'Cotton t-shirt with university logo', 25.00, 1, '["XS", "S", "M", "L", "XL"]', 100, 1, 'product-3.jpg');