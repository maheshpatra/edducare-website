<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Current directory: " . __DIR__ . "<br>";
$dbFile = __DIR__ . '/../config/database.php';
if (!file_exists($dbFile)) {
    die("Error: Database configuration file not found at $dbFile");
}
require_once $dbFile;
echo "Database config loaded successfully.<br>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("Could not connect to database. Check your credentials in backend/config/database.php");
    }

    $email = 'mahesh@edducare.com';
    $password = 'Edducare@9009#';
    $username = 'mahesh';
    $firstName = 'Mahesh';
    $lastName = 'Patra';
    
    // Dynamically find role_id for super_admin
    $roleQuery = "SELECT id FROM user_roles WHERE name = 'super_admin' LIMIT 1";
    $roleStmt = $db->query($roleQuery);
    $role = $roleStmt->fetch();
    
    if (!$role) {
        die("Role 'super_admin' not found in user_roles table.");
    }
    $roleId = $role['id'];

    // Check if user already exists
    $checkQuery = "SELECT id FROM users WHERE email = :email OR username = :username";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindValue(':email', $email);
    $checkStmt->bindValue(':username', $username);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        echo "User with this email or username already exists.\n";
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $insertQuery = "INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, is_active, email_verified)
                    VALUES (:role_id, :username, :email, :password_hash, :first_name, :last_name, 1, 1)";
    
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindValue(':role_id', $roleId);
    $insertStmt->bindValue(':username', $username);
    $insertStmt->bindValue(':email', $email);
    $insertStmt->bindValue(':password_hash', $passwordHash);
    $insertStmt->bindValue(':first_name', $firstName);
    $insertStmt->bindValue(':last_name', $lastName);

    if ($insertStmt->execute()) {
        echo "SuperAdmin created successfully.\n";
        echo "Email: $email\n";
        echo "Username: $username\n";
    } else {
        echo "Failed to create SuperAdmin.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
