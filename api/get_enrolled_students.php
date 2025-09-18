<?php
// API file: api/get_enrolled_students.php
session_start();
header('Content-Type: application/json');

// Check if faculty is logged in
if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Include database configuration
require_once '../config/database.php';

// Check if section_id is provided
if (!isset($_POST['section_id']) || empty($_POST['section_id'])) {
    echo json_encode(['success' => false, 'message' => 'Section ID is required']);
    exit();
}

$section_id = (int)$_POST['section_id'];

try {
    // Verify that this section belongs to the logged-in faculty
    $stmt = $pdo->prepare("
        SELECT id FROM class_sections 
        WHERE id = ? AND faculty_id = ?
    ");
    $stmt->execute([$section_id, $_SESSION['faculty_id']]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }

    // Get enrolled students for this section
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.student_id,
            u.first_name,
            u.last_name,
            u.email,
            e.enrollment_date,
            e.status,
            CONCAT(u.first_name, ' ', u.last_name) as full_name
        FROM enrollments e
        INNER JOIN users u ON e.student_id = u.id
        WHERE e.class_section_id = ? AND e.status = 'enrolled'
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$section_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'students' => $students
    ]);

} catch (PDOException $e) {
    error_log("API Error in get_enrolled_students.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}
?>