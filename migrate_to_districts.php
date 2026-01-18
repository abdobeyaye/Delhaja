<?php
/**
 * Migration Script: District-Based Delivery System
 * This script migrates the database from the old promo code system to the new district-based system
 * 
 * BACKUP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
 * 
 * Run this once: php migrate_to_districts.php
 */

require_once 'config.php';

echo "=== District-Based Delivery System Migration ===\n\n";

try {
    // Step 1: Add new columns to orders1 table if they don't exist
    echo "Step 1: Adding new columns to orders1 table...\n";
    
    $columns_to_add = [
        "ALTER TABLE orders1 ADD COLUMN pickup_district_id INT DEFAULT NULL COMMENT 'Pickup district ID'",
        "ALTER TABLE orders1 ADD COLUMN delivery_district_id INT DEFAULT NULL COMMENT 'Delivery district ID'",
        "ALTER TABLE orders1 ADD COLUMN delivery_fee INT DEFAULT 100 COMMENT 'Delivery fee in MRU'",
        "ALTER TABLE orders1 ADD COLUMN detailed_address VARCHAR(500) DEFAULT NULL COMMENT 'Detailed delivery address'",
        "ALTER TABLE orders1 ADD INDEX idx_pickup_district (pickup_district_id)",
        "ALTER TABLE orders1 ADD INDEX idx_delivery_district (delivery_district_id)"
    ];
    
    foreach ($columns_to_add as $sql) {
        try {
            $conn->exec($sql);
            echo "  ✓ " . substr($sql, 0, 60) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "  ⊘ Column/Index already exists (skipped)\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Step 2: Remove old promo code columns
    echo "\nStep 2: Removing promo code columns from orders1...\n";
    
    $columns_to_remove = [
        "ALTER TABLE orders1 DROP COLUMN promo_code",
        "ALTER TABLE orders1 DROP COLUMN discount_amount"
    ];
    
    foreach ($columns_to_remove as $sql) {
        try {
            $conn->exec($sql);
            echo "  ✓ " . substr($sql, 0, 60) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'check that column/key exists') !== false) {
                echo "  ⊘ Column already removed (skipped)\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Step 3: Create districts table
    echo "\nStep 3: Creating districts table...\n";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS districts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL COMMENT 'District name (French)',
        name_ar VARCHAR(100) NOT NULL COMMENT 'District name (Arabic)',
        is_active TINYINT(1) DEFAULT 1 COMMENT 'Active status',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "  ✓ Districts table created\n";
    
    // Insert districts if not exists
    $check = $conn->query("SELECT COUNT(*) FROM districts")->fetchColumn();
    if ($check == 0) {
        echo "  ✓ Inserting 10 Mauritanian districts...\n";
        $conn->exec("INSERT INTO districts (id, name, name_ar) VALUES
            (1, 'Tevragh Zeina', 'تفرغ زينة'),
            (2, 'Ksar', 'لكصر'),
            (3, 'Sebkha', 'سبخة'),
            (4, 'Teyarett', 'تيارت'),
            (5, 'Dar Naïm', 'دار النعيم'),
            (6, 'Toujounine', 'توجنين'),
            (7, 'Arafat', 'عرفات'),
            (8, 'El Mina', 'الميناء'),
            (9, 'Riyad', 'الرياض'),
            (10, 'Tarhil', 'الترحيل')");
        echo "  ✓ Districts inserted successfully\n";
    } else {
        echo "  ⊘ Districts already exist (skipped)\n";
    }
    
    // Step 4: Create district_prices table
    echo "\nStep 4: Creating district_prices table...\n";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS district_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_district_id INT NOT NULL COMMENT 'Source district ID',
        to_district_id INT NOT NULL COMMENT 'Destination district ID',
        price INT NOT NULL DEFAULT 100 COMMENT 'Delivery price in MRU',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_route (from_district_id, to_district_id),
        INDEX idx_from (from_district_id),
        INDEX idx_to (to_district_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "  ✓ District prices table created\n";
    
    // Insert pricing data if not exists
    $check = $conn->query("SELECT COUNT(*) FROM district_prices")->fetchColumn();
    if ($check == 0) {
        echo "  ✓ Inserting 90 exact pricing routes...\n";
        
        $pricing_data = [
            // Tevragh Zeina (ID: 1)
            [1, 1, 100], [1, 2, 100], [1, 3, 100], [1, 8, 150], [1, 7, 150], [1, 4, 150],
            [1, 5, 200], [1, 6, 200], [1, 9, 200], [1, 10, 200],
            // Ksar (ID: 2)
            [2, 2, 100], [2, 1, 100], [2, 4, 100], [2, 5, 100], [2, 3, 100],
            [2, 6, 150], [2, 7, 150], [2, 8, 150], [2, 9, 200], [2, 10, 200],
            // Sebkha (ID: 3)
            [3, 3, 100], [3, 8, 100], [3, 1, 100], [3, 2, 100],
            [3, 7, 150], [3, 9, 150], [3, 4, 200], [3, 5, 200], [3, 6, 200], [3, 10, 200],
            // Teyarett (ID: 4)
            [4, 4, 100], [4, 5, 100], [4, 2, 100],
            [4, 1, 150], [4, 6, 150],
            [4, 3, 200], [4, 7, 200], [4, 8, 200], [4, 9, 200], [4, 10, 200],
            // Dar Naïm (ID: 5)
            [5, 5, 100], [5, 4, 100], [5, 6, 100], [5, 2, 100],
            [5, 7, 150],
            [5, 1, 200], [5, 3, 200], [5, 8, 200], [5, 9, 200], [5, 10, 200],
            // Toujounine (ID: 6)
            [6, 6, 100], [6, 5, 100], [6, 7, 100],
            [6, 2, 150], [6, 4, 150], [6, 9, 150],
            [6, 1, 200], [6, 3, 200], [6, 8, 200], [6, 10, 200],
            // Arafat (ID: 7)
            [7, 7, 100], [7, 6, 100], [7, 8, 100], [7, 9, 100],
            [7, 2, 150], [7, 1, 150], [7, 3, 150], [7, 5, 150],
            [7, 4, 200], [7, 10, 200],
            // El Mina (ID: 8)
            [8, 8, 100], [8, 3, 100], [8, 7, 100], [8, 9, 100],
            [8, 1, 150], [8, 2, 150],
            [8, 6, 200], [8, 5, 200], [8, 4, 200], [8, 10, 200],
            // Riyad (ID: 9)
            [9, 9, 100], [9, 7, 100], [9, 8, 100],
            [9, 3, 150], [9, 6, 150],
            [9, 1, 200], [9, 2, 200], [9, 5, 200], [9, 4, 200], [9, 10, 200],
            // Tarhil (ID: 10)
            [10, 10, 100],
            [10, 1, 200], [10, 2, 200], [10, 3, 200], [10, 4, 200], [10, 5, 200],
            [10, 6, 200], [10, 7, 200], [10, 8, 200], [10, 9, 200]
        ];
        
        $stmt = $conn->prepare("INSERT INTO district_prices (from_district_id, to_district_id, price) VALUES (?, ?, ?)");
        foreach ($pricing_data as $route) {
            $stmt->execute($route);
        }
        
        echo "  ✓ Pricing data inserted successfully (90 routes)\n";
    } else {
        echo "  ⊘ Pricing data already exists (skipped)\n";
    }
    
    // Step 5: Create driver_districts table
    echo "\nStep 5: Creating driver_districts table...\n";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS driver_districts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        driver_id INT NOT NULL COMMENT 'Link to users1.id',
        district_id INT NOT NULL COMMENT 'Link to districts.id',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_driver_district (driver_id, district_id),
        INDEX idx_driver (driver_id),
        INDEX idx_district (district_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "  ✓ Driver districts table created\n";
    
    // Step 6: Drop promo code tables
    echo "\nStep 6: Removing promo code tables...\n";
    
    try {
        $conn->exec("DROP TABLE IF EXISTS promo_code_uses");
        echo "  ✓ Dropped promo_code_uses table\n";
    } catch (PDOException $e) {
        echo "  ⊘ promo_code_uses table already removed\n";
    }
    
    try {
        $conn->exec("DROP TABLE IF EXISTS promo_codes");
        echo "  ✓ Dropped promo_codes table\n";
    } catch (PDOException $e) {
        echo "  ⊘ promo_codes table already removed\n";
    }
    
    echo "\n=== Migration completed successfully! ===\n";
    echo "\nNext steps:\n";
    echo "1. Test the order submission with district selection\n";
    echo "2. Test delivery fee calculation\n";
    echo "3. Have drivers select their operating districts in settings\n";
    echo "4. Verify order filtering works for drivers\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Please check the error and try again.\n\n";
    exit(1);
}
?>
