<?php
require_once __DIR__ . '/../config/database.php';

class Analytics {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    public function updateDailyAnalytics($schoolId, $date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }

        try {
            // Get total students
            $totalStudentsQuery = "SELECT COUNT(*) as total FROM students WHERE school_id = :school_id AND status = 'active'";
            $stmt = $this->conn->prepare($totalStudentsQuery);
            $stmt->bindParam(':school_id', $schoolId);
            $stmt->execute();
            $totalStudents = $stmt->fetch()['total'];

            // Get attendance data for the date
            $attendanceQuery = "SELECT 
                                COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
                                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent
                               FROM attendance 
                               WHERE date = :date AND student_id IN (
                                   SELECT id FROM students WHERE school_id = :school_id
                               )";
            $stmt = $this->conn->prepare($attendanceQuery);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':school_id', $schoolId);
            $stmt->execute();
            $attendance = $stmt->fetch();

            // Get new admissions for the date
            $admissionsQuery = "SELECT COUNT(*) as new_admissions 
                               FROM students 
                               WHERE school_id = :school_id AND DATE(admission_date) = :date";
            $stmt = $this->conn->prepare($admissionsQuery);
            $stmt->bindParam(':school_id', $schoolId);
            $stmt->bindParam(':date', $date);
            $stmt->execute();
            $newAdmissions = $stmt->fetch()['new_admissions'];

            // Get fee collected for the date
            $feeQuery = "SELECT COALESCE(SUM(paid_amount), 0) as fee_collected 
                        FROM student_fees sf
                        JOIN students s ON sf.student_id = s.id
                        WHERE s.school_id = :school_id AND DATE(sf.paid_date) = :date";
            $stmt = $this->conn->prepare($feeQuery);
            $stmt->bindParam(':school_id', $schoolId);
            $stmt->bindParam(':date', $date);
            $stmt->execute();
            $feeCollected = $stmt->fetch()['fee_collected'];

            // Insert or update daily analytics
            $insertQuery = "INSERT INTO daily_analytics 
                           (school_id, date, total_students, present_students, absent_students, new_admissions, fee_collected)
                           VALUES (:school_id, :date, :total_students, :present_students, :absent_students, :new_admissions, :fee_collected)
                           ON DUPLICATE KEY UPDATE
                           total_students = VALUES(total_students),
                           present_students = VALUES(present_students),
                           absent_students = VALUES(absent_students),
                           new_admissions = VALUES(new_admissions),
                           fee_collected = VALUES(fee_collected)";

