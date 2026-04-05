<?php
$token = $_GET['token'] ?? '';
if (!$token) {
    die('Invalid or missing token.');
}
?>

<?php include 'reset-password-view.php'; ?>
