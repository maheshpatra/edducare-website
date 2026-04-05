<?php
// This script should be run daily via cron job
require_once __DIR__ . '/../includes/analytics.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    $analytics = new Analytics();

    // Get all active schools
    $schoolsQuery = "SELECT id FROM schools WHERE is_active = 1 AND is_blocked = 0";
    $schoolsStmt = $conn->prepare($schoolsQuery);
    $schoolsStmt->execute();
    $schools = $schoolsStmt->fetchAll();

    $yesterday = date('Y-m-d', strtotime('-1 day'));

    foreach ($schools as $school) {
        echo "Updating analytics for school ID: " . $school['id'] . "\n";
        
        // Update daily analytics
        $analytics->updateDailyAnalytics($school['id'], $yesterday);
        
        // Get current academic year for the school
        $academicYearQuery = "SELECT id FROM academic_years WHERE school_id = :school_id AND is_current = 1";
        $academicYearStmt = $conn->prepare($academicYearQuery);
        $academicYearStmt->bindParam(':school_id', $school['id']);
        $academicYearStmt->execute();
        $academicYear = $academicYearStmt->fetch();
        
        if ($academicYear) {
            // Update caste analytics
            $analytics->updateCasteAnalytics($school['id'], $academicYear['id']);
        }
    }

    echo "Analytics update completed successfully\n";

} catch (Exception $e) {
    echo "Error updating analytics: " . $e->getMessage() . "\n";
}
?>
