<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../middleware/auth.php';

// Authentication check
$auth = new AuthMiddleware();
$user = $auth->authenticate();

if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized: Authentication token is missing or invalid']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';
$dateFrom = $_GET['dateFrom'] ?? '';
$dateTo = $_GET['dateTo'] ?? '';
$classId = $_GET['classId'] ?? '';
$sectionId = $_GET['sectionId'] ?? '';

if (empty($type)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Report type is required']);
    exit;
}

try {
    $data = [];
    $filename = $type . "_report_" . date('Y-m-d');

    switch ($type) {
        case 'attendance':
            $query = "SELECT a.date, s.first_name, s.last_name, s.admission_number, c.name as class_name, sec.name as section_name, a.status, a.remarks
                      FROM attendance a
                      JOIN students s ON a.student_id = s.id
                      JOIN classes c ON a.class_id = c.id
                      JOIN sections sec ON a.section_id = sec.id
                      WHERE s.school_id = :school_id";
            if ($dateFrom) $query .= " AND a.date >= :dateFrom";
            if ($dateTo) $query .= " AND a.date <= :dateTo";
            if ($classId) $query .= " AND a.class_id = :classId";
            if ($sectionId) $query .= " AND a.section_id = :sectionId";
            $query .= " ORDER BY s.last_name, s.first_name, a.date ASC";
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':school_id', $user['school_id']);
            if ($dateFrom) $stmt->bindValue(':dateFrom', $dateFrom);
            if ($dateTo) $stmt->bindValue(':dateTo', $dateTo);
            if ($classId) $stmt->bindValue(':classId', $classId);
            if ($sectionId) $stmt->bindValue(':sectionId', $sectionId);
            $stmt->execute();
            $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Matrix generation for Attendance
            $students = [];
            $dates = [];
            $start = $dateFrom ? new DateTime($dateFrom) : new DateTime('first day of this month');
            $end = $dateTo ? new DateTime($dateTo) : new DateTime('last day of this month');
            $end->modify('+1 day'); // Include end date
            $period = new DatePeriod($start, new DateInterval('P1D'), $end);

            $matrix_headers = [];
            foreach ($period as $dt) {
                $d = $dt->format('Y-m-d');
                $dates[] = $d;
                $matrix_headers[$d] = [
                    'day' => $dt->format('j'),
                    'weekday' => substr($dt->format('D'), 0, 2)
                ];
            }

            foreach ($raw_data as $row) {
                $sid = $row['admission_number'];
                if (!isset($students[$sid])) {
                    $students[$sid] = [
                        'name' => $row['first_name'] . ' ' . $row['last_name'],
                        'id' => $sid,
                        'attendance' => []
                    ];
                }
                // Map to matrix legend: P, U, T, E
                $map = ['present' => 'P', 'absent' => 'U', 'late' => 'T', 'half_day' => 'E'];
                $students[$sid]['attendance'][$row['date']] = $map[$row['status']] ?? '?';
            }
            
            $data = [
                'type' => 'matrix',
                'students' => array_values($students),
                'headers' => $matrix_headers,
                'meta' => [
                    'school' => $user['school_name'] ?? '[School Name]',
                    'month_year' => $start->format('F Y'),
                    'class' => $raw_data[0]['class_name'] ?? 'N/A',
                    'section' => $raw_data[0]['section_name'] ?? 'N/A'
                ]
            ];
            break;

        case 'financial':
            // ... (keep as is but update the base data structure if needed)
            $query = "SELECT s.first_name, s.last_name, s.student_id, fc.name as fee_type, sf.amount, sf.paid_amount, sf.due_date, sf.status, sf.paid_date
                      FROM student_fees sf
                      JOIN students s ON sf.student_id = s.id
                      JOIN fee_categories fc ON sf.fee_category_id = fc.id
                      WHERE s.school_id = :school_id";
            if ($dateFrom) $query .= " AND sf.due_date >= :dateFrom";
            if ($dateTo) $query .= " AND sf.due_date <= :dateTo";
            $query .= " ORDER BY sf.due_date DESC";
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':school_id', $user['school_id']);
            if ($dateFrom) $stmt->bindValue(':dateFrom', $dateFrom);
            if ($dateTo) $stmt->bindValue(':dateTo', $dateTo);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'enrollment':
            $query = "SELECT s.student_id, s.admission_number, s.first_name, s.last_name, s.gender, s.date_of_birth, s.admission_date, s.status, c.name as class_name, sec.name as section_name
                      FROM students s
                      LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active'
                      LEFT JOIN classes c ON se.class_id = c.id
                      LEFT JOIN sections sec ON se.section_id = sec.id
                      WHERE s.school_id = :school_id AND s.status != 'deleted'";
            if ($classId) $query .= " AND se.class_id = :classId";
            if ($sectionId) $query .= " AND se.section_id = :sectionId";
            $query .= " ORDER BY c.grade_level, sec.name, s.last_name";

            $stmt = $db->prepare($query);
            $stmt->bindValue(':school_id', $user['school_id']);
            if ($classId) $stmt->bindValue(':classId', $classId);
            if ($sectionId) $stmt->bindValue(':sectionId', $sectionId);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'academic':
            $query = "SELECT s.first_name, s.last_name, s.student_id, e.name as exam_name, sub.name as subject_name, er.marks_obtained, es.max_marks, es.pass_marks
                      FROM exam_results er
                      JOIN students s ON er.student_id = s.id
                      JOIN exam_subjects es ON er.exam_subject_id = es.id
                      JOIN exams e ON es.exam_id = e.id
                      JOIN subjects sub ON es.subject_id = sub.id
                      WHERE s.school_id = :school_id";
            if ($classId) $query .= " AND e.class_id = :classId";
            if ($sectionId) $query .= " AND er.section_id = :sectionId"; 
            $query .= " ORDER BY e.name, s.last_name";

            $stmt = $db->prepare($query);
            $stmt->bindValue(':school_id', $user['school_id']);
            if ($classId) $stmt->bindValue(':classId', $classId);
            if ($sectionId) $stmt->bindValue(':sectionId', $sectionId);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'teacher':
            $query = "SELECT u.first_name, u.last_name, u.email, u.phone, u.employee_id, u.teacher_type, u.qualification, u.experience_years, u.joining_date, u.is_active
                      FROM users u
                      JOIN user_roles ur ON u.role_id = ur.id
                      WHERE u.school_id = :school_id AND ur.name = 'teacher' AND u.is_active != 0";
            if ($classId) {
                // If classId is provided, filter teachers assigned to that class
                $query = "SELECT DISTINCT u.first_name, u.last_name, u.email, u.employee_id, u.teacher_type, u.qualification
                          FROM users u
                          JOIN teacher_assignments ta ON u.id = ta.teacher_id
                          WHERE u.school_id = :school_id AND ta.class_id = :classId";
            }
            $query .= " ORDER BY u.last_name, u.first_name";

            $stmt = $db->prepare($query);
            $stmt->bindValue(':school_id', $user['school_id']);
            if ($classId) $stmt->bindValue(':classId', $classId);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'library':
            $query = "SELECT lb.title, lb.author, lb.isbn, s.first_name as student_first, s.last_name as student_last, lt.issue_date, lt.due_date, lt.return_date, lt.status
                      FROM library_transactions lt
                      JOIN library_books lb ON lt.book_id = lb.id
                      JOIN students s ON lt.student_id = s.id
                      WHERE lb.school_id = :school_id";
            if ($dateFrom) $query .= " AND lt.issue_date >= :dateFrom";
            if ($dateTo) $query .= " AND lt.issue_date <= :dateTo";
            if ($sectionId) {
                $query = str_replace("WHERE lb.school_id = :school_id", "JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active' WHERE lb.school_id = :school_id AND se.section_id = :sectionId", $query);
            }
            $query .= " ORDER BY lt.issue_date DESC";

            $stmt = $db->prepare($query);
            $stmt->bindValue(':school_id', $user['school_id']);
            if ($dateFrom) $stmt->bindValue(':dateFrom', $dateFrom);
            if ($dateTo) $stmt->bindValue(':dateTo', $dateTo);
            if ($sectionId) $stmt->bindValue(':sectionId', $sectionId);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        default:
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid report type']);
            exit;
    }

    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    if (empty($data) || (is_array($data) && isset($data['students']) && empty($data['students']))) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No data found for this report']);
        exit;
    }

    // CSV / Excel Generation
    if ($format === 'csv' || $format === 'excel') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($type === 'attendance' && isset($data['type']) && $data['type'] === 'matrix') {
            // Matrix Header Row 1: Dates
            $h1 = ['ID', 'Student Name'];
            foreach ($data['headers'] as $h) $h1[] = $h['day'];
            fputcsv($output, $h1);
            
            // Matrix Header Row 2: Weekdays
            $h2 = ['', ''];
            foreach ($data['headers'] as $h) $h2[] = $h['weekday'];
            fputcsv($output, $h2);
            
            // Data Rows
            foreach ($data['students'] as $s) {
                $row = [$s['id'], $s['name']];
                foreach ($data['headers'] as $d => $h) {
                    $row[] = $s['attendance'][$d] ?? '';
                }
                fputcsv($output, $row);
            }
        } else {
            // Standard List Header
            fputcsv($output, array_keys($data[0]));
            // Standard List Data
            foreach ($data as $row) fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }

    // PDF / HTML Print View
    if ($format === 'pdf') {
        header('Content-Type: text/html');
        echo "<html><head><title>{$filename}</title>";
        echo "<style>
                body { font-family: 'Helvetica', 'Arial', sans-serif; padding: 30px; color: #333; background: #fff; }
                .sheet { max-width: 100%; overflow-x: auto; }
                .report-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #5d4037; padding-bottom: 15px; }
                .report-header .title-area h1 { margin: 0; color: #5d4037; font-size: 26px; text-transform: uppercase; letter-spacing: 1px; }
                .report-header .school-area { text-align: right; }
                .report-header .school-area h2 { margin: 0; font-size: 20px; color: #333; }
                
                .meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; font-size: 13px; }
                .meta-item { border-bottom: 1px solid #ddd; padding: 5px 0; }
                .meta-label { font-weight: bold; color: #666; margin-right: 10px; }
                
                .legend { background: #efebe9; padding: 8px 15px; border-radius: 4px; font-size: 11px; margin-bottom: 15px; border: 1px solid #d7ccc8; color: #5d4037; }
                
                table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                th, td { border: 1px solid #bcbcbc; padding: 4px 2px; text-align: center; font-size: 10px; }
                th { background-color: #f7f3f2; font-weight: bold; color: #5d4037; }
                .student-col { text-align: left; padding-left: 8px; font-weight: bold; width: 150px; font-size: 11px; }
                .id-col { width: 40px; }
                .weekend { background-color: #fce4ec; }
                
                .status-P { color: #2e7d32; font-weight: bold; }
                .status-U { color: #c62828; font-weight: bold; }
                .status-T { color: #f57c00; font-weight: bold; }
                
                @media print { 
                    .no-print { display: none; } 
                    body { padding: 0; }
                    .legend { -webkit-print-color-adjust: exact; }
                }
              </style></head><body>";
        
        echo "<div class='no-print' style='position: fixed; top: 20px; right: 20px;'>
                <button onclick='window.print()' style='padding: 12px 24px; background: #5d4037; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>Print Report</button>
              </div>";

        if ($type === 'attendance' && isset($data['type']) && $data['type'] === 'matrix') {
            echo "<div class='sheet'>";
            echo "<div class='report-header'>";
            echo "<div class='title-area'><h1>Monthly Class Attendance</h1></div>";
            echo "<div class='school-area'><h2>{$data['meta']['school']}</h2></div>";
            echo "</div>";
            
            echo "<div class='meta-grid'>";
            echo "<div class='meta-item'><span class='meta-label'>Class:</span> {$data['meta']['class']}</div>";
            echo "<div class='meta-item'><span class='meta-label'>Section:</span> {$data['meta']['section']}</div>";
            echo "<div class='meta-item' style='text-align: right;'><span class='meta-label'>Period:</span> {$data['meta']['month_year']}</div>";
            echo "</div>";
            
            echo "<div class='legend'><b>Enter Status:</b> T = Tardy (Late), U = Unexcused (Absent), E = Excused (Half Day), P = Present</div>";
            
            echo "<table><thead>";
            // Weekday symbols
            echo "<tr><th class='id-col'>ID</th><th class='student-col'>Student Name</th>";
            foreach ($data['headers'] as $h) {
                $isWeekend = ($h['weekday'] === 'Sa' || $h['weekday'] === 'Su') ? 'weekend' : '';
                echo "<th class='{$isWeekend}'>{$h['weekday']}</th>";
            }
            echo "</tr>";
            // Day numbers
            echo "<tr><th></th><th></th>";
            foreach ($data['headers'] as $h) {
                $isWeekend = ($h['weekday'] === 'Sa' || $h['weekday'] === 'Su') ? 'weekend' : '';
                echo "<th class='{$isWeekend}'>{$h['day']}</th>";
            }
            echo "</tr></thead><tbody>";
            
            foreach ($data['students'] as $s) {
                echo "<tr><td class='id-col'>{$s['id']}</td><td class='student-col'>{$s['name']}</td>";
                foreach ($data['headers'] as $d => $h) {
                    $val = $s['attendance'][$d] ?? '';
                    $isWeekend = ($h['weekday'] === 'Sa' || $h['weekday'] === 'Su') ? 'weekend' : '';
                    echo "<td class='{$isWeekend} status-{$val}'>{$val}</td>";
                }
                echo "</tr>";
            }
            echo "</tbody></table>";
            echo "</div>";
        } else {
            // Original Generic Template
            echo "<div class='header'>";
            echo "<h1>" . ucwords(str_replace('_', ' ', $type)) . " Report</h1>";
            echo "<p>{$user['school_name']}</p>";
            echo "<p>Generated on " . date('M d, Y') . "</p>";
            echo "</div>";
            echo "<table><thead><tr>";
            foreach (array_keys($data[0]) as $head) echo "<th>" . ucwords(str_replace('_', ' ', $head)) . "</th>";
            echo "</tr></thead><tbody>";
            foreach ($data as $row) {
                echo "<tr>";
                foreach ($row as $cell) echo "<td>" . $cell . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
        
        echo "</body></html>";
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
