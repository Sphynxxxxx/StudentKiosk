<?php
session_start();

// Check if faculty is logged in
if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Include database configuration
require_once 'config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['section_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$section_id = (int)$_POST['section_id'];
$faculty_id = $_SESSION['faculty_id'];

try {
    // Verify that this section belongs to the logged-in faculty
    $stmt = $pdo->prepare("
        SELECT cs.id, c.course_code, c.course_name, cs.section_name
        FROM class_sections cs
        INNER JOIN courses c ON cs.course_id = c.id
        WHERE cs.id = ? AND cs.faculty_id = ? AND cs.status = 'active'
    ");
    $stmt->execute([$section_id, $faculty_id]);
    $course_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course_info) {
        echo json_encode(['success' => false, 'message' => 'Section not found or unauthorized']);
        exit();
    }
    
    // Get students enrolled in this section
    $stmt = $pdo->prepare("
        SELECT 
            u.id as student_id,
            u.student_id as id_number,
            u.first_name,
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) as full_name,
            u.email,
            e.enrollment_date,
            e.grade,
            e.status as enrollment_status
        FROM enrollments e
        INNER JOIN users u ON e.student_id = u.id
        WHERE e.class_section_id = ? AND e.status = 'enrolled'
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$section_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'course_info' => $course_info,
        'total_students' => count($students)
    ]);

} catch (PDOException $e) {
    error_log("Get students error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}
?>