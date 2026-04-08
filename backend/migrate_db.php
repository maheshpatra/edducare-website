<?php
require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();
    if (!$db) throw new Exception("DB connection failed");

    echo "Checking schema...\n";

    // Check for utr_number and payment_method in admission_requests
    $res = $db->query("DESCRIBE admission_requests")->fetchAll();
    $cols = array_column($res, 'Field');

    if (!in_array('utr_number', $cols)) {
        echo "Adding utr_number...\n";
        $db->exec("ALTER TABLE admission_requests ADD COLUMN utr_number VARCHAR(100) DEFAULT NULL");
    }

    if (!in_array('payment_method', $cols)) {
        echo "Adding payment_method...\n";
        $db->exec("ALTER TABLE admission_requests ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL");
    }

    if (!in_array('payment_status', $cols)) {
        echo "Adding payment_status...\n";
        $db->exec("ALTER TABLE admission_requests ADD COLUMN payment_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'");
    }

    // Check for school_payment_gateways table
    try {
        $db->query("SELECT 1 FROM school_payment_gateways LIMIT 1");
        echo "school_payment_gateways table exists.\n";
    } catch (Exception $e) {
        echo "Creating school_payment_gateways table...\n";
        $sql = "CREATE TABLE school_payment_gateways (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            gateway_name VARCHAR(50) NOT NULL,
            is_active TINYINT(1) DEFAULT 0,
            mode ENUM('sandbox', 'live') DEFAULT 'sandbox',
            config_json TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY school_gateway (school_id, gateway_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $db->exec($sql);
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
