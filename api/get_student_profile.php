<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Include database configuration
require_once '../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Get parameters
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$academic_year_id = isset($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : null;

if (!$student_id || !$academic_year_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

try {
    // Get student information
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.student_id as student_number,
            u.first_name,
            u.last_name,
            u.email,
            sp.year_level,
            sp.student_status,
            p.program_code,
            p.program_name,
            s.section_name
        FROM users u
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        INNER JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode([
            'success' => false,
            'message' => 'Student not found'
        ]);
        exit;
    }
    
    // Get student grades for the academic year
    $stmt = $pdo->prepare("
        SELECT 
            subj.course_code,
            subj.subject_name,
            subj.credits,
            cs.section_name as class_section_name,
            g.midterm_grade,
            g.final_grade,
            g.overall_grade,
            g.letter_grade,
            g.remarks,
            g.graded_at,
            fac.first_name as faculty_first_name,
            fac.last_name as faculty_last_name
        FROM enrollments e
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN subjects subj ON cs.subject_id = subj.id
        INNER JOIN users fac ON cs.faculty_id = fac.id
        LEFT JOIN grades g ON e.id = g.enrollment_id
        WHERE e.student_id = ? 
            AND cs.academic_year_id = ?
            AND e.status = 'enrolled'
        ORDER BY subj.course_code
    ");
    $stmt->execute([$student_id, $academic_year_id]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add faculty full name to each grade
    foreach ($grades as &$grade) {
        $grade['faculty_name'] = $grade['faculty_first_name'] . ' ' . $grade['faculty_last_name'];
        unset($grade['faculty_first_name']);
        unset($grade['faculty_last_name']);
    }
    
    // Calculate summary statistics
    $total_subjects = 0;
    $passed_subjects = 0;
    $failed_subjects = 0;
    $total_credits = 0;
    $gpa_sum = 0;
    $graded_subjects = 0;
    
    foreach ($grades as $grade) {
        $total_subjects++;
        $total_credits += $grade['credits'];
        
        if ($grade['overall_grade'] !== null) {
            $graded_subjects++;
            $gpa_sum += $grade['overall_grade'];
            
            if ($grade['overall_grade'] <= 3.00) {
                $passed_subjects++;
            } else {
                $failed_subjects++;
            }
        }
    }
    
    $gpa = $graded_subjects > 0 ? $gpa_sum / $graded_subjects : null;
    
    // Determine honor status
    $honor = '-';
    if ($gpa !== null && $graded_subjects >= 3) {
        if ($gpa <= 1.75) {
            $honor = "Dean's List";
        } elseif ($gpa <= 2.00) {
            $honor = "Honor Roll";
        }
    }
    
    $summary = [
        'gpa' => $gpa,
        'total_subjects' => $total_subjects,
        'passed_subjects' => $passed_subjects,
        'failed_subjects' => $failed_subjects,
        'total_credits' => $total_credits,
        'honor' => $honor
    ];
    
    // Return response
    echo json_encode([
        'success' => true,
        'student' => $student,
        'grades' => $grades,
        'summary' => $summary
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching student profile: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}