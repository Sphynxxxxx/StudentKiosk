<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Include database configuration
require_once '../config/database.php';

$message = '';
$message_type = '';

// Get navigation parameters
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : null;
$academic_year_id = isset($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : null;

try {
    // Get academic years
    $stmt = $pdo->prepare("SELECT * FROM academic_years ORDER BY year_start DESC, semester");
    $stmt->execute();
    $academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active academic year if none selected
    if (!$academic_year_id && !empty($academic_years)) {
        $stmt = $pdo->prepare("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $active_ay = $stmt->fetch(PDO::FETCH_ASSOC);
        $academic_year_id = $active_ay ? $active_ay['id'] : $academic_years[0]['id'];
    }
    
} catch (PDOException $e) {
    error_log("Rankings error: " . $e->getMessage());
    $message = 'Error loading rankings: ' . $e->getMessage();
    $message_type = 'error';
}

// Fetch data based on navigation level
$departments = [];
$programs = [];
$rankings = [];
$stats = [];
$current_department = null;
$current_program = null;

try {
    if (!$department_id) {
        // Level 1: Show departments
        $stmt = $pdo->prepare("
            SELECT d.*, u.first_name, u.last_name,
                   COUNT(DISTINCT p.id) as program_count,
                   COUNT(DISTINCT s.id) as student_count
            FROM departments d
            LEFT JOIN users u ON d.head_faculty_id = u.id
            LEFT JOIN programs p ON d.id = p.department_id AND p.status = 'active'
            LEFT JOIN student_profiles sp ON p.id = sp.program_id
            LEFT JOIN users s ON sp.user_id = s.id AND s.role = 'student' AND s.status = 'active'
            WHERE d.status = 'active'
            GROUP BY d.id
            ORDER BY d.name
        ");
        $stmt->execute();
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif (!$program_id) {
        // Level 2: Show programs in selected department
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$department_id]);
        $current_department = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COUNT(DISTINCT s.id) as student_count
            FROM programs p
            LEFT JOIN student_profiles sp ON p.id = sp.program_id
            LEFT JOIN users s ON sp.user_id = s.id AND s.role = 'student' AND s.status = 'active'
            WHERE p.department_id = ? AND p.status = 'active'
            GROUP BY p.id
            ORDER BY p.program_name
        ");
        $stmt->execute([$department_id]);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Level 3: Show rankings for selected program
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$department_id]);
        $current_department = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT * FROM programs WHERE id = ?");
        $stmt->execute([$program_id]);
        $current_program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate rankings for this program
        $rankings = calculateRankings($pdo, $academic_year_id, $program_id);
        
        // Get statistics for this program
        $stats = getStatistics($pdo, $academic_year_id, $program_id);
    }
    
} catch (PDOException $e) {
    error_log("Database error in rankings.php: " . $e->getMessage());
    $message = 'Error loading data: ' . $e->getMessage();
    $message_type = 'error';
}

// Function to calculate rankings
function calculateRankings($pdo, $academic_year_id, $program_id) {
    $query = "
        SELECT 
            u.id as student_id,
            u.student_id as student_number,
            u.first_name,
            u.last_name,
            u.email,
            sp.year_level,
            sp.student_status,
            p.program_code,
            p.program_name,
            s.section_name,
            AVG(g.overall_grade) as gpa,
            SUM(subj.credits) as total_credits,
            COUNT(DISTINCT e.id) as total_subjects,
            COUNT(DISTINCT CASE WHEN g.overall_grade <= 3.00 THEN e.id END) as passed_subjects,
            COUNT(DISTINCT CASE WHEN g.overall_grade > 3.00 THEN e.id END) as failed_subjects,
            MIN(g.overall_grade) as highest_grade,
            MAX(g.overall_grade) as lowest_grade
        FROM users u
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        INNER JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        INNER JOIN enrollments e ON u.id = e.student_id
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN subjects subj ON cs.subject_id = subj.id
        INNER JOIN grades g ON e.id = g.enrollment_id
        WHERE u.role = 'student'
          AND u.status = 'active'
          AND cs.academic_year_id = ?
          AND e.status = 'enrolled'
          AND g.overall_grade IS NOT NULL
          AND sp.program_id = ?
        GROUP BY u.id 
        HAVING COUNT(DISTINCT e.id) >= 3 
        ORDER BY gpa ASC, u.last_name, u.first_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$academic_year_id, $program_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add ranking
    $rank = 1;
    $previous_gpa = null;
    $same_rank_count = 0;
    
    foreach ($students as $key => &$student) {
        if ($previous_gpa !== null && abs($student['gpa'] - $previous_gpa) < 0.01) {
            $same_rank_count++;
        } else {
            $rank += $same_rank_count;
            $same_rank_count = 1;
        }
        
        $student['rank'] = $rank;
        $student['total_students'] = count($students);
        
        // Determine honor classification
        if ($student['gpa'] <= 1.75) {
            $student['honor'] = 'Dean\'s List';
            $student['honor_class'] = 'dean';
        } elseif ($student['gpa'] <= 2.00) {
            $student['honor'] = 'Honor Roll';
            $student['honor_class'] = 'honor';
        } else {
            $student['honor'] = '-';
            $student['honor_class'] = 'none';
        }
        
        $previous_gpa = $student['gpa'];
    }
    
    return $students;
}

