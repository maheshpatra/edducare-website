<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

try {
    $auth = new AuthMiddleware();
    // Only super admins should see landing page contacts
    $user = $auth->requireRole(['super_admin']);

    if (!$user) {
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $query = "SELECT * FROM main_site_contacts ORDER BY created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $contacts]);
            break;

        case 'POST':
            // Update status
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['id']) || !isset($input['status'])) {
                throw new Exception("ID and status are required");
            }
            $query = "UPDATE main_site_contacts SET status = :status WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':status' => $input['status'], ':id' => $input['id']]);
            echo json_encode(['success' => true, 'message' => 'Status updated']);
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['id'])) {
                throw new Exception("ID is required");
            }
            $query = "DELETE FROM main_site_contacts WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $input['id']]);
            echo json_encode(['success' => true, 'message' => 'Message deleted']);
            break;

        default:
            http_response_code(405);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
