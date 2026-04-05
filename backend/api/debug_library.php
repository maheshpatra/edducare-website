<?php
header('Content-Type: text/html');
require_once '../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);
if (!$user) exit;

$database = new Database();
$db = $database->getConnection();

echo "<h2>Library Database Table Check</h2><ul>";
$tables = ['library_books', 'book_issues', 'library_transactions', 'students', 'users', 'student_enrollments'];
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
