<?php
/**
 * Configuration File - Delivery Pro System v2.0
 * Database connection, session management, and application constants
 */

// ==========================================
// DATABASE CONFIGURATION
// ==========================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'delivery_pro');

// ==========================================
// APPLICATION CONSTANTS
// ==========================================
$order_expiry_hours = 3; // Auto-cancel pending orders after 3 hours
$points_cost_per_order = 10; // Points cost for driver to accept an order
$driver_max_active_orders = 5; // Maximum active orders per driver

// Uploads directory
$uploads_dir = __DIR__ . '/uploads';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

// ==========================================
// PDO DATABASE CONNECTION
// ==========================================
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ==========================================
// DATABASE INITIALIZATION
// ==========================================

// Create users1 table
$conn->exec("CREATE TABLE IF NOT EXISTS users1 (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create orders1 table (with district_id and detailed_address, WITHOUT promo fields)
$conn->exec("CREATE TABLE IF NOT EXISTS orders1 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT DEFAULT NULL COMMENT 'Link to users1.id',
    customer_name VARCHAR(50) NOT NULL COMMENT 'Customer username/display name',
    details TEXT NOT NULL COMMENT 'Order details/items',
    address VARCHAR(255) NOT NULL COMMENT 'Delivery address',
    detailed_address TEXT DEFAULT NULL COMMENT 'Detailed address from customer',
    client_phone VARCHAR(20) DEFAULT NULL COMMENT 'Customer phone number',
    district_id INT DEFAULT NULL COMMENT 'District/commune ID',
    pickup_lat DECIMAL(10,8) DEFAULT NULL,
    pickup_lng DECIMAL(11,8) DEFAULT NULL,
    dropoff_lat DECIMAL(10,8) DEFAULT NULL,
    dropoff_lng DECIMAL(11,8) DEFAULT NULL,
    distance_km DECIMAL(6,2) DEFAULT NULL,
    status ENUM('pending','accepted','picked_up','delivered','cancelled') DEFAULT 'pending',
    driver_id INT DEFAULT NULL COMMENT 'Assigned driver users1.id',
    delivery_code VARCHAR(10) DEFAULT NULL COMMENT '4-digit PIN for delivery verification',
    points_cost INT DEFAULT 0 COMMENT 'Points deducted from driver',
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
    INDEX idx_district (district_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create serial_counters table
$conn->exec("CREATE TABLE IF NOT EXISTS serial_counters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prefix CHAR(2) NOT NULL COMMENT 'CL, DR, AD',
    `year_month` CHAR(4) NOT NULL COMMENT 'YYMM format',
    current_count INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_prefix_month (prefix, `year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create ratings table
$conn->exec("CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    rater_id INT NOT NULL COMMENT 'User who gave the rating',
    ratee_id INT NOT NULL COMMENT 'User who received the rating',
    score TINYINT NOT NULL COMMENT 'Rating 1-5',
    comment TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ratee (ratee_id),
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create order_tracking table
$conn->exec("CREATE TABLE IF NOT EXISTS order_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    driver_id INT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    accuracy FLOAT DEFAULT NULL COMMENT 'GPS accuracy in meters',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order_time (order_id, recorded_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create districts table (pre-populated with Nouakchott districts)
$conn->exec("CREATE TABLE IF NOT EXISTS districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create driver_districts table (driver-district relationship)
$conn->exec("CREATE TABLE IF NOT EXISTS driver_districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    district_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_driver_district (driver_id, district_id),
    INDEX idx_driver (driver_id),
    INDEX idx_district (district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Pre-populate districts with Nouakchott's 10 districts
$check_districts = $conn->query("SELECT COUNT(*) FROM districts")->fetchColumn();
if ($check_districts == 0) {
    $districts = [
        ['Tevragh Zeina', 'تفرغ زينة'],
        ['Ksar', 'لكصر'],
        ['Dar Naïm', 'دار النعيم'],
        ['Toujounine', 'توجنين'],
        ['Arafat', 'عرفات'],
        ['Riyad', 'الرياض'],
        ['El Mina', 'الميناء'],
        ['Sebkha', 'سبخة'],
        ['Teyarett', 'تيارت'],
        ['Tarhil', 'الترحيل']
    ];
    
    $stmt = $conn->prepare("INSERT INTO districts (name, name_ar, is_active) VALUES (?, ?, 1)");
    foreach ($districts as $district) {
        $stmt->execute($district);
    }
}

// Check if demo users exist
$check_users = $conn->query("SELECT COUNT(*) FROM users1")->fetchColumn();
if ($check_users == 0) {
    // Password for all demo users: 123 (hashed)
    $hashed_pass = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    
    $conn->exec("INSERT INTO users1 (serial_no, username, password, role, points, status, full_name, phone, phone_verified, is_verified) VALUES
        ('AD-2501-00001', 'admin', '{$hashed_pass}', 'admin', 0, 'active', 'Administrator', '20000001', 1, 1),
        ('DR-2501-00001', 'driver', '{$hashed_pass}', 'driver', 50, 'active', 'Demo Driver', '30000002', 1, 1),
        ('CL-2501-00001', 'client', '{$hashed_pass}', 'customer', 0, 'active', 'Demo Client', '40000003', 1, 0)");
}

// Helper function to generate serial numbers
function generateSerialNumber($conn, $role) {
    $prefix_map = ['admin' => 'AD', 'driver' => 'DR', 'customer' => 'CL'];
    $prefix = $prefix_map[$role];
    $year_month = date('ym');
    
    // Use transaction for atomic increment
    $conn->beginTransaction();
    try {
        // Get or create counter
        $stmt = $conn->prepare("INSERT INTO serial_counters (prefix, year_month, current_count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE current_count = current_count + 1");
        $stmt->execute([$prefix, $year_month]);
        
        // Get current count
        $stmt = $conn->prepare("SELECT current_count FROM serial_counters WHERE prefix=? AND year_month=?");
        $stmt->execute([$prefix, $year_month]);
        $count = $stmt->fetchColumn();
        
        $conn->commit();
        
        return sprintf('%s-%s-%05d', $prefix, $year_month, $count);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// ==========================================
// SESSION MANAGEMENT
// ==========================================
session_start();
?>