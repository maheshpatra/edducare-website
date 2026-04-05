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
if (!$user) exit;

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['transaction_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Transaction ID is required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    // Verify transaction exists and is issued
    $transactionQuery = "SELECT bi.*, lb.school_id 
                        FROM book_issues bi
                        INNER JOIN library_books lb ON bi.book_id = lb.id
                        WHERE bi.id = :transaction_id AND bi.status = 'issued' AND lb.school_id = :school_id";
    
    $transactionStmt = $db->prepare($transactionQuery);
    $transactionStmt->bindValue(':transaction_id', $input['transaction_id']);
    $transactionStmt->bindValue(':school_id', $user['school_id']);
    $transactionStmt->execute();
    
    if ($transactionStmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Transaction not found or book already returned']);
        exit;
    }
    
    $transaction = $transactionStmt->fetch();
    
    // Calculate fine if overdue
    $returnDate = date('Y-m-d');
    $fine = 0;
    
    if ($returnDate > $transaction['due_date']) {
        $overdueDays = (strtotime($returnDate) - strtotime($transaction['due_date'])) / (60 * 60 * 24);
        $fine = $overdueDays * 1; // 1 unit fine per day
    }
    
    // Update transaction
    $updateQuery = "UPDATE book_issues 
                   SET status = 'returned', return_date = :return_date, fine_amount = :fine
                   WHERE id = :transaction_id";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':return_date', $returnDate);
    $updateStmt->bindValue(':fine', $fine);
    $updateStmt->bindValue(':transaction_id', $input['transaction_id']);
    
    $updateStmt->execute();
    
    // Update book availability
    $updateBookQuery = "UPDATE library_books SET available_copies = available_copies + 1 WHERE id = :book_id";
    $updateBookStmt = $db->prepare($updateBookQuery);
    $updateBookStmt->bindValue(':book_id', $transaction['book_id']);
    $updateBookStmt->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Book returned successfully',
        'fine' => $fine,
        'return_date' => $returnDate
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'details' => $e->getMessage()]);
}
?>
