<?php
// API file: get_student_enrollments.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id'])) {
    // Check for faculty session (based on your faculty dashboard structure)
    if (!isset($_SESSION['faculty_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Please log in']);
        exit();
    }
    $is_faculty = true;
    $user_id = $_SESSION['faculty_id'];
} else {
    // Check if user is admin
    if ($_SESSION['role'] !== 'administrator') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Insufficient privileges']);
        exit();
    }
    $is_faculty = false;
    $user_id = $_SESSION['user_id'];
}

// Include database configuration
require_once '../config/database.php';

// Check if student_id is provided
if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID is required']);
    exit();
}

$student_id = $_GET['student_id'];

try {
    if ($is_faculty) {
        // For faculty: only show enrollments in courses they teach
        $stmt = $pdo->prepare("
            SELECT 
                e.id as enrollment_id,
                e.class_section_id,
                e.enrollment_date,
                e.status,
                cs.section_name,
                cs.schedule,
                cs.room,
                cs.faculty_id,
                c.course_code,
                c.course_name,
                c.credits,
                CONCAT(uf.first_name, ' ', uf.last_name) as faculty_name,
                d.name as department_name,
                ay.year_start,
                ay.year_end,
                ay.semester
            FROM enrollments e
            INNER JOIN class_sections cs ON e.class_section_id = cs.id
            INNER JOIN courses c ON cs.course_id = c.id
            INNER JOIN users uf ON cs.faculty_id = uf.id
            INNER JOIN departments d ON c.department_id = d.id
            INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
            WHERE e.student_id = ? 
            AND e.status = 'enrolled' 
            AND cs.faculty_id = ?
            ORDER BY c.course_name, cs.section_name
        ");
        
        $stmt->execute([$student_id, $user_id]);
    } else {
        // For administrators: show all enrollments
        $stmt = $pdo->prepare("
            SELECT 
                e.id as enrollment_id,
                e.class_section_id,
                e.enrollment_date,
                e.status,
                cs.section_name,
                cs.schedule,
                cs.room,
                cs.faculty_id,
                c.course_code,
                c.course_name,
                c.credits,
                CONCAT(uf.first_name, ' ', uf.last_name) as faculty_name,
                d.name as department_name,
                ay.year_start,
                ay.year_end,
                ay.semester
            FROM enrollments e
            INNER JOIN class_sections cs ON e.class_section_id = cs.id
            INNER JOIN courses c ON cs.course_id = c.id
            INNER JOIN users uf ON cs.faculty_id = uf.id
            INNER JOIN departments d ON c.department_id = d.id
            INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
            WHERE e.student_id = ? AND e.status = 'enrolled'
            ORDER BY c.course_name, cs.section_name
        ");
        
        $stmt->execute([$student_id]);
    }
    
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return the enrollments as JSON (even if empty array)
    echo json_encode($enrollments);
    
} catch (PDOException $e) {
    error_log("Database error in get_student_enrollments.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("System error in get_student_enrollments.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'System error: ' . $e->getMessage()]);
}
?>