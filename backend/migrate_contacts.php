<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS `main_site_contacts` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `school_name` varchar(255) NOT NULL,
      `email` varchar(255) NOT NULL,
      `phone` varchar(20) DEFAULT NULL,
      `plan` varchar(50) DEFAULT NULL,
      `message` text NOT NULL,
      `status` enum('pending', 'contacted', 'resolved') DEFAULT 'pending',
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $conn->exec($sql);
    echo "Table main_site_contacts created successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
