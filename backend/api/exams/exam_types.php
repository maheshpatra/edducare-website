<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();
$schoolId = $user['school_id'];
$method = $_SERVER['REQUEST_METHOD'];

// ─── Ensure exam_types table exists ────────────────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS exam_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_slug_school (school_id, slug)
    )");

    // Seed default types if none exist for this school
    $checkStmt = $db->prepare("SELECT COUNT(*) as cnt FROM exam_types WHERE school_id = :school_id");
    $checkStmt->execute([':school_id' => $schoolId]);
    $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($count == 0) {
        $defaults = [
            ['Unit Test', 'unit_test', 'Regular unit tests', 1],
            ['Mid Term', 'mid_term', 'Mid-term examination', 2],
            ['Final Exam', 'final', 'Final/annual examination', 3],
            ['Quarterly', 'quarterly', 'Quarterly examination', 4],
            ['Half Yearly', 'half_yearly', 'Half-yearly examination', 5],
            ['Pre-Board', 'pre_board', 'Pre-board practice exam', 6],
        ];
        $insertStmt = $db->prepare("INSERT INTO exam_types (school_id, name, slug, description, sort_order) VALUES (:school_id, :name, :slug, :desc, :sort)");
        foreach ($defaults as $d) {
            $insertStmt->execute([
                ':school_id' => $schoolId,
                ':name' => $d[0],
                ':slug' => $d[1],
                ':desc' => $d[2],
                ':sort' => $d[3],
            ]);
        }
    }
} catch (Exception $e) {
    // Table might already exist, ignore
}

// ─── GET: List exam types ───────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $query = "SELECT * FROM exam_types WHERE school_id = :school_id ORDER BY sort_order ASC, name ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([':school_id' => $schoolId]);
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $types]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// ─── POST: Create or update exam type ───────────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '');
    $id = $input['id'] ?? null;

    if (!$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Exam type name is required']);
        exit;
    }

    // Generate slug from name
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
    $slug = trim($slug, '_');

    try {
        if ($id) {
            // Update existing
            $query = "UPDATE exam_types SET name = :name, slug = :slug, description = :desc WHERE id = :id AND school_id = :school_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':desc' => $description,
                ':id' => $id,
                ':school_id' => $schoolId,
            ]);
            echo json_encode(['success' => true, 'message' => 'Exam type updated']);
        } else {
            // Get next sort order
            $sortStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM exam_types WHERE school_id = :school_id");
            $sortStmt->execute([':school_id' => $schoolId]);
            $nextSort = $sortStmt->fetch(PDO::FETCH_ASSOC)['next_sort'];

            $query = "INSERT INTO exam_types (school_id, name, slug, description, sort_order) VALUES (:school_id, :name, :slug, :desc, :sort)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':school_id' => $schoolId,
                ':name' => $name,
                ':slug' => $slug,
                ':desc' => $description,
                ':sort' => $nextSort,
            ]);
            echo json_encode(['success' => true, 'message' => 'Exam type created', 'id' => $db->lastInsertId()]);
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(['error' => 'An exam type with this name already exists']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

// ─── DELETE: Remove exam type ───────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID is required']);
        exit;
    }

    try {
        $query = "DELETE FROM exam_types WHERE id = :id AND school_id = :school_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $id, ':school_id' => $schoolId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Exam type deleted']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Exam type not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
