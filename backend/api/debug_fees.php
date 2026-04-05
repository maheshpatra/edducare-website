<?php
header('Content-Type: text/html');
require_once '../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);
if (!$user) exit;

$database = new Database();
$db = $database->getConnection();

echo "<h2>Fee Database Table Check</h2><ul>";
$tables = ['fee_categories', 'student_fees', 'fee_assignments', 'fee_payments', 'students', 'student_enrollments', 'users'];
foreach ($tables as $table) {
    try {
        $stmt = $db->query("SELECT 1 FROM $table LIMIT 1");
        echo "<li style='color:green'>Table <b>$table</b> EXISTS.</li>";
        
        echo "<ul>";
        $cols = $db->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "<li>Column: <b>" . $col['Field'] . "</b> (" . $col['Type'] . ")</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "<li style='color:red'>Table <b>$table</b> DOES NOT EXIST.</li>";
    }
}
echo "</ul>";
?>
