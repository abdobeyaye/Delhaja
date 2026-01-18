<?php
/**
 * Configuration File
 * Enhanced Delivery Pro System v2.0
 * Database connection and system constants
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// DATABASE CONNECTION
// ==========================================
$db_host = 'localhost';
$db_name = 'delhaja';
$db_user = 'root';
$db_pass = '';

try {
    $conn = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
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
// SYSTEM CONSTANTS
// ==========================================

// Points cost per order for drivers
$points_cost_per_order = 20;

// Order expiry time in hours (auto-cancel pending orders)
$order_expiry_hours = 3;

// Contact information
$whatsapp_number = '22200000000'; // Replace with actual WhatsApp number
$help_email = 'support@delhaja.mr'; // Replace with actual support email

// Upload directory for avatars
$upload_dir = 'uploads/avatars/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Maximum avatar file size (2MB)
$max_avatar_size = 2 * 1024 * 1024;

// Allowed avatar file types
$allowed_avatar_types = ['image/jpeg', 'image/png', 'image/webp'];

// ==========================================
// APP METADATA
// ==========================================
$app_version = '2.0';
$app_name = 'Delhaja';

?>