// Function to get statistics
function getStatistics($pdo, $academic_year_id, $program_id) {
    $query = "
        SELECT 
            COUNT(DISTINCT u.id) as total_students,
            AVG(avg_gpa.gpa) as average_gpa,
            MIN(avg_gpa.gpa) as highest_gpa,
            MAX(avg_gpa.gpa) as lowest_gpa,
            COUNT(DISTINCT CASE WHEN avg_gpa.gpa <= 1.75 THEN u.id END) as deans_list,
            COUNT(DISTINCT CASE WHEN avg_gpa.gpa > 1.75 AND avg_gpa.gpa <= 2.00 THEN u.id END) as honor_roll
        FROM users u
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        INNER JOIN (
            SELECT 
                e.student_id,
                AVG(g.overall_grade) as gpa
            FROM enrollments e
            INNER JOIN class_sections cs ON e.class_section_id = cs.id
            INNER JOIN grades g ON e.id = g.enrollment_id
            WHERE cs.academic_year_id = ?
              AND e.status = 'enrolled'
              AND g.overall_grade IS NOT NULL
            GROUP BY e.student_id
            HAVING COUNT(e.id) >= 3
        ) avg_gpa ON u.id = avg_gpa.student_id
        WHERE u.role = 'student' 
          AND u.status = 'active'
          AND sp.program_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$academic_year_id, $program_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Rankings - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin_dashboard.css" rel="stylesheet">
    <style>
        .breadcrumb {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb-separator {
            color: #6c757d;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .info-card h3 {
            margin: 0 0 10px 0;
            color: #1f2937;
            font-size: 1.1em;
        }
        
        .info-card p {
            margin: 5px 0;
            color: #6b7280;
            font-size: 0.9em;
        }
        
        .info-card .stats {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 0.8em;
            color: #6b7280;
        }
        
        .ranking-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        
        .stat-card.dean {
            border-left-color: #c0c0c0;
        }
        
        .stat-card.honor {
            border-left-color: #cd7f32;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filter-row {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 0.9em;
            font-weight: 500;
            color: #374151;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: white;
            min-width: 200px;
        }
        
        .rankings-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .rankings-table th,
        .rankings-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .rankings-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            font-size: 0.85em;
        }
        
        .rankings-table tr:hover {
            background: #f8f9fa;
        }
        
        .rank-badge {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            border-radius: 50%;
            font-weight: 700;
            font-size: 1.1em;
        }
        
        .rank-1 {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
        }
        
        .rank-2 {
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
            color: #000;
        }
        
        .rank-3 {
            background: linear-gradient(135deg, #cd7f32, #e09856);
            color: #fff;
        }
        
        .rank-other {
            background: #e5e7eb;
            color: #374151;
        }
        
        .gpa-cell {
            font-size: 1.2em;
            font-weight: 700;
            color: #28a745;
        }
        
        .honor-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .honor-dean {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .honor-honor {
            background: #d4edda;
            color: #155724;
        }
        
        .student-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .student-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .student-details {
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .student-row:hover {
            background: #e3f2fd !important;
            cursor: pointer;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 1200px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            padding: 25px 30px;
            background: #1e3a8a;
            color: white;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.5em;
        }
        
        .close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .close:hover {
            transform: scale(1.2);
        }
        
        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .student-profile-header {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .profile-item {
            display: flex;
            flex-direction: column;
        }
        
        .profile-label {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .profile-value {
            font-size: 1.1em;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .grades-detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .grades-detail-table th,
        .grades-detail-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .grades-detail-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #374151;
            font-size: 0.9em;
        }
        
        .grades-detail-table tr:hover {
            background: #f8f9fa;
        }
        
        .grade-excellent { color: #28a745; font-weight: 600; }
        .grade-very-satisfactory { color: #20c997; font-weight: 600; }
        .grade-satisfactory { color: #17a2b8; font-weight: 600; }
        .grade-fair { color: #ffc107; font-weight: 600; }
        .grade-failed { color: #dc3545; font-weight: 600; }
        .grade-incomplete { color: #fd7e14; font-weight: 600; }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .loading i {
            font-size: 3em;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .summary-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .summary-card.success { border-left-color: #28a745; }
        .summary-card.warning { border-left-color: #ffc107; }
        .summary-card.danger { border-left-color: #dc3545; }
        
        .summary-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .summary-label {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .compose-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            display: none;
        }
        
        .compose-section.active {
            display: block;
            animation: slideDown 0.3s;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95em;
            font-family: inherit;
        }
        
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .btn-compose {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-compose:hover {
            background: #218838;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(40,167,69,0.3);
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95em;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .compose-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .alert-success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .rankings-table {
                font-size: 0.9em;
            }
            
            .rankings-table th,
            .rankings-table td {
                padding: 10px 8px;
            }
            
            .ranking-stats {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                min-width: unset;
            }
        }
        
        @media print {
            .sidebar, .header, .filter-section, .btn, .breadcrumb {
                display: none !important;
            }
            
            .rankings-table {
                page-break-inside: avoid;
            }
            
            .main-content {
                margin: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="dashboard-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Breadcrumb Navigation -->
                <div class="breadcrumb">
                    <a href="?">
                        <i class="fas fa-home"></i>
                        Colleges
                    </a>
                    
                    <?php if ($current_department): ?>
                        <span class="breadcrumb-separator">→</span>
                        <a href="?department_id=<?php echo $department_id; ?>&academic_year_id=<?php echo $academic_year_id; ?>">
                            <?php echo htmlspecialchars($current_department['name']); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($current_program): ?>
                        <span class="breadcrumb-separator">→</span>
                        <span>
                            <?php echo htmlspecialchars($current_program['program_name']); ?> Rankings
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Academic Year Filter -->
                <?php if ($department_id): ?>
                <div class="filter-section">
                    <form method="GET" class="filter-row">
                        <?php if ($department_id): ?><input type="hidden" name="department_id" value="<?php echo $department_id; ?>"><?php endif; ?>
                        <?php if ($program_id): ?><input type="hidden" name="program_id" value="<?php echo $program_id; ?>"><?php endif; ?>
                        
                        <div class="filter-group">
                            <label for="academic_year_id">Academic Year:</label>
                            <select name="academic_year_id" id="academic_year_id" class="filter-select" onchange="this.form.submit()">
                                <?php foreach ($academic_years as $ay): ?>
                                <option value="<?php echo $ay['id']; ?>" <?php echo ($academic_year_id == $ay['id']) ? 'selected' : ''; ?>>
                                    <?php echo $ay['year_start'] . '-' . $ay['year_end'] . ' (' . ucfirst($ay['semester']) . ')'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Content based on navigation level -->
                <?php if (!$department_id): ?>
                    <!-- Level 1: Departments -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-building"></i> Select a College
                            </h3>
                        </div>
                        <div class="card-content">
                            <?php if (empty($departments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-building"></i>
                                    <h3>No Colleges Found</h3>
                                    <p>No active colleges are available.</p>
                                </div>
                            <?php else: ?>
                                <div class="cards-grid">
                                    <?php foreach ($departments as $dept): ?>
                                        <div class="info-card" onclick="location.href='?department_id=<?php echo $dept['id']; ?>'">
                                            <h3><?php echo htmlspecialchars($dept['name']); ?></h3>
                                            <?php if ($dept['first_name']): ?>
                                                <p><strong>Head:</strong> <?php echo htmlspecialchars($dept['first_name'] . ' ' . $dept['last_name']); ?></p>
                                            <?php endif; ?>
                                            <div class="stats">
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $dept['program_count']; ?></div>
                                                    <div class="stat-label">Programs</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $dept['student_count']; ?></div>
                                                    <div class="stat-label">Students</div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif (!$program_id): ?>
                    <!-- Level 2: Programs -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-graduation-cap"></i> Select a Program in <?php echo htmlspecialchars($current_department['name']); ?>
                            </h3>
                        </div>
                        <div class="card-content">
                            <?php if (empty($programs)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-graduation-cap"></i>
                                    <h3>No Programs Found</h3>
                                    <p>No active programs are available in this college.</p>
                                </div>
                            <?php else: ?>
                                <div class="cards-grid">
                                    <?php foreach ($programs as $program): ?>
                                        <div class="info-card" onclick="location.href='?department_id=<?php echo $department_id; ?>&program_id=<?php echo $program['id']; ?>&academic_year_id=<?php echo $academic_year_id; ?>'">
                                            <h3><?php echo htmlspecialchars($program['program_name']); ?></h3>
                                            <p><strong>Code:</strong> <?php echo htmlspecialchars($program['program_code']); ?></p>
                                            <p><strong>Duration:</strong> <?php echo $program['duration_years']; ?> years</p>
                                            <div class="stats">
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $program['student_count']; ?></div>
                                                    <div class="stat-label">Students</div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Level 3: Rankings -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-trophy"></i> Student Rankings - <?php echo htmlspecialchars($current_program['program_name']); ?>
                            </h3>
                        </div>

                        <div class="card-content">
                            <!-- Statistics -->
                            <?php if ($stats && $stats['total_students'] > 0): ?>
                            <div class="ranking-stats">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                                    <div class="stat-label">Total Students Ranked</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo number_format($stats['average_gpa'], 4); ?></div>
                                    <div class="stat-label">Average GPA</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo number_format($stats['highest_gpa'], 4); ?></div>
                                    <div class="stat-label">Highest GPA</div>
                                </div>
                                <div class="stat-card dean">
                                    <div class="stat-value"><?php echo $stats['deans_list']; ?></div>
                                    <div class="stat-label">Dean's List</div>
                                </div>
                                <div class="stat-card honor">
                                    <div class="stat-value"><?php echo $stats['honor_roll']; ?></div>
                                    <div class="stat-label">Honor Roll</div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Rankings Table -->
                            <?php if (empty($rankings)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-medal"></i>
                                    <h3>No Rankings Available</h3>
                                    <p>No students with sufficient grades found for this program in the selected academic year.</p>
                                    <p style="font-size: 0.9em; margin-top: 10px;">Note: Students must have at least 3 graded subjects to be ranked.</p>
                                </div>
                            <?php else: ?>
                                <div style="overflow-x: auto;">
                                    <table class="rankings-table">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Student Information</th>
                                                <th>Year</th>
                                                <th>Section</th>
                                                <th>GPA</th>
                                                <th>Subjects</th>
                                                <th>Credits</th>
                                                <th>Honor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rankings as $student): ?>
                                            <tr class="student-row" onclick="viewStudentProfile(<?php echo $student['student_id']; ?>, <?php echo $academic_year_id; ?>)" style="cursor: pointer;">
                                                <td>
                                                    <div class="rank-badge rank-<?php echo ($student['rank'] <= 3) ? $student['rank'] : 'other'; ?>">
                                                        <?php echo $student['rank']; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="student-info">
                                                        <div class="student-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                        <div class="student-details">
                                                            ID: <?php echo htmlspecialchars($student['student_number']); ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['year_level']); ?></td>
                                                <td><?php echo htmlspecialchars($student['section_name'] ?? 'N/A'); ?></td>
                                                <td class="gpa-cell"><?php echo number_format($student['gpa'], 4); ?></td>
                                                <td><?php echo $student['passed_subjects']; ?>/<?php echo $student['total_subjects']; ?></td>
                                                <td><?php echo $student['total_credits']; ?></td>
                                                <td>
                                                    <?php if ($student['honor'] !== '-'): ?>
                                                    <span class="honor-badge honor-<?php echo $student['honor_class']; ?>">
                                                        <?php echo htmlspecialchars($student['honor']); ?>
                                                    </span>
                                                    <?php else: ?>
                                                    -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 0.9em; color: #6c757d;">
                                    <strong>Ranking Criteria:</strong>
                                    <ul style="margin: 10px 0 0 20px;">
                                        <li>Students must have at least 3 graded subjects to be included</li>
                                        <li>GPA is calculated as the average of all overall grades</li>
                                        <li>Lower GPA is better (1.00 = Highest, 5.00 = Failed)</li>
                                        <li>Students with the same GPA receive the same rank</li>
                                        <li>Dean's List: GPA ≤ 1.75</li>
                                        <li>Honor Roll: GPA > 1.75 and ≤ 2.00</li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Student Profile Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-graduate"></i> Student Profile & Grades</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading student profile...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // View student profile
        function viewStudentProfile(studentId, academicYearId) {
            const modal = document.getElementById('studentModal');
            const modalBody = document.getElementById('modalBody');
            
            // Show modal with loading state
            modal.style.display = 'block';
            modalBody.innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading student profile...</p>
                </div>
            `;
            
            // Fetch student data
            fetch(`../api/get_student_profile.php?student_id=${studentId}&academic_year_id=${academicYearId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayStudentProfile(data.student, data.grades, data.summary);
                    } else {
                        modalBody.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <h3>Error Loading Profile</h3>
                                <p>${data.message || 'Unable to load student profile.'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <h3>Error Loading Profile</h3>
                            <p>An error occurred while loading the student profile.</p>
                        </div>
                    `;
                });
        }
        
        // Display student profile
        function displayStudentProfile(student, grades, summary) {
            const modalBody = document.getElementById('modalBody');
            
            let gradesHtml = '';
            if (grades.length === 0) {
                gradesHtml = `
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Grades Available</h3>
                        <p>This student has no graded subjects yet.</p>
                    </div>
                `;
            } else {
                gradesHtml = `
                    <table class="grades-detail-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Subject Name</th>
                                <th>Section</th>
                                <th>Faculty</th>
                                <th>Credits</th>
                                <th>Final</th>
                                <th>Overall</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${grades.map(grade => `
                                <tr>
                                    <td><strong>${escapeHtml(grade.course_code)}</strong></td>
                                    <td>${escapeHtml(grade.subject_name)}</td>
                                    <td>${escapeHtml(grade.class_section_name)}</td>
                                    <td>${escapeHtml(grade.faculty_name)}</td>
                                    <td>${grade.credits}</td>
                                    <td>${grade.final_grade || '-'}</td>
                                    <td class="grade-${grade.remarks ? grade.remarks.toLowerCase().replace(/ /g, '-') : ''}">${grade.overall_grade ? parseFloat(grade.overall_grade).toFixed(4) : '-'}</td>
                                    <td><span class="grade-${grade.remarks ? grade.remarks.toLowerCase().replace(/ /g, '-') : ''}">${grade.remarks || '-'}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }
            
            modalBody.innerHTML = `
                <div class="student-profile-header">
                    <div class="profile-item">
                        <div class="profile-label">Full Name</div>
                        <div class="profile-value">${escapeHtml(student.first_name + ' ' + student.last_name)}</div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Student ID</div>
                        <div class="profile-value">${escapeHtml(student.student_number)}</div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Email</div>
                        <div class="profile-value">${escapeHtml(student.email)}</div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Program</div>
                        <div class="profile-value">${escapeHtml(student.program_name)}</div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Year Level</div>
                        <div class="profile-value">${escapeHtml(student.year_level)}</div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Section</div>
                        <div class="profile-value">${escapeHtml(student.section_name || 'N/A')}</div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Status</div>
                        <div class="profile-value">${escapeHtml(student.student_status ? student.student_status.charAt(0).toUpperCase() + student.student_status.slice(1) : 'Regular')}</div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn-compose" onclick="toggleComposeSection()">
                        <i class="fas fa-envelope"></i> Compose Message
                    </button>
                </div>
                
                <!-- Compose Message Section -->
                <div id="composeSection" class="compose-section">
                    <h3 style="margin-bottom: 15px;"><i class="fas fa-paper-plane"></i> Send Email to ${escapeHtml(student.first_name + ' ' + student.last_name)}</h3>
                    <div id="emailAlert"></div>
                    <form id="emailForm" onsubmit="sendEmail(event, '${escapeHtml(student.email)}', '${escapeHtml(student.first_name + ' ' + student.last_name)}')">
                        <div class="form-group">
                            <label>To:</label>
                            <input type="email" value="${escapeHtml(student.email)}" readonly style="background: #e9ecef;">
                        </div>
                        
                        <div class="form-group">
                            <label for="emailSubject">Subject: <span style="color: red;">*</span></label>
                            <input type="text" id="emailSubject" name="subject" required placeholder="Enter email subject">
                        </div>
                        
                        <div class="form-group">
                            <label for="emailTemplate">Quick Templates:</label>
                            <select id="emailTemplate" onchange="applyTemplate()" class="form-group">
                                <option value="">-- Select a template --</option>
                                <option value="latin_honors">Latin Honors Qualification</option>
                                <option value="congratulations">Congratulations Message</option>
                                <option value="incomplete_warning">Incomplete Grade Warning</option>
                                <option value="reminder">Grade Submission Reminder</option>
                                <option value="custom">Custom Message</option>
                            </select>
                        </div>
                        <input type="hidden" id="studentGPA" value="${summary.gpa ? parseFloat(summary.gpa).toFixed(4) : 'N/A'}">
                        <input type="hidden" id="studentRank" value="${summary.rank || 'N/A'}">
                        <input type="hidden" id="studentHonor" value="${summary.honor || 'N/A'}">
                        
                        <div class="form-group">
                            <label for="emailMessage">Message: <span style="color: red;">*</span></label>
                            <textarea id="emailMessage" name="message" required placeholder="Type your message here..."></textarea>
                        </div>
                        
                        <div class="compose-actions">
                            <button type="submit" class="btn-compose">
                                <i class="fas fa-paper-plane"></i> Send Email
                            </button>
                            <button type="button" class="btn-cancel" onclick="toggleComposeSection()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
                
                <h3 style="margin-bottom: 15px;"><i class="fas fa-chart-bar"></i> Academic Summary</h3>
                <div class="summary-cards">
                    <div class="summary-card success">
                        <div class="summary-value">${summary.gpa ? parseFloat(summary.gpa).toFixed(4) : 'N/A'}</div>
                        <div class="summary-label">GPA</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value">${summary.total_subjects || 0}</div>
                        <div class="summary-label">Total Subjects</div>
                    </div>
                    <div class="summary-card success">
                        <div class="summary-value">${summary.passed_subjects || 0}</div>
                        <div class="summary-label">Passed</div>
                    </div>
                    <div class="summary-card danger">
                        <div class="summary-value">${summary.failed_subjects || 0}</div>
                        <div class="summary-label">Failed</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value">${summary.total_credits || 0}</div>
                        <div class="summary-label">Total Credits</div>
                    </div>
                    <div class="summary-card warning">
                        <div class="summary-value">${summary.honor || '-'}</div>
                        <div class="summary-label">Honor Status</div>
                    </div>
                </div>
                
                <h3 style="margin-bottom: 15px; margin-top: 25px;"><i class="fas fa-list"></i> Detailed Grades</h3>
                ${gradesHtml}
            `;
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('studentModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('studentModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.toString().replace(/[&<>"']/g, m => map[m]) : '';
        }
        
        // Toggle compose section
        function toggleComposeSection() {
            const composeSection = document.getElementById('composeSection');
            if (composeSection) {
                composeSection.classList.toggle('active');
                
                // Clear form if closing
                if (!composeSection.classList.contains('active')) {
                    document.getElementById('emailForm').reset();
                    document.getElementById('emailAlert').innerHTML = '';
                }
            }
        }
        
        // Apply email template with GPA
        function applyTemplate() {
            const template = document.getElementById('emailTemplate').value;
            const subjectField = document.getElementById('emailSubject');
            const messageField = document.getElementById('emailMessage');
            
            // Get student data from hidden inputs
            const gpa = document.getElementById('studentGPA')?.value || 'N/A';
            const rank = document.getElementById('studentRank')?.value || 'N/A';
            const honor = document.getElementById('studentHonor')?.value || 'N/A';
            
            switch(template) {
                case 'latin_honors':
                    subjectField.value = 'Congratulations! You Qualify for Latin Honors';
                    messageField.value = `Dear Student,

We are delighted to inform you that based on your outstanding academic performance, you have qualified for Latin Honors recognition!

Your Achievement:
• Rank: ${rank}
• GPA (General Weighted Average): ${gpa}
• Honor Status: ${honor}

Your exceptional dedication, hard work, and consistent excellence throughout your academic journey have earned you this prestigious distinction. This achievement reflects not only your intellectual capabilities but also your commitment to academic excellence.

Your current GPA places you among the top-performing students in your program. We encourage you to maintain this excellent performance as you continue your studies.

Please visit the Registrar's Office for more information about the Latin Honors recognition ceremony and requirements.

Once again, congratulations on this remarkable achievement!

Best regards,
[Your Name]
[Your Position]
Iloilo Science and Technology University`;
                    break;
                
                case 'congratulations':
                    subjectField.value = 'Congratulations on Your Academic Achievement!';
                    messageField.value = `Dear Student,

Congratulations on your outstanding academic performance!

Your Current Standing:
• GPA: ${gpa}
• Honor Status: ${honor}

Your dedication and hard work have truly paid off. Keep up the excellent work!

Best regards,
[Your Name]
[Your Position]
Iloilo Science and Technology University`;
                    break;
                
                case 'incomplete_warning':
                    subjectField.value = 'URGENT: Incomplete Grade Compliance Required';
                    messageField.value = `Dear Student,

This is an important notice regarding your academic status.

Our records indicate that you have one or more INCOMPLETE (INC) grades that require immediate attention. According to university policy, all incomplete grades must be completed within the specified deadline to avoid academic penalties.

Action Required:
1. Review your incomplete subjects in your student portal
2. Contact the respective faculty members to arrange completion of requirements
3. Submit all pending requirements before the deadline
4. Ensure all incomplete grades are resolved to maintain good academic standing

Consequences of Non-Compliance:
• Incomplete grades may be converted to FAILED (F) after the deadline
• May affect your GPA and academic standing
• May delay graduation or enrollment in advanced courses
• May impact scholarship eligibility

Current GPA: ${gpa}

Please take immediate action to resolve your incomplete grades. If you need assistance or have questions about the completion process, please contact the Registrar's Office or your academic adviser.

Important Deadlines:
• Completion Period: [Insert Deadline]
• Final Submission: [Insert Date]

We encourage you to prioritize completing these requirements to maintain your academic progress and standing.

For assistance, please contact:
• Registrar's Office: [Contact Information]
• Academic Adviser: [Contact Information]

Best regards,
[Your Name]
[Your Position]
Iloilo Science and Technology University`;
                    break;
                    
                case 'reminder':
                    subjectField.value = 'Important: Academic Update';
                    messageField.value = `Dear Student,

This is a reminder regarding your academic requirements. Please ensure all necessary submissions are completed on time.

Current GPA: ${gpa}

Best regards,
[Your Name]
[Your Position]
Iloilo Science and Technology University`;
                    break;
                    
                case 'custom':
                    subjectField.value = '';
                    messageField.value = '';
                    break;
            }
        }
        
        // Send email
        function sendEmail(event, recipientEmail, recipientName) {
            event.preventDefault();
            
            const form = event.target;
            const subject = form.subject.value;
            const message = form.message.value;
            const alertDiv = document.getElementById('emailAlert');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('recipient_email', recipientEmail);
            formData.append('recipient_name', recipientName);
            formData.append('subject', subject);
            formData.append('message', message);
            
            // Send email via AJAX
            fetch('../api/send_student_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertDiv.innerHTML = `
                        <div class="alert-success-message">
                            <i class="fas fa-check-circle"></i>
                            <span>Email sent successfully to ${escapeHtml(recipientEmail)}!</span>
                        </div>
                    `;
                    
                    // Reset form
                    form.reset();
                    
                    // Hide compose section after 3 seconds
                    setTimeout(() => {
                        toggleComposeSection();
                    }, 3000);
                } else {
                    alertDiv.innerHTML = `
                        <div class="alert-error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>${escapeHtml(data.message || 'Failed to send email. Please try again.')}</span>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertDiv.innerHTML = `
                    <div class="alert-error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>An error occurred while sending the email. Please try again.</span>
                    </div>
                `;
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email';
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Esc to close modal
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>