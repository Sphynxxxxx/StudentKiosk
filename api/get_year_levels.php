<?php
require_once '../config/database.php';
require_once '../includes/YearLevelHelper.php';

header('Content-Type: application/json');

if (!isset($_GET['program_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Program ID is required']);
    exit;
}

try {
    $programId = $_GET['program_id'];
    $yearLevels = YearLevelHelper::getYearLevelOptions($programId);
    echo json_encode([
        'success' => true,
        'yearLevels' => $yearLevels
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch year levels',
        'message' => $e->getMessage()
    ]);
}
?>