            $stmt = $this->conn->prepare($insertQuery);
            $stmt->bindParam(':school_id', $schoolId);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':total_students', $totalStudents);
            $stmt->bindParam(':present_students', $attendance['present']);
            $stmt->bindParam(':absent_students', $attendance['absent']);
            $stmt->bindParam(':new_admissions', $newAdmissions);
            $stmt->bindParam(':fee_collected', $feeCollected);
            $stmt->execute();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateCasteAnalytics($schoolId, $academicYearId) {
        try {
            $query = "SELECT 
                        caste,
                        COUNT(*) as total,
                        COUNT(CASE WHEN gender = 'male' THEN 1 END) as male,
                        COUNT(CASE WHEN gender = 'female' THEN 1 END) as female
                      FROM students s
                      JOIN student_enrollments se ON s.id = se.student_id
                      WHERE s.school_id = :school_id 
                      AND se.academic_year_id = :academic_year_id 
                      AND s.status = 'active'
                      GROUP BY caste";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':school_id', $schoolId);
            $stmt->bindParam(':academic_year_id', $academicYearId);
            $stmt->execute();

            $casteData = $stmt->fetchAll();

            foreach ($casteData as $data) {
                $insertQuery = "INSERT INTO caste_analytics 
                               (school_id, academic_year_id, caste, total_students, male_students, female_students)
                               VALUES (:school_id, :academic_year_id, :caste, :total, :male, :female)
                               ON DUPLICATE KEY UPDATE
                               total_students = VALUES(total_students),
                               male_students = VALUES(male_students),
                               female_students = VALUES(female_students)";

                $stmt = $this->conn->prepare($insertQuery);
                $stmt->bindParam(':school_id', $schoolId);
                $stmt->bindParam(':academic_year_id', $academicYearId);
                $stmt->bindParam(':caste', $data['caste']);
                $stmt->bindParam(':total', $data['total']);
                $stmt->bindParam(':male', $data['male']);
                $stmt->bindParam(':female', $data['female']);
                $stmt->execute();
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getDashboardAnalytics($schoolId, $startDate = null, $endDate = null) {
        if (!$startDate) $startDate = date('Y-m-01');
        if (!$endDate) $endDate = date('Y-m-t');

        $stats = [
            'total_students' => 0,
            'total_teachers' => 0,
            'average_attendance' => 0,
            'school_days' => 0,
            'fee_collected' => 0,
            'fee_pending' => 0,
            'pending_fee_count' => 0,
            'caste_distribution' => [],
            'gender_distribution' => [],
            'class_strength' => [],
            'total_books' => 0,
            'exams_pending' => 0,
            'recent_activities' => []
        ];

        try {
            // Total students
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM students WHERE school_id = :school_id AND status = 'active'");
            $stmt->execute([':school_id' => $schoolId]);
            $stats['total_students'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            // Total teachers
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM users WHERE school_id = :school_id AND role_id IN (3,4) AND is_active = 1");
            $stmt->execute([':school_id' => $schoolId]);
            $stats['total_teachers'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            // Attendance
            $stmt = $this->conn->prepare("SELECT 
                                            AVG(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100 as avg_attendance,
                                            COUNT(DISTINCT date) as school_days
                                          FROM attendance a
                                          JOIN students s ON a.student_id = s.id
                                          WHERE s.school_id = :school_id AND a.date BETWEEN :start_date AND :end_date");
            $stmt->execute([':school_id' => $schoolId, ':start_date' => $startDate, ':end_date' => $endDate]);
            $attendanceData = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['average_attendance'] = $attendanceData['avg_attendance'] !== null ? round($attendanceData['avg_attendance'], 1) : 0;
            $stats['school_days'] = (int)($attendanceData['school_days'] ?? 0);

            // Fees
            $stmt = $this->conn->prepare("SELECT 
                                            SUM(CASE WHEN status = 'paid' THEN paid_amount ELSE 0 END) as collected,
                                            SUM(amount) as total_due,
                                            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
                                          FROM student_fees sf
                                          JOIN students s ON sf.student_id = s.id
                                          WHERE s.school_id = :school_id");
            $stmt->execute([':school_id' => $schoolId]);
            $feeData = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['fee_collected'] = (float)($feeData['collected'] ?? 0);
            $stats['fee_pending'] = (float)(($feeData['total_due'] ?? 0) - ($feeData['collected'] ?? 0));
            $stats['pending_fee_count'] = (int)($feeData['pending_count'] ?? 0);

            // Caste
            $stmt = $this->conn->prepare("SELECT caste, COUNT(*) as count FROM students WHERE school_id = :school_id AND status = 'active' GROUP BY caste");
            $stmt->execute([':school_id' => $schoolId]);
            $stats['caste_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Gender
            $stmt = $this->conn->prepare("SELECT gender, COUNT(*) as count FROM students WHERE school_id = :school_id AND status = 'active' GROUP BY gender");
            $stmt->execute([':school_id' => $schoolId]);
            $stats['gender_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Class strength
            $stmt = $this->conn->prepare("SELECT c.name as class_name, COUNT(se.student_id) as strength
                                          FROM classes c
                                          LEFT JOIN student_enrollments se ON c.id = se.class_id
                                          LEFT JOIN students s ON se.student_id = s.id AND s.status = 'active'
                                          WHERE c.school_id = :school_id
                                          GROUP BY c.id, c.name
                                          ORDER BY c.grade_level");
            $stmt->execute([':school_id' => $schoolId]);
            $stats['class_strength'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Books (Handle errors for library table if needed)
            try {
                $stmt = $this->conn->prepare("SELECT SUM(total_copies) as total FROM library_books WHERE school_id = :school_id");
                $stmt->execute([':school_id' => $schoolId]);
                $stats['total_books'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            } catch (Exception $e) {}

            // Exams
            try {
                $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM exams WHERE school_id = :school_id AND end_date >= CURDATE()");
                $stmt->execute([':school_id' => $schoolId]);
                $stats['exams_pending'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            } catch (Exception $e) {}

            // Activity Logs
            try {
                $stmt = $this->conn->prepare("SELECT al.action, al.entity_type, al.created_at, 
                                                CASE 
                                                    WHEN al.user_type = 'user' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = al.user_id)
                                                    WHEN al.user_type = 'student' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM students WHERE id = al.student_id)
                                                END as user_name
                                          FROM activity_logs al
                                          WHERE al.school_id = :school_id
                                          ORDER BY al.created_at DESC
                                          LIMIT 10");
                $stmt->execute([':school_id' => $schoolId]);
                $stats['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}

        } catch (Exception $e) {
            // General catch for safety, but we try to populate as much as possible
        }

        return $stats;
    }

    public function getAdvancedReports($schoolId, $reportType, $filters = []) {
        try {
            switch ($reportType) {
                case 'caste_wise_performance':
                    return $this->getCasteWisePerformanceReport($schoolId, $filters);
                case 'attendance_trends':
                    return $this->getAttendanceTrendsReport($schoolId, $filters);
                case 'fee_defaulters':
                    return $this->getFeeDefaultersReport($schoolId, $filters);
                case 'academic_performance':
                    return $this->getAcademicPerformanceReport($schoolId, $filters);
                default:
                    return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    private function getCasteWisePerformanceReport($schoolId, $filters) {
        $query = "SELECT 
                    s.caste,
                    COUNT(DISTINCT s.id) as total_students,
                    AVG(er.marks_obtained) as avg_marks,
                    AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100 as avg_attendance
                  FROM students s
                  LEFT JOIN exam_results er ON s.id = er.student_id
                  LEFT JOIN attendance a ON s.id = a.student_id
                  WHERE s.school_id = :school_id AND s.status = 'active'";

        if (isset($filters['academic_year_id'])) {
            $query .= " AND EXISTS (SELECT 1 FROM student_enrollments se WHERE se.student_id = s.id AND se.academic_year_id = :academic_year_id)";
        }

        $query .= " GROUP BY s.caste ORDER BY s.caste";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        
        if (isset($filters['academic_year_id'])) {
            $stmt->bindParam(':academic_year_id', $filters['academic_year_id']);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getAttendanceTrendsReport($schoolId, $filters) {
        $startDate = $filters['start_date'] ?? date('Y-m-01');
        $endDate = $filters['end_date'] ?? date('Y-m-t');

        $query = "SELECT 
                    DATE(a.date) as date,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                    COUNT(*) as total_marked,
                    ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_percentage
                  FROM attendance a
                  JOIN students s ON a.student_id = s.id
                  WHERE s.school_id = :school_id 
                  AND a.date BETWEEN :start_date AND :end_date
                  GROUP BY DATE(a.date)
                  ORDER BY a.date";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    private function getFeeDefaultersReport($schoolId, $filters) {
        $query = "SELECT 
                    s.first_name, s.last_name, s.student_id, s.admission_number,
                    s.father_name, s.father_phone, s.caste,
                    c.name as class_name, sec.name as section_name,
                    SUM(sf.amount - sf.paid_amount) as pending_amount,
                    COUNT(sf.id) as pending_installments,
                    MIN(sf.due_date) as oldest_due_date
                  FROM students s
                  JOIN student_enrollments se ON s.id = se.student_id
                  JOIN classes c ON se.class_id = c.id
                  JOIN sections sec ON se.section_id = sec.id
                  JOIN student_fees sf ON s.id = sf.student_id
                  WHERE s.school_id = :school_id 
                  AND sf.status IN ('pending', 'overdue')
                  AND sf.due_date < CURDATE()";

        if (isset($filters['class_id'])) {
            $query .= " AND c.id = :class_id";
        }

        if (isset($filters['caste'])) {
            $query .= " AND s.caste = :caste";
        }

        $query .= " GROUP BY s.id ORDER BY pending_amount DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        
        if (isset($filters['class_id'])) {
            $stmt->bindParam(':class_id', $filters['class_id']);
        }
        
        if (isset($filters['caste'])) {
            $stmt->bindParam(':caste', $filters['caste']);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getAcademicPerformanceReport($schoolId, $filters) {
        $query = "SELECT 
                    s.first_name, s.last_name, s.student_id, s.caste,
                    c.name as class_name, sec.name as section_name,
                    sub.name as subject_name,
                    AVG(er.marks_obtained) as avg_marks,
                    COUNT(er.id) as total_exams,
                    CASE 
                        WHEN AVG(er.marks_obtained) >= 90 THEN 'A+'
                        WHEN AVG(er.marks_obtained) >= 80 THEN 'A'
                        WHEN AVG(er.marks_obtained) >= 70 THEN 'B+'
                        WHEN AVG(er.marks_obtained) >= 60 THEN 'B'
                        WHEN AVG(er.marks_obtained) >= 50 THEN 'C'
                        ELSE 'F'
                    END as grade
                  FROM students s
                  JOIN student_enrollments se ON s.id = se.student_id
                  JOIN classes c ON se.class_id = c.id
                  JOIN sections sec ON se.section_id = sec.id
                  JOIN exam_results er ON s.id = er.student_id
                  JOIN exam_subjects es ON er.exam_subject_id = es.id
                  JOIN subjects sub ON es.subject_id = sub.id
                  WHERE s.school_id = :school_id";

        if (isset($filters['academic_year_id'])) {
            $query .= " AND se.academic_year_id = :academic_year_id";
        }

        if (isset($filters['class_id'])) {
            $query .= " AND c.id = :class_id";
        }

        $query .= " GROUP BY s.id, sub.id ORDER BY c.grade_level, s.first_name";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        
        if (isset($filters['academic_year_id'])) {
            $stmt->bindParam(':academic_year_id', $filters['academic_year_id']);
        }
        
        if (isset($filters['class_id'])) {
            $stmt->bindParam(':class_id', $filters['class_id']);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>
