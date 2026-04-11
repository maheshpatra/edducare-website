<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS `cms_pages` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `slug` varchar(100) NOT NULL,
      `title` varchar(255) NOT NULL,
      `content` longtext NOT NULL,
      `is_active` tinyint(1) DEFAULT 1,
      `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $conn->exec($sql);
    echo "Table cms_pages created successfully.\n";

    // Insert default content if not exists
    $pages = [
        [
            'slug' => 'terms-and-conditions',
            'title' => 'Terms and Conditions',
            'content' => '<h1>Terms and Conditions</h1><p>Welcome to EdduCare Cloud. By using our services, you agree to these terms...</p>'
        ],
        [
            'slug' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'content' => '<h1>Privacy Policy</h1><p>Your privacy is important to us. This policy explains how we handle your data...</p>'
        ]
    ];

    foreach ($pages as $page) {
        $stmt = $conn->prepare("INSERT IGNORE INTO cms_pages (slug, title, content) VALUES (:slug, :title, :content)");
        $stmt->execute($page);
        echo "Default content for {$page['slug']} inserted.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
