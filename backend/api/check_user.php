<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $email = 'mahesh@edducare.com';

    echo "<h3>Diagnostic for $email:</h3>";

    $query = "SELECT u.*, ur.name as role_name 
              FROM users u 
              JOIN user_roles ur ON u.role_id = ur.id 
              WHERE u.email = :email";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        echo "<p style='color:red'>User '$email' NOT found in 'users' table.</p>";
        
        // Check if user exists without join
        $q2 = "SELECT id, role_id, username, is_active FROM users WHERE email = :email";
        $s2 = $db->prepare($q2);
        $s2->bindValue(':email', $email);
        $s2->execute();
        if ($s2->rowCount() > 0) {
            $u2 = $s2->fetch();
            echo "<p style='color:orange'>User exists but JOIN failed. role_id: " . $u2['role_id'] . ". User is_active: " . $u2['is_active'] . "</p>";
            
            // Check roles
            $q3 = "SELECT * FROM user_roles";
            $res = $db->query($q3)->fetchAll();
            echo "<h4>Configured Roles:</h4><ul>";
            foreach($res as $r) {
                echo "<li>ID: {$r['id']} - Name: {$r['name']}</li>";
            }
            echo "</ul>";
        }
    } else {
        $user = $stmt->fetch();
        echo "<ul>";
        echo "<li><b>ID:</b> " . $user['id'] . "</li>";
        echo "<li><b>Username:</b> " . $user['username'] . "</li>";
        echo "<li><b>Role Name:</b> " . $user['role_name'] . "</li>";
        echo "<li><b>Is Active:</b> " . ($user['is_active'] ? 'YES' : 'NO') . "</li>";
        echo "<li><b>School ID:</b> " . ($user['school_id'] ?? 'NULL (Correct for Super Admin)') . "</li>";
        echo "</ul>";
        
        if ($user['role_name'] !== 'super_admin') {
            echo "<p style='color:red'>ERROR: Role name is '" . $user['role_name'] . "' but code expects 'super_admin'.</p>";
        } else {
            echo "<p style='color:green'>Role verification PASSED.</p>";
        }
    }

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage();
}
?>
