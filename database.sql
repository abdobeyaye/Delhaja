-- ============================================
-- Delivery Pro System - Database Schema v2.0
-- Phone + Password Authentication
-- Mauritanian Phone Format: 8 digits starting with 2, 3, or 4
-- ============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================
-- 1. USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS users1 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_no VARCHAR(15) UNIQUE COMMENT 'Format: XX-YYMM-00001 (CL/DR/AD)',
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL COMMENT 'Hashed with password_hash()',
    role ENUM('admin','driver','customer') NOT NULL,
    points INT DEFAULT 0 COMMENT 'Driver balance for accepting orders',
    status ENUM('active','banned') DEFAULT 'active',
    full_name VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL UNIQUE COMMENT 'Mauritanian: 8 digits starting with 2/3/4',
    phone_verified TINYINT(1) DEFAULT 0,
    email VARCHAR(100) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    avatar_url VARCHAR(255) DEFAULT NULL COMMENT 'Profile picture path',
    rating DECIMAL(3,2) DEFAULT 5.00 COMMENT 'Average rating 1-5',
    total_orders INT DEFAULT 0 COMMENT 'Total orders completed',
    total_earnings INT DEFAULT 0 COMMENT 'Total points earned',
    is_online TINYINT(1) DEFAULT 0 COMMENT 'Driver availability status',
    is_verified TINYINT(1) DEFAULT 0 COMMENT 'Admin-verified driver',
    last_lat DECIMAL(10,8) DEFAULT NULL COMMENT 'Last known latitude',
    last_lng DECIMAL(11,8) DEFAULT NULL COMMENT 'Last known longitude',
    location_updated_at TIMESTAMP NULL COMMENT 'When location was last updated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_role_status (role, status),
    INDEX idx_serial (serial_no),
    INDEX idx_online (is_online, role),
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. ORDERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS orders1 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT DEFAULT NULL COMMENT 'Link to users1.id',
    customer_name VARCHAR(50) NOT NULL COMMENT 'Customer username/display name',
    details TEXT NOT NULL COMMENT 'Order details/items',
    address VARCHAR(255) NOT NULL COMMENT 'Delivery address',
    client_phone VARCHAR(20) DEFAULT NULL COMMENT 'Customer phone number',
    pickup_lat DECIMAL(10,8) DEFAULT NULL,
    pickup_lng DECIMAL(11,8) DEFAULT NULL,
    dropoff_lat DECIMAL(10,8) DEFAULT NULL,
    dropoff_lng DECIMAL(11,8) DEFAULT NULL,
    distance_km DECIMAL(6,2) DEFAULT NULL,
    status ENUM('pending','accepted','picked_up','delivered','cancelled') DEFAULT 'pending',
    driver_id INT DEFAULT NULL COMMENT 'Assigned driver users1.id',
    delivery_code VARCHAR(10) DEFAULT NULL COMMENT '4-digit PIN for delivery verification',
    points_cost INT DEFAULT 0 COMMENT 'Points deducted from driver',
    pickup_district_id INT DEFAULT NULL COMMENT 'Pickup district ID',
    delivery_district_id INT DEFAULT NULL COMMENT 'Delivery district ID',
    delivery_fee INT DEFAULT 100 COMMENT 'Delivery fee in MRU',
    detailed_address VARCHAR(500) DEFAULT NULL COMMENT 'Detailed delivery address',
    accepted_at TIMESTAMP NULL,
    picked_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    cancel_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_driver (driver_id),
    INDEX idx_client (client_id),
    INDEX idx_customer (customer_name),
    INDEX idx_created (created_at),
    INDEX idx_pickup_district (pickup_district_id),
    INDEX idx_delivery_district (delivery_district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. SERIAL NUMBER COUNTERS
-- ============================================
CREATE TABLE IF NOT EXISTS serial_counters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prefix CHAR(2) NOT NULL COMMENT 'CL, DR, AD',
    `year_month` CHAR(4) NOT NULL COMMENT 'YYMM format',
    current_count INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_prefix_month (prefix, `year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. RATINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    rater_id INT NOT NULL COMMENT 'User who gave the rating',
    ratee_id INT NOT NULL COMMENT 'User who received the rating',
    score TINYINT NOT NULL COMMENT 'Rating 1-5',
    comment TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ratee (ratee_id),
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. ORDER TRACKING (Real-time location)
-- ============================================
CREATE TABLE IF NOT EXISTS order_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    driver_id INT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    accuracy FLOAT DEFAULT NULL COMMENT 'GPS accuracy in meters',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order_time (order_id, recorded_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. DISTRICTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'District name (French)',
    name_ar VARCHAR(100) NOT NULL COMMENT 'District name (Arabic)',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Active status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert 10 Mauritanian districts
INSERT INTO districts (id, name, name_ar) VALUES
(1, 'Tevragh Zeina', 'تفرغ زينة'),
(2, 'Ksar', 'لكصر'),
(3, 'Sebkha', 'سبخة'),
(4, 'Teyarett', 'تيارت'),
(5, 'Dar Naïm', 'دار النعيم'),
(6, 'Toujounine', 'توجنين'),
(7, 'Arafat', 'عرفات'),
(8, 'El Mina', 'الميناء'),
(9, 'Riyad', 'الرياض'),
(10, 'Tarhil', 'الترحيل');

-- ============================================
-- 7. DISTRICT PRICES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS district_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_district_id INT NOT NULL COMMENT 'Source district ID',
    to_district_id INT NOT NULL COMMENT 'Destination district ID',
    price INT NOT NULL DEFAULT 100 COMMENT 'Delivery price in MRU',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_route (from_district_id, to_district_id),
    INDEX idx_from (from_district_id),
    INDEX idx_to (to_district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert exact pricing data
-- Tevragh Zeina (ID: 1)
INSERT INTO district_prices (from_district_id, to_district_id, price) VALUES
(1, 1, 100), (1, 2, 100), (1, 3, 100), (1, 8, 150), (1, 7, 150), (1, 4, 150),
(1, 5, 200), (1, 6, 200), (1, 9, 200), (1, 10, 200),
-- Ksar (ID: 2)
(2, 2, 100), (2, 1, 100), (2, 4, 100), (2, 5, 100), (2, 3, 100),
(2, 6, 150), (2, 7, 150), (2, 8, 150), (2, 9, 200), (2, 10, 200),
-- Sebkha (ID: 3)
(3, 3, 100), (3, 8, 100), (3, 1, 100), (3, 2, 100),
(3, 7, 150), (3, 9, 150), (3, 4, 200), (3, 5, 200), (3, 6, 200), (3, 10, 200),
-- Teyarett (ID: 4)
(4, 4, 100), (4, 5, 100), (4, 2, 100),
(4, 1, 150), (4, 6, 150),
(4, 3, 200), (4, 7, 200), (4, 8, 200), (4, 9, 200), (4, 10, 200),
-- Dar Naïm (ID: 5)
(5, 5, 100), (5, 4, 100), (5, 6, 100), (5, 2, 100),
(5, 7, 150),
(5, 1, 200), (5, 3, 200), (5, 8, 200), (5, 9, 200), (5, 10, 200),
-- Toujounine (ID: 6)
(6, 6, 100), (6, 5, 100), (6, 7, 100),
(6, 2, 150), (6, 4, 150), (6, 9, 150),
(6, 1, 200), (6, 3, 200), (6, 8, 200), (6, 10, 200),
-- Arafat (ID: 7)
(7, 7, 100), (7, 6, 100), (7, 8, 100), (7, 9, 100),
(7, 2, 150), (7, 1, 150), (7, 3, 150), (7, 5, 150),
(7, 4, 200), (7, 10, 200),
-- El Mina (ID: 8)
(8, 8, 100), (8, 3, 100), (8, 7, 100), (8, 9, 100),
(8, 1, 150), (8, 2, 150),
(8, 6, 200), (8, 5, 200), (8, 4, 200), (8, 10, 200),
-- Riyad (ID: 9)
(9, 9, 100), (9, 7, 100), (9, 8, 100),
(9, 3, 150), (9, 6, 150),
(9, 1, 200), (9, 2, 200), (9, 5, 200), (9, 4, 200), (9, 10, 200),
-- Tarhil (ID: 10) - Default 200 to all
(10, 10, 100),
(10, 1, 200), (10, 2, 200), (10, 3, 200), (10, 4, 200), (10, 5, 200),
(10, 6, 200), (10, 7, 200), (10, 8, 200), (10, 9, 200);

-- ============================================
-- 8. DRIVER DISTRICTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS driver_districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL COMMENT 'Link to users1.id',
    district_id INT NOT NULL COMMENT 'Link to districts.id',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_driver_district (driver_id, district_id),
    INDEX idx_driver (driver_id),
    INDEX idx_district (district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT USERS
-- Login with phone number + password
-- Mauritanian phone: 8 digits starting with 2, 3, or 4
-- ============================================
-- Password for all: 123 (hashed)

INSERT INTO users1 (serial_no, username, password, role, points, status, full_name, phone, phone_verified, is_verified) VALUES
('AD-2501-00001', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0, 'active', 'Administrator', '20000001', 1, 1),
('DR-2501-00001', 'driver', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', 50, 'active', 'Demo Driver', '30000002', 1, 1),
('CL-2501-00001', 'client', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 0, 'active', 'Demo Client', '40000003', 1, 0);

-- ============================================
-- DEMO ACCOUNTS LOGIN INFO:
-- ============================================
-- Admin:    Phone: 20000001  Password: 123
-- Driver:   Phone: 30000002  Password: 123
-- Client:   Phone: 40000003  Password: 123
-- ============================================
-- Phone format: 8 digits starting with 2, 3, or 4
-- ============================================
