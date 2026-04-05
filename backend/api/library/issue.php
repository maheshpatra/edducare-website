<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin', 'librarian', 'teacher_academic', 'teacher_administrative']);

if (!$user) {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['book_id']) || !isset($input['student_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Book ID and Student ID are required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    // Verify book availability
    $bookQuery = "SELECT * FROM library_books WHERE id = :book_id AND school_id = :school_id AND available_copies > 0";
    $bookStmt = $db->prepare($bookQuery);
    $bookStmt->bindValue(':book_id', $input['book_id']);
    $bookStmt->bindValue(':school_id', $user['school_id']);
    $bookStmt->execute();
    
    if ($bookStmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Book not available or not found']);
        exit;
    }
    
    $book = $bookStmt->fetch();
    
    // Verify student exists and belongs to school
    $studentQuery = "SELECT id FROM students WHERE id = :student_id AND school_id = :school_id";
    $studentStmt = $db->prepare($studentQuery);
    $studentStmt->bindValue(':student_id', $input['student_id']);
    $studentStmt->bindValue(':school_id', $user['school_id']);
    $studentStmt->execute();
    
    if ($studentStmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Student not found or doesn\'t belong to this school']);
        exit;
    }
    
    // Check if student already has this book issued (status 'issued')
    $existingQuery = "SELECT id FROM book_issues 
                     WHERE book_id = :book_id AND student_id = :student_id AND status = 'issued'";
    $existingStmt = $db->prepare($existingQuery);
    $existingStmt->bindValue(':book_id', $input['book_id']);
    $existingStmt->bindValue(':student_id', $input['student_id']);
    $existingStmt->execute();
    
    if ($existingStmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Student already has this book issued']);
        exit;
    }
    
    // Issue the book
    $issueDate = date('Y-m-d');
    $dueDate = $input['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
    
    $issueQuery = "INSERT INTO book_issues (book_id, student_id, user_type, issue_date, due_date, status, issued_by)
                  VALUES (:book_id, :student_id, 'student', :issue_date, :due_date, 'issued', :issued_by)";
    
    $issueStmt = $db->prepare($issueQuery);
    $issueStmt->bindValue(':book_id', $input['book_id']);
    $issueStmt->bindValue(':student_id', $input['student_id']);
    $issueStmt->bindValue(':issue_date', $issueDate);
    $issueStmt->bindValue(':due_date', $dueDate);
    $issueStmt->bindValue(':issued_by', $user['id']);
    
    $issueStmt->execute();
    $transactionId = $db->lastInsertId();
    
    // Update book availability
    $updateBookQuery = "UPDATE library_books SET available_copies = available_copies - 1 WHERE id = :book_id";
    $updateBookStmt = $db->prepare($updateBookQuery);
    $updateBookStmt->bindValue(':book_id', $input['book_id']);
    $updateBookStmt->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Book issued successfully',
        'transaction_id' => $transactionId,
        'due_date' => $dueDate
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
?>
