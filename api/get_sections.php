<?php
require_once '../config/database.php';

if (isset($_GET['program_id']) && isset($_GET['year_level'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, section_name 
            FROM sections 
            WHERE program_id = ? AND year_level = ? AND status = 'active'
            ORDER BY section_name
        ");
        $stmt->execute([$_GET['program_id'], $_GET['year_level']]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($sections);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>

