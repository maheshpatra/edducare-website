<?php
header('Content-Type: text/html');
require_once '../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin', 'class_teacher']);
if (!$user) exit;

$database = new Database();
$db = $database->getConnection();

echo "<h2>Database Table Check</h2><ul>";
$tables = ['assignments', 'students', 'student_enrollments', 'teacher_assignments', 'class_subjects', 'users'];
foreach ($tables as $table) {
    try {
        $stmt = $db->query("SELECT 1 FROM $table LIMIT 1");
        echo "<li style='color:green'>Table <b>$table</b> EXISTS.</li>";
        
        // Show columns for assignments
        if ($table === 'assignments') {
            echo "<ul>";
            $cols = $db->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                echo "<li>Column: <b>" . $col['Field'] . "</b> (" . $col['Type'] . ")</li>";
            }
            echo "</ul>";
        }
    } catch (Exception $e) {
        echo "<li style='color:red'>Table <b>$table</b> DOES NOT EXIST. Error: " . $e->getMessage() . "</li>";
    }
}
echo "</ul>";
?>
