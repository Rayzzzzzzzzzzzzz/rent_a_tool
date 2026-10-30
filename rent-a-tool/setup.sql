-- Rent-A-Tool Database Schema
-- Run this in phpMyAdmin or MySQL to set up the database

CREATE DATABASE IF NOT EXISTS rentatool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rentatool;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    profile_image VARCHAR(255) DEFAULT 'default-avatar.png',
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    login_attempts INT DEFAULT 0,
    lockout_until DATETIME NULL,
    penalty_count INT DEFAULT 0
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'tool',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tools table
CREATE TABLE IF NOT EXISTS tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category_id INT,
    owner_id INT,
    daily_rate DECIMAL(10,2) NOT NULL,
    security_deposit DECIMAL(10,2) NOT NULL,
    late_fee_per_day DECIMAL(10,2) DEFAULT 10.00,
    image VARCHAR(255) DEFAULT 'default-tool.png',
    status ENUM('available', 'rented', 'under_repair', 'pending') DEFAULT 'available',
    condition_notes TEXT,
    location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Rentals table
CREATE TABLE IF NOT EXISTS rentals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tool_id INT NOT NULL,
    renter_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    return_date DATE NULL,
    daily_rate DECIMAL(10,2) NOT NULL,
    security_deposit DECIMAL(10,2) NOT NULL,
    total_days INT NOT NULL,
    rental_fee DECIMAL(10,2) NOT NULL,
    late_fee DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    deposit_status ENUM('held', 'released', 'forfeited') DEFAULT 'held',
    rental_status ENUM('pending', 'active', 'completed', 'cancelled', 'overdue') DEFAULT 'pending',
    pre_rental_condition TEXT,
    post_rental_condition TEXT,
    condition_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
    FOREIGN KEY (renter_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type ENUM('rental', 'deposit', 'late_fee', 'refund') NOT NULL,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rental_id) REFERENCES rentals(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login attempts tracking
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE
);

-- Activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewee_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rental_id) REFERENCES rentals(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user 
Register first then when you have a account change the role of the user make it into a admin role to access the 
admin panel


-- Insert sample categories
INSERT INTO categories (name, description, icon) VALUES
('Power Tools', 'Electric and battery-powered tools', 'zap'),
('Hand Tools', 'Manual tools for various tasks', 'tool'),
('Gardening', 'Lawn and garden equipment', 'flower-2'),
('Construction', 'Heavy-duty construction equipment', 'building'),
('Automotive', 'Car repair and maintenance tools', 'car'),
('Cleaning', 'Pressure washers and cleaning equipment', 'droplet')
ON DUPLICATE KEY UPDATE id=id;

-- Insert sample users (password: user123)
INSERT INTO users (fullname, email, phone, password, role, address) VALUES
('John Smith', 'john@example.com', '555-0101', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '123 Main St, City'),
('Sarah Johnson', 'sarah@example.com', '555-0102', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '456 Oak Ave, Town'),
('Mike Davis', 'mike@example.com', '555-0103', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '789 Pine Rd, Village')
ON DUPLICATE KEY UPDATE id=id;

-- Insert sample tools
INSERT INTO tools (name, description, category_id, owner_id, daily_rate, security_deposit, late_fee_per_day, location, status) VALUES
('Cordless Drill Set', 'Professional 20V cordless drill with multiple bits and batteries', 1, 2, 25.00, 150.00, 15.00, 'Downtown', 'available'),
('Pressure Washer', '3000 PSI electric pressure washer for outdoor cleaning', 6, 3, 35.00, 200.00, 20.00, 'Uptown', 'available'),
('Lawn Mower', 'Self-propelled gas lawn mower, 21-inch cutting deck', 3, 2, 30.00, 250.00, 25.00, 'Suburbs', 'available'),
('Circular Saw', '7-1/4 inch circular saw with laser guide', 1, 1, 20.00, 120.00, 12.00, 'Midtown', 'available'),
('Hammer Drill', 'Rotary hammer drill for concrete and masonry', 1, 3, 40.00, 300.00, 30.00, 'Industrial District', 'available'),
('Garden Tiller', 'Front-tine garden tiller for soil preparation', 3, 1, 45.00, 350.00, 35.00, 'Rural Area', 'available'),
('Floor Sander', 'Drum floor sander for hardwood refinishing', 4, 2, 55.00, 500.00, 50.00, 'Downtown', 'available'),
('Air Compressor', '6-gallon pancake air compressor with nail gun kit', 5, 3, 28.00, 180.00, 18.00, 'Westside', 'available'),
('Wet/Dry Vacuum', '16-gallon wet/dry shop vacuum', 6, 1, 18.00, 100.00, 10.00, 'Eastside', 'available'),
('Tile Cutter', 'Professional wet tile saw with diamond blade', 4, 2, 32.00, 220.00, 22.00, 'Northside', 'available');

-- Create indexes for better performance
CREATE INDEX idx_tools_status ON tools(status);
CREATE INDEX idx_tools_category ON tools(category_id);
CREATE INDEX idx_rentals_renter ON rentals(renter_id);
CREATE INDEX idx_rentals_tool ON rentals(tool_id);
CREATE INDEX idx_rentals_status ON rentals(rental_status);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_payments_rental ON payments(rental_id);
