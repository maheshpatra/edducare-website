<?php
require_once __DIR__ . '/backend/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $email = 'mahesh@edducare.com';
    $password = 'Edducare@9009#';
    $username = 'mahesh';
    $firstName = 'Mahesh';
    $lastName = 'Patra';
    $roleId = 1; // super_admin role_id

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
