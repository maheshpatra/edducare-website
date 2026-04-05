<?php
/**
 * Test Data Seeder
 * 
 * ⚠️  DELETE THIS FILE AFTER RUNNING — it contains no auth and could be exploited.
 * 
 * Access via browser or curl:
 *   http://YOUR_BACKEND_URL/seed_test_data.php
 * 
 * It will seed exams, library books, library transactions,
 * assignments, attendance, and announcements for student id=18.
 */

// ─── Load DB connection ─────────────────────────────────────
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die(json_encode(['error' => 'Database connection failed']));
}

$results = [];
$errors  = [];

function exec_sql($db, $label, $sql, &$results, &$errors) {
    try {
        $db->exec($sql);
        $results[] = "✅ $label";
    } catch (Exception $e) {
        $errors[] = "❌ $label → " . $e->getMessage();
    }
}

// ─── Find dynamic IDs ─────────────────────────────────────
$ay_id    = $db->query("SELECT id FROM academic_years WHERE school_id=1 AND is_current=1 LIMIT 1")->fetchColumn();
$tch_id   = $db->query("SELECT id FROM users WHERE school_id=1 AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
$admin_id = $db->query("SELECT id FROM users WHERE school_id=1 ORDER BY id LIMIT 1")->fetchColumn();

// ★ KEY FIX: Use the student's ACTUAL active enrollment (class_id=14, section_id=3)
// This prevents seeding data for the wrong class.
$enroll = $db->query("
    SELECT se.class_id, se.section_id
    FROM student_enrollments se
    WHERE se.student_id = 18 AND se.status = 'active'
    ORDER BY se.enrollment_date DESC LIMIT 1
")->fetch();

if ($enroll) {
    $cls_id = $enroll['class_id'];
    $sec_id = $enroll['section_id'];
    $results[] = "✅ Using student 18 actual enrollment: class_id=$cls_id, section_id=$sec_id";
} else {
    // Fallback: first class in school
    $cls_id = $db->query("SELECT id FROM classes WHERE school_id=1 ORDER BY id LIMIT 1")->fetchColumn();
    $sec_id = $db->query("SELECT s.id FROM sections s JOIN classes c ON s.class_id=c.id WHERE c.school_id=1 ORDER BY s.id LIMIT 1")->fetchColumn();
    $results[] = "⚠️ No active enrollment found for student 18 — using first class: class_id=$cls_id, section_id=$sec_id";
}

// Subjects linked to student's class
$subj_a = $db->query("SELECT id FROM subjects WHERE school_id=1 AND id=9 LIMIT 1")->fetchColumn()
           ?: $db->query("SELECT id FROM subjects WHERE school_id=1 ORDER BY id LIMIT 1")->fetchColumn();
$subj_b = $db->query("SELECT id FROM subjects WHERE school_id=1 AND id=19 LIMIT 1")->fetchColumn()
           ?: $db->query("SELECT id FROM subjects WHERE school_id=1 ORDER BY id LIMIT 1 OFFSET 1")->fetchColumn();

$results[] = "ℹ️  class_id=$cls_id | section_id=$sec_id | academic_year_id=$ay_id | teacher_id=$tch_id | subj_a=$subj_a | subj_b=$subj_b";

// Skip enrollment step — student 18 already has an active enrollment
$results[] = "⏩ Skipping enrollment insert — using existing active enrollment";


// ─── STEP 2: Extend exams table ────────────────────────────
$columns = [
    'class_id'    => "INT NULL",
    'section_id'  => "INT NULL",
    'subject'     => "VARCHAR(255) NULL",
    'exam_type'   => "VARCHAR(50) NULL",
    'exam_date'   => "DATE NULL",
    'start_time'  => "TIME NULL",
    'total_marks' => "INT DEFAULT 100",
    'room'        => "VARCHAR(100) NULL",
];
$existing = $db->query("SHOW COLUMNS FROM exams")->fetchAll(PDO::FETCH_COLUMN);
foreach ($columns as $col => $def) {
    if (!in_array($col, $existing)) {
        exec_sql($db, "ALTER exams ADD $col", "ALTER TABLE exams ADD COLUMN $col $def", $results, $errors);
    } else {
        $results[] = "⏩ exams.$col already exists";
    }
}

// ─── STEP 3: Insert exams ──────────────────────────────────
$exams = [
    // [name, type, subject, exam_type, days_from_today, start_time, total_marks, room]
    ['Mathematics Unit Test – I',      'unit_test', 'Mathematics',     'unit_test', +3,  '09:00:00', 100, 'Room 101'],
    ['Hindi Mid-Term Examination',      'mid_term',  'Hindi',           'midterm',   +7,  '10:30:00', 100, 'Hall A'],
    ['Science Practical Examination',   'practical', 'Science',         'quiz',      +10, '14:00:00', 50,  'Lab 1'],
    ['English Final Examination',       'final',     'English',         'final',     +15, '09:00:00', 100, 'Hall B'],
    ['Social Studies Quiz – II',        'unit_test', 'Social Studies',  'quiz',      +21, '11:00:00', 25,  'Room 205'],
    ['Computer Science Class Test',     'unit_test', 'Computer Science','unit_test', 0,   '13:00:00', 50,  'Computer Lab'],
    ['Mathematics Monthly Test',        'unit_test', 'Mathematics',     'unit_test', -5,  '09:00:00', 100, 'Room 101'],
    ['Hindi Dictation Test',            'unit_test', 'Hindi',           'quiz',      -12, '10:00:00', 25,  'Room 201'],
    ['Science Mid-Term Exam',           'mid_term',  'Science',         'midterm',   -20, '09:30:00', 100, 'Hall A'],
];

$insertedExams = 0;
foreach ($exams as $e) {
    [$name, $type, $subject, $exam_type, $days, $start_time, $total_marks, $room] = $e;
    $exam_date = date('Y-m-d', strtotime("$days days"));
    try {
        $stmt = $db->prepare("
            INSERT INTO exams 
                (school_id, class_id, section_id, name, type, subject, exam_type, academic_year_id,
                 exam_date, start_time, total_marks, room, start_date, end_date, created_by)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$cls_id, $sec_id, $name, $type, $subject, $exam_type, $ay_id,
                        $exam_date, $start_time, $total_marks, $room,
                        $exam_date, $exam_date, $tch_id]);
        $insertedExams++;
    } catch (Exception $ex) {
        $errors[] = "❌ Exam '$name' → " . $ex->getMessage();
    }
}
$results[] = "✅ Inserted $insertedExams exams";

// ─── STEP 4: Library books ─────────────────────────────────
$books = [
    ['978-0136042594','Computer Science: An Overview',         'Glenn Brookshear',    'Pearson',           'Computer',    5, 3, 'Shelf A-1'],
    ['978-0201633610','Design Patterns (GoF)',                  'Gang of Four',        'Addison-Wesley',    'Computer',    3, 2, 'Shelf A-2'],
    ['978-0545010221','Harry Potter and the Sorcerers Stone',   'J.K. Rowling',        'Scholastic',        'Literature',  4, 3, 'Shelf B-1'],
    ['978-0743273565','The Great Gatsby',                       'F. Scott Fitzgerald', 'Scribner',          'Literature',  2, 2, 'Shelf B-2'],
    ['978-0393970128','A Brief History of Time',                'Stephen Hawking',     'Bantam Books',      'Science',     3, 1, 'Shelf C-1'],
    ['978-0679720201','Cosmos',                                 'Carl Sagan',          'Random House',      'Science',     2, 2, 'Shelf C-2'],
    ['978-0316769174','The Catcher in the Rye',                 'J.D. Salinger',       'Little Brown',      'Literature',  3, 3, 'Shelf B-3'],
    ['978-0486284736','Euclid Elements',                        'Euclid',              'Dover Publications','Mathematics', 4, 4, 'Shelf D-1'],
    ['978-0131103627','The C Programming Language',             'K&R',                 'Prentice Hall',     'Computer',    2, 0, 'Shelf A-3'],
    ['978-0385333481','The Alchemist',                          'Paulo Coelho',        'HarperOne',         'Literature',  5, 5, 'Shelf B-4'],
    ['978-0521657914','Calculus Made Easy',                     'Silvanus Thompson',   'Macmillan',         'Mathematics', 3, 2, 'Shelf D-2'],
    ['978-0071354943','Schaums Outline of Physics',              'Eugene Hecht',        'McGraw-Hill',       'Science',     4, 3, 'Shelf C-3'],
    ['978-8120340138','NCERT Mathematics Class 5',              'NCERT',               'NCERT',             'Mathematics',10, 7, 'Shelf D-3'],
    ['978-8120340152','NCERT Science Class 5',                  'NCERT',               'NCERT',             'Science',    10, 8, 'Shelf C-4'],
    ['978-8120340169','NCERT Hindi Vasant Class 5',             'NCERT',               'NCERT',             'Literature', 10, 9, 'Shelf B-5'],
];

$insertedBooks = 0;
foreach ($books as $b) {
    [$isbn, $title, $author, $publisher, $category, $total, $avail, $location] = $b;
    // Skip if already exists
    $exists = $db->prepare("SELECT id FROM library_books WHERE school_id=1 AND isbn=?");
    $exists->execute([$isbn]);
    if ($exists->fetchColumn()) { $results[] = "⏩ Book '$title' already exists"; continue; }
    try {
        $stmt = $db->prepare("INSERT INTO library_books 
            (school_id, isbn, title, author, publisher, category, total_copies, available_copies, location)
            VALUES (1,?,?,?,?,?,?,?,?)");
        $stmt->execute([$isbn, $title, $author, $publisher, $category, $total, $avail, $location]);
        $insertedBooks++;
    } catch (Exception $ex) {
        $errors[] = "❌ Book '$title' → " . $ex->getMessage();
    }
}
$results[] = "✅ Inserted $insertedBooks library books";

// ─── STEP 5: Fix/create library_transactions (student_id → students.id) ─────
// The enhanced schema uses a separate 'students' table, so student_id must
// reference students.id NOT users.id. We drop and recreate if wrong FK exists.
try {
    // Check if table exists with wrong FK
    $hasTx = $db->query("SHOW TABLES LIKE 'library_transactions'")->fetchColumn();
    if ($hasTx) {
        // Check if the FK references users(id) — if so, drop and recreate
        $fkCheck = $db->query("
            SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'library_transactions'
              AND COLUMN_NAME = 'student_id'
              AND REFERENCED_TABLE_NAME = 'users'
        ")->fetchColumn();
        if ($fkCheck) {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $db->exec("DROP TABLE library_transactions");
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            $results[] = "♻️  Dropped library_transactions (had wrong FK → users)";
        } else {
            $results[] = "⏩ library_transactions already exists with correct FK";
        }
    }
    // Create with correct FK → students.id
    $db->exec("
        CREATE TABLE IF NOT EXISTS library_transactions (
            id           INT PRIMARY KEY AUTO_INCREMENT,
            school_id    INT NOT NULL,
            book_id      INT NOT NULL,
            student_id   INT NOT NULL,
            issued_by    INT NOT NULL,
            returned_to  INT NULL,
            issue_date   DATE NOT NULL,
            due_date     DATE NOT NULL,
            return_date  DATE NULL,
            fine_amount  DECIMAL(8,2) DEFAULT 0,
            status       ENUM('issued','returned','overdue') DEFAULT 'issued',
            remarks      TEXT NULL,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (book_id)    REFERENCES library_books(id),
            FOREIGN KEY (student_id) REFERENCES students(id),
            FOREIGN KEY (issued_by)  REFERENCES users(id)
        )
    ");
    $results[] = "✅ library_transactions table ready (FK → students.id)";
} catch (Exception $e) {
    $errors[] = "❌ library_transactions setup → " . $e->getMessage();
}


// ─── STEP 6: Library transactions ─────────────────────────
$book1 = $db->query("SELECT id FROM library_books WHERE school_id=1 AND isbn='978-0545010221' LIMIT 1")->fetchColumn();
$book2 = $db->query("SELECT id FROM library_books WHERE school_id=1 AND isbn='978-0393970128' LIMIT 1")->fetchColumn();
$book3 = $db->query("SELECT id FROM library_books WHERE school_id=1 AND isbn='978-0131103627' LIMIT 1")->fetchColumn();
$book4 = $db->query("SELECT id FROM library_books WHERE school_id=1 AND isbn='978-0385333481' LIMIT 1")->fetchColumn();

$txData = [
    // [book_id, student_id, issued_by, issue_offset, due_offset, return_offset, status]
    [$book1, 18, $tch_id, -5,  +9,  null, 'issued'],
    [$book2, 18, $tch_id, -22, -8,  null, 'issued'],   // overdue
    [$book3, 18, $tch_id, -35, -21, -23,  'returned'],
    [$book4, 18, $tch_id, -50, -36, -38,  'returned'],
];

$insertedTx = 0;
foreach ($txData as $t) {
    [$bk, $st, $by, $io, $do_, $ro, $status] = $t;
    if (!$bk) { $errors[] = "❌ Transaction skipped - book not found"; continue; }
    $issue_date  = date('Y-m-d', strtotime("$io days"));
    $due_date    = date('Y-m-d', strtotime("$do_ days"));
    $return_date = $ro !== null ? date('Y-m-d', strtotime("$ro days")) : null;
    try {
        $stmt = $db->prepare("INSERT INTO library_transactions
            (school_id, book_id, student_id, issued_by, returned_to, issue_date, due_date, return_date, status)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$bk, $st, $by, $return_date ? $by : null, $issue_date, $due_date, $return_date, $status]);
        $insertedTx++;
    } catch (Exception $ex) {
        $errors[] = "❌ Transaction → " . $ex->getMessage();
    }
}
$results[] = "✅ Inserted $insertedTx library transactions";

// ─── STEP 7: Assignments ──────────────────────────────────-
$assignments_data = [
    [$tch_id, $cls_id, $sec_id, $subj_a, 'Write a paragraph on "My School"',
     'Write a minimum 150-word paragraph describing your school experience. Include your favorite subject and a cherished memory.', '+2 days', 20],
    [$tch_id, $cls_id, $sec_id, $subj_b, 'Chapter 3 – Fractions Worksheet',
     'Complete all exercises from Chapter 3 on Fractions. Show your working clearly. Include diagrams where required.', '+4 days', 30],
    [$tch_id, $cls_id, $sec_id, $subj_a, 'Learn and Recite Poem: Ped ki Yatra',
     'Learn the poem "Ped ki Yatra" from Chapter 5. Be prepared to recite it in class with proper intonation and expression.', '+1 day', 10],
    [$tch_id, $cls_id, $sec_id, $subj_b, 'Multiplication Table Chart (1–20)',
     'Create a colorful chart of multiplication tables from 1 to 20 on an A3 sheet. Use a different color for each table.', '+6 days', 25],
    [$tch_id, $cls_id, $sec_id, $subj_a, 'Reading Comprehension – Chapter 2',
     'Read the passage in Chapter 2 and answer all 10 questions in full sentences. Underline key answers in the passage.', '-1 day', 20],
    [$tch_id, $cls_id, $sec_id, $subj_b, 'Geometry – Draw and Label Shapes',
     'Draw and label 5 geometric shapes. Measure and write the perimeter of each. Include square, circle, triangle, rectangle, pentagon.', '-3 days', 20],
];

$insertedAssign = 0;
$assignIds      = [];
foreach ($assignments_data as $a) {
    [$teacher, $cls, $sec, $sub, $title, $desc, $due_offset, $marks] = $a;
    $due = date('Y-m-d H:i:s', strtotime($due_offset));
    try {
        $stmt = $db->prepare("INSERT INTO assignments
            (teacher_id, class_id, section_id, subject_id, title, description, due_date, max_marks)
            VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$teacher, $cls, $sec, $sub, $title, $desc, $due, $marks]);
        $assignIds[$title] = $db->lastInsertId();
        $insertedAssign++;
    } catch (Exception $ex) {
        $errors[] = "❌ Assignment '$title' → " . $ex->getMessage();
    }
}
$results[] = "✅ Inserted $insertedAssign assignments";

// ─── STEP 8: Assignment submissions ──────────────────────--
$subs = [
    ['Write a paragraph on "My School"',
     'My school is a wonderful place to learn and grow. Every morning I walk through the gates with excitement knowing today will be another day of discovery...', null, null, 'now'],
    ['Reading Comprehension – Chapter 2',
     'Answers: 1. The story is about a brave girl who faced many challenges... 2. The main theme is courage and friendship between the characters...',
     17, 'Good effort! Work on sentence structure and vocabulary.', '-2 days'],
];
$insertedSubs = 0;
foreach ($subs as [$title, $text, $marks, $feedback, $when]) {
    $aid = $assignIds[$title] ?? null;
    if (!$aid) { $errors[] = "⏩ Submission for '$title' – assignment ID not found, skipping"; continue; }
    $submitted_at = date('Y-m-d H:i:s', strtotime($when));
    try {
        $stmt = $db->prepare("INSERT IGNORE INTO assignment_submissions
            (assignment_id, student_id, submission_text, marks_obtained, feedback, submitted_at, graded_at, graded_by)
            VALUES (?,18,?,?,?,?,?,?)");
        $graded_at = $marks !== null ? date('Y-m-d H:i:s', strtotime('-1 day')) : null;
        $graded_by = $marks !== null ? $tch_id : null;
        $stmt->execute([$aid, $text, $marks, $feedback, $submitted_at, $graded_at, $graded_by]);
        $insertedSubs++;
    } catch (Exception $ex) {
        $errors[] = "❌ Submission '$title' → " . $ex->getMessage();
    }
}
$results[] = "✅ Inserted $insertedSubs assignment submissions";

// ─── STEP 9: Attendance ───────────────────────────────────-
$att_records = [
    ['2026-02-01','present',null], ['2026-02-02','present',null], ['2026-02-03','absent','Informed: family function'],
    ['2026-02-04','present',null], ['2026-02-05','present',null], ['2026-02-06','late','Arrived 15 minutes late'],
    ['2026-02-07','present',null], ['2026-02-08','present',null], ['2026-02-09','present',null],
    ['2026-02-10','present',null], ['2026-02-11','absent','Sick – medical certificate provided'],
    ['2026-02-12','absent','Sick leave continued'], ['2026-02-13','present',null],
    ['2026-02-14','present',null], ['2026-02-15','present',null], ['2026-02-16','present',null],
    ['2026-02-17','late','Bus delay reported by parent'], ['2026-02-18','present',null],
    ['2026-02-19','present',null], ['2026-02-20','present',null], ['2026-02-21','present',null],
    ['2026-02-22','present',null], ['2026-02-23','present',null], ['2026-02-24','present',null],
    ['2026-02-25','present',null],
];
$insertedAtt = 0;
foreach ($att_records as [$date, $status, $remarks]) {
    try {
        $stmt = $db->prepare("INSERT IGNORE INTO attendance
            (student_id, class_id, section_id, teacher_id, date, status, remarks)
            VALUES (18,?,?,?,?,?,?)");
        $stmt->execute([$cls_id, $sec_id, $tch_id, $date, $status, $remarks]);
        $insertedAtt++;
    } catch (Exception $ex) {
        $errors[] = "❌ Attendance $date → " . $ex->getMessage();
    }
}
$results[] = "✅ Inserted $insertedAtt attendance records";

// ─── STEP 10: is_published column + Announcements ─────────
$existing_ann_cols = $db->query("SHOW COLUMNS FROM announcements")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('is_published', $existing_ann_cols)) {
    exec_sql($db, "ALTER announcements ADD is_published", 
        "ALTER TABLE announcements ADD COLUMN is_published TINYINT(1) DEFAULT 1 AFTER is_active",
        $results, $errors);
    $db->exec("UPDATE announcements SET is_published = 1 WHERE school_id = 1");
}

$announcements = [
    ['Annual Sports Day – February 28, 2026',
     'Dear Students and Parents, We are pleased to announce our Annual Sports Day on February 28, 2026. All students must report by 8:00 AM in sports uniform. Events include 100m sprint, long jump, relay races, and fun team games. Parents are cordially invited to cheer!',
     'all', 'high'],
    ['Fee Payment Reminder – Last Date: March 5',
     'This is a reminder that the last date for fee payment for the current term is March 5, 2026. Please ensure timely payment to avoid late fees. Payment can be made online through the school portal or at the accounts office between 9 AM – 1 PM.',
     'all', 'urgent'],
    ['Parent-Teacher Meeting – March 1, 2026',
     'A Parent-Teacher Meeting is scheduled for March 1, 2026 from 10:00 AM to 2:00 PM. Parents of all students are requested to attend and discuss their ward\'s academic progress with respective class teachers.',
     'all', 'high'],
    ['New Books Available in School Library',
     'The school library has received an exciting new batch of books in Fiction, Science, Mathematics, and General Knowledge. Students are encouraged to visit and borrow books. Reading regularly improves language skills and general awareness!',
     'students', 'low'],
    ['Republic Day Celebration Highlights',
     'The school celebrated Republic Day with great enthusiasm. Special speeches, cultural performances, and a flag-hoisting ceremony were highlights. Congratulations to all participants and organizers who made this event memorable!',
     'all', 'medium'],
];
$insertedAnn = 0;
foreach ($announcements as [$title, $content, $audience, $priority]) {
    try {
        $stmt = $db->prepare("INSERT INTO announcements
            (school_id, created_by, title, content, target_audience, priority, is_active, is_published)
            VALUES (1,?,?,?,?,?,1,1)");
        $stmt->execute([$admin_id, $title, $content, $audience, $priority]);
        $insertedAnn++;
    } catch (Exception $ex) {
        $errors[] = "❌ Announcement '$title' → " . $ex->getMessage();
    }
}
$results[] = "✅ Inserted $insertedAnn announcements";

// ─── Output ───────────────────────────────────────────────-
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Edducare Test Seed</title>
<style>
  body{font-family:system-ui,sans-serif;max-width:800px;margin:40px auto;padding:20px;background:#f8fafc;}
  h1{color:#3E6E80;} 
  .ok{color:#16a34a;} .err{color:#dc2626;} .info{color:#2563eb;}
  pre{background:#1e293b;color:#e2e8f0;padding:20px;border-radius:10px;overflow:auto;}
  .summary{background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;padding:16px;margin:16px 0;}
  .err-box{background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:16px;margin:16px 0;}
  .warn{color:#d97706;font-weight:600;}
</style>
</head>
<body>
<h1>🌱 Edducare Test Data Seeder</h1>
<p class="warn">⚠️ DELETE this file from your server after running!</p>

<div class="summary">
<h3>✅ Results</h3>
<pre><?php echo implode("\n", $results); ?></pre>
</div>

<?php if ($errors): ?>
<div class="err-box">
<h3>❌ Errors</h3>
<pre><?php echo implode("\n", $errors); ?></pre>
</div>
<?php endif; ?>

<div class="summary">
<strong>🎉 Done! Pull-to-refresh the app to see test data.</strong><br>
<small>Remember to delete this file: <code>backend/seed_test_data.php</code></small>
</div>
</body>
</html>
