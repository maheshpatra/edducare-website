<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

$auth = new AuthMiddleware();
$database = new Database();
$db = $database->getConnection();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $user = $auth->requireRole(['super_admin', 'school_admin', 'librarian', 'teacher_academic', 'teacher_administrative', 'student']);
            if (!$user) exit;
            handleGetBooks($db, $user);
            break;
            
        case 'POST':
            $user = $auth->requireRole(['super_admin', 'school_admin', 'librarian']);
            if (!$user) exit;
            handleAddBook($db, $user);
            break;
            
        case 'PUT':
            $user = $auth->requireRole(['super_admin', 'school_admin', 'librarian']);
            if (!$user) exit;
            handleUpdateBook($db, $user);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGetBooks($db, $user) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $whereConditions = ["lb.school_id = :school_id"];
    $params = [':school_id' => $user['school_id']];
    
    if (isset($_GET['search'])) {
        $whereConditions[] = "(lb.title LIKE :search OR lb.author LIKE :search OR lb.isbn LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }
    
    if (isset($_GET['category'])) {
        $whereConditions[] = "lb.category = :category";
        $params[':category'] = $_GET['category'];
    }
    
    if (isset($_GET['available_only']) && $_GET['available_only'] === 'true') {
        $whereConditions[] = "lb.available_copies > 0";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "SELECT 
        lb.*,
        (lb.total_copies - lb.available_copies) as issued_copies,
        COUNT(lt.id) as total_transactions
        FROM library_books lb
        LEFT JOIN book_issues lt ON lb.id = lt.book_id
        WHERE $whereClause
        GROUP BY lb.id
        ORDER BY lb.title
        LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $books = $stmt->fetchAll();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM library_books lb WHERE $whereClause";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $countStmt->bindValue($key, $value);
        }
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $books,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

function handleAddBook($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['title', 'author', 'isbn', 'category', 'total_copies'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    $query = "INSERT INTO library_books (school_id, title, author, isbn, category, publisher, publication_year, total_copies, available_copies, location, description)
              VALUES (:school_id, :title, :author, :isbn, :category, :publisher, :publication_year, :total_copies, :available_copies, :location, :description)";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':school_id', $user['school_id']);
    $stmt->bindValue(':title', $input['title']);
    $stmt->bindValue(':author', $input['author']);
    $stmt->bindValue(':isbn', $input['isbn']);
    $stmt->bindValue(':category', $input['category']);
    $stmt->bindValue(':publisher', $input['publisher'] ?? null);
    $stmt->bindValue(':publication_year', $input['publication_year'] ?? null);
    $stmt->bindValue(':total_copies', $input['total_copies']);
    $stmt->bindValue(':available_copies', $input['total_copies']); // Initially all copies are available
    $stmt->bindValue(':location', $input['location'] ?? null);
    $stmt->bindValue(':description', $input['description'] ?? null);
    
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Book added successfully',
        'book_id' => $db->lastInsertId()
    ]);
}

function handleUpdateBook($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['book_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Book ID is required']);
        return;
    }
    
    $updateFields = [];
    $params = [':book_id' => $input['book_id'], ':school_id' => $user['school_id']];
    
    $allowedFields = ['title', 'author', 'isbn', 'category', 'publisher', 'publication_year', 'total_copies', 'location', 'description'];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = :$field";
            $params[":$field"] = $input[$field];
        }
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        return;
    }
    
    // If total_copies is being updated, adjust available_copies
    if (isset($input['total_copies'])) {
        $getCurrentQuery = "SELECT total_copies, available_copies FROM library_books WHERE id = :book_id AND school_id = :school_id";
        $getCurrentStmt = $db->prepare($getCurrentQuery);
        $getCurrentStmt->bindValue(':book_id', $input['book_id']);
        $getCurrentStmt->bindValue(':school_id', $user['school_id']);
        $getCurrentStmt->execute();
        
        if ($getCurrentStmt->rowCount() > 0) {
            $current = $getCurrentStmt->fetch();
            $issuedCopies = $current['total_copies'] - $current['available_copies'];
            $newAvailable = max(0, $input['total_copies'] - $issuedCopies);
            
            $updateFields[] = "available_copies = :available_copies";
            $params[':available_copies'] = $newAvailable;
        }
    }
    
    $query = "UPDATE library_books SET " . implode(', ', $updateFields) . " WHERE id = :book_id AND school_id = :school_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Book not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Book updated successfully'
    ]);
}
?>
