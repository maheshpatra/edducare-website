<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

if (!$school_id) {
    http_response_code(400);
    echo json_encode(['error' => 'School ID is required']);
    exit;
}

try {
    // 1. Fetch theme table data
    $query = "SELECT * FROM school_themes WHERE school_id = :school_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':school_id' => $school_id]);
    $theme = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch stats
    $stats_query = "SELECT label, value, icon FROM school_stats WHERE school_id = :school_id ORDER BY sort_order ASC";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([':school_id' => $school_id]);
    $stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no theme, return defaults (fallback)
    if (!$theme) {
        $theme = [
            'primary_color' => '#3b82f6',
            'secondary_color' => '#1e40af',
            'font_family' => 'Inter, sans-serif',
            'layout_style' => 'modern',
            'principal_message' => 'Welcome to our school. We are committed to fostering an inclusive environment for all students.',
            'about_text' => 'We are a premier educational institution with a legacy of excellence.',
        ];
    }

    echo json_encode([
        'success' => true, 
        'data' => [
            'theme' => $theme,
            'stats' => $stats
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
