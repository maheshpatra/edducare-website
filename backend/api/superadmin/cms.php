<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

try {
    $auth = new AuthMiddleware();
    // Assuming super_admin is the role name for super administrators
    $user = $auth->requireRole(['super_admin']);

    if (!$user) {
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database connection failed");
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['slug'])) {
                $query = "SELECT * FROM cms_pages WHERE slug = :slug LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':slug', $_GET['slug']);
                $stmt->execute();
                $page = $stmt->fetch();
                echo json_encode(['success' => true, 'data' => $page]);
            } else {
                $query = "SELECT id, slug, title, is_active, updated_at FROM cms_pages ORDER BY title ASC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $pages = $stmt->fetchAll();
                echo json_encode(['success' => true, 'data' => $pages]);
            }
            break;

        case 'PUT':
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['slug']) || !isset($input['title']) || !isset($input['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields: slug, title, content']);
                exit;
            }

            $query = "INSERT INTO cms_pages (slug, title, content, is_active) 
                      VALUES (:slug, :title, :content, :is_active)
                      ON DUPLICATE KEY UPDATE 
                      title = VALUES(title), 
                      content = VALUES(content), 
                      is_active = VALUES(is_active)";
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':slug', $input['slug']);
            $stmt->bindValue(':title', $input['title']);
            $stmt->bindValue(':content', $input['content']);
            $stmt->bindValue(':is_active', $input['is_active'] ?? 1);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Page saved successfully']);
            } else {
                throw new Exception("Failed to save page");
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
