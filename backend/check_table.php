<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("DESCRIBE admission_requests");
print_r($stmt->fetchAll());
