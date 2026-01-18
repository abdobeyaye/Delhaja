<?php
/**
 * Configuration File - Delivery Pro System
 * Database connection, session management, and global settings
 */

// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// Set UTF-8 encoding
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Africa/Nouakchott');

// ==========================================
// DATABASE CONFIGURATION
// ==========================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'delivery_pro');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Connect to database using PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ==========================================
// SYSTEM SETTINGS
// ==========================================

// Points system - cost per order for driver
$points_cost_per_order = 20;

// Driver settings
$driver_max_active_orders = 3; // Maximum active orders per driver

// Order expiry time (hours)
$order_expiry_hours = 3; // Auto-cancel pending orders after 3 hours

// Delivery fee settings (MRU - Mauritanian Ouguiya)
define('DELIVERY_FEE_SAME_ZONE', 100);      // Same zone or adjacent districts
define('DELIVERY_FEE_ADJACENT_ZONE', 150);  // 1 zone difference
define('DELIVERY_FEE_FAR_ZONE', 200);       // 2+ zones difference

// File upload settings
$upload_dir = 'uploads/';
$max_file_size = 5 * 1024 * 1024; // 5 MB
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

// ==========================================
// CREATE TABLES IF NOT EXISTS
// ==========================================

// Users table
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

// Districts table with zone-based pricing
$conn->exec("CREATE TABLE IF NOT EXISTS districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    zone INT DEFAULT 1 COMMENT 'Zone grouping for distance calculation (1-4)',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_zone (zone),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Driver districts table (many-to-many relationship)
$conn->exec("CREATE TABLE IF NOT EXISTS driver_districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    district_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_driver_district (driver_id, district_id),
    INDEX idx_driver (driver_id),
    INDEX idx_district (district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Orders table with district-based delivery
$conn->exec("CREATE TABLE IF NOT EXISTS orders1 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT DEFAULT NULL COMMENT 'Link to users1.id',
    customer_name VARCHAR(50) NOT NULL COMMENT 'Customer username/display name',
    details TEXT NOT NULL COMMENT 'Order details/items',
    address VARCHAR(255) NOT NULL COMMENT 'Delivery address',
    detailed_address VARCHAR(500) DEFAULT NULL COMMENT 'Detailed delivery address',
    client_phone VARCHAR(20) DEFAULT NULL COMMENT 'Customer phone number',
    pickup_district_id INT DEFAULT NULL COMMENT 'Pickup district',
    delivery_district_id INT DEFAULT NULL COMMENT 'Delivery district',
    delivery_fee INT DEFAULT 100 COMMENT 'Delivery fee in MRU (100-200)',
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
    INDEX idx_created (created_at),
    INDEX idx_pickup_district (pickup_district_id),
    INDEX idx_delivery_district (delivery_district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Serial number counters
$conn->exec("CREATE TABLE IF NOT EXISTS serial_counters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prefix CHAR(2) NOT NULL COMMENT 'CL, DR, AD',
    `year_month` CHAR(4) NOT NULL COMMENT 'YYMM format',
    current_count INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_prefix_month (prefix, `year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ratings table
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

// Order tracking (real-time location)
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

// ==========================================
// INSERT DEFAULT DISTRICTS IF EMPTY
// ==========================================
$district_check = $conn->query("SELECT COUNT(*) FROM districts");
if ($district_check->fetchColumn() == 0) {
    $districts = [
        // Zone 1 (Central) - 100 MRU within zone
        ['Tevragh Zeina', 'تفرغ زينة', 1],
        ['Ksar', 'لكصر', 1],
        ['Sebkha', 'سبخة', 1],
        
        // Zone 2 (North) - 150 MRU to/from Zone 1, 100 MRU within zone
        ['Dar Naïm', 'دار النعيم', 2],
        ['Toujounine', 'توجنين', 2],
        ['Teyarett', 'تيارت', 2],
        
        // Zone 3 (South) - 150 MRU to/from Zone 1, 100 MRU within zone
        ['Arafat', 'عرفات', 3],
        ['Riyad', 'الرياض', 3],
        ['El Mina', 'الميناء', 3],
        
        // Zone 4 (Outer) - 200 MRU to/from other zones
        ['Tarhil', 'الترحيل', 4]
    ];
    
    $stmt = $conn->prepare("INSERT INTO districts (name, name_ar, zone, is_active) VALUES (?, ?, ?, 1)");
    foreach ($districts as $district) {
        $stmt->execute($district);
    }
}

// ==========================================
// INSERT DEFAULT USERS IF EMPTY
// ==========================================
$user_check = $conn->query("SELECT COUNT(*) FROM users1");
if ($user_check->fetchColumn() == 0) {
    // Password: 123 (hashed)
    $hashed_pass = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    
    $default_users = [
        ['AD-2501-00001', 'admin', $hashed_pass, 'admin', 0, 'Administrator', '20000001', 1, 1],
        ['DR-2501-00001', 'driver', $hashed_pass, 'driver', 50, 'Demo Driver', '30000002', 1, 1],
        ['CL-2501-00001', 'client', $hashed_pass, 'customer', 0, 'Demo Client', '40000003', 1, 0]
    ];
    
    $stmt = $conn->prepare("INSERT INTO users1 (serial_no, username, password, role, points, full_name, phone, phone_verified, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($default_users as $user) {
        $stmt->execute($user);
    }
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

/**
 * Generate unique serial number for users
 * Format: XX-YYMM-00001
 * XX = CL (Customer), DR (Driver), AD (Admin)
 */
function generateSerialNumber($conn, $role) {
    $prefix = ($role == 'customer') ? 'CL' : (($role == 'driver') ? 'DR' : 'AD');
    $yearMonth = date('ym');
    
    // Use transaction to prevent race conditions
    $conn->beginTransaction();
    
    try {
        // Lock and get current count
        $stmt = $conn->prepare("SELECT current_count FROM serial_counters WHERE prefix = ? AND year_month = ? FOR UPDATE");
        $stmt->execute([$prefix, $yearMonth]);
        $row = $stmt->fetch();
        
        if ($row) {
            $count = $row['current_count'] + 1;
            $conn->prepare("UPDATE serial_counters SET current_count = ? WHERE prefix = ? AND year_month = ?")
                 ->execute([$count, $prefix, $yearMonth]);
        } else {
            $count = 1;
            $conn->prepare("INSERT INTO serial_counters (prefix, year_month, current_count) VALUES (?, ?, ?)")
                 ->execute([$prefix, $yearMonth, $count]);
        }
        
        $conn->commit();
        return sprintf("%s-%s-%05d", $prefix, $yearMonth, $count);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

/**
 * Check if user's phone is verified
 */
function isPhoneVerified($user) {
    return !empty($user['phone']) && $user['phone_verified'] == 1;
}

/**
 * Calculate delivery fee based on zone difference
 * @param int $zone1 First district zone
 * @param int $zone2 Second district zone
 * @return int Delivery fee in MRU
 */
function calculateDeliveryFee($zone1, $zone2) {
    $zone_diff = abs($zone1 - $zone2);
    
    if ($zone_diff == 0) {
        return DELIVERY_FEE_SAME_ZONE; // Same zone
    } elseif ($zone_diff == 1) {
        return DELIVERY_FEE_ADJACENT_ZONE; // Adjacent zones
    } else {
        return DELIVERY_FEE_FAR_ZONE; // Far zones (2+ difference)
    }
}

/**
 * Count active orders for a driver
 */
function countActiveOrders($conn, $driver_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders1 WHERE driver_id = ? AND status IN ('accepted', 'picked_up')");
    $stmt->execute([$driver_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * Get driver statistics
 */
function getDriverStats($conn, $driver_id) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status='delivered' THEN 1 END) as completed,
            COUNT(CASE WHEN status='cancelled' THEN 1 END) as cancelled,
            COUNT(CASE WHEN status IN ('accepted', 'picked_up') THEN 1 END) as active,
            COALESCE(SUM(CASE WHEN status='delivered' THEN points_cost END), 0) as total_earned
        FROM orders1 
        WHERE driver_id = ?
    ");
    $stmt->execute([$driver_id]);
    return $stmt->fetch();
}

/**
 * Get customer statistics
 */
function getClientStats($conn, $client_id, $username) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status='delivered' THEN 1 END) as completed,
            COUNT(CASE WHEN status='cancelled' THEN 1 END) as cancelled,
            COUNT(CASE WHEN status='pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status IN ('accepted', 'picked_up') THEN 1 END) as in_progress
        FROM orders1 
        WHERE client_id = ? OR customer_name = ?
    ");
    $stmt->execute([$client_id, $username]);
    return $stmt->fetch();
}

/**
 * Upload avatar image
 */
function uploadAvatar($file, $user_id) {
    global $upload_dir, $max_file_size, $allowed_extensions;
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file size
    if ($file_size > $max_file_size) {
        return ['success' => false, 'error' => 'File size exceeds 5MB limit'];
    }
    
    // Validate file extension
    if (!in_array($file_ext, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and GIF allowed'];
    }
    
    // Generate unique filename
    $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        return ['success' => true, 'path' => $upload_path];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
}

// ==========================================
// SHORTCUTS
// ==========================================
$role = $_SESSION['user']['role'] ?? null;
$u = $_SESSION['user'] ?? null;
$uid = $_SESSION['user']['id'] ?? null;

?>
