<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session first
session_start();



// Include database configuration
require_once '../config/database.php';

// Get navigation parameters
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : null;
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;
$academic_year_id = isset($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : null;

// Handle grade updates
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'update_grade':
                $grade_id = $_POST['grade_id'];
                $final_grade = !empty($_POST['final_grade']) ? trim(strtoupper($_POST['final_grade'])) : null;
                $completion_grade = !empty($_POST['completion_grade']) ? trim($_POST['completion_grade']) : null;
                
                // Calculate overall grade, letter grade, and remarks
                $overall_grade = null;
                $letter_grade = null;
                $remarks = null;
                
                // Handle INC (Incomplete)
                if ($final_grade === 'INC') {
                    $letter_grade = 'INC';
                    $remarks = 'Incomplete';
                    $overall_grade = null;
                    
                    // If completion grade is provided, use it for overall
                    if ($completion_grade && is_numeric($completion_grade)) {
                        $completion_num = (float)$completion_grade;
                        if ($completion_num >= 1.00 && $completion_num <= 5.00) {
                            $overall_grade = $completion_num;
                            
                            // Calculate remarks based on completion grade - UPDATED SCALE
                            if ($overall_grade >= 1.00 && $overall_grade <= 1.50) {
                                $letter_grade = 'A';
                                $remarks = 'Excellent';
                            } elseif ($overall_grade >= 1.60 && $overall_grade <= 2.00) {
                                $letter_grade = 'B';
                                $remarks = 'Very Satisfactory';
                            } elseif ($overall_grade >= 2.10 && $overall_grade <= 2.50) {
                                $letter_grade = 'C';
                                $remarks = 'Satisfactory';
                            } elseif ($overall_grade >= 2.60 && $overall_grade <= 3.00) {
                                $letter_grade = 'D';
                                $remarks = 'Fair';
                            } else {
                                $letter_grade = 'F';
                                $remarks = 'Failed';
                            }
                        }
                    }
                }
                // Handle DRP (Dropped)
                elseif ($final_grade === 'DRP') {
                    $letter_grade = 'DRP';
                    $remarks = 'Dropped';
                    $overall_grade = null;
                }
                // Calculate for numeric grades - UPDATED SCALE
                elseif (is_numeric($final_grade)) {
                    $overall_grade = (float)$final_grade;
                    
                    if ($overall_grade >= 1.00 && $overall_grade <= 1.50) {
                        $letter_grade = 'A';
                        $remarks = 'Excellent';
                    } elseif ($overall_grade >= 1.60 && $overall_grade <= 2.00) {
                        $letter_grade = 'B';
                        $remarks = 'Very Satisfactory';
                    } elseif ($overall_grade >= 2.10 && $overall_grade <= 2.50) {
                        $letter_grade = 'C';
                        $remarks = 'Satisfactory';
                    } elseif ($overall_grade >= 2.60 && $overall_grade <= 3.00) {
                        $letter_grade = 'D';
                        $remarks = 'Fair';
                    } else {
                        $letter_grade = 'F';
                        $remarks = 'Failed';
                    }
                }
                
                $stmt = $pdo->prepare("
                    UPDATE grades 
                    SET final_grade = ?, completion_grade = ?, overall_grade = ?, 
                        letter_grade = ?, remarks = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$final_grade, $completion_grade, $overall_grade, $letter_grade, $remarks, $grade_id]);
                
                $message = 'Grade updated successfully!';
                $message_type = 'success';
                break;
                
            case 'bulk_update_grades':
                $updated_count = 0;
                $pdo->beginTransaction();
                
                foreach ($_POST['grades'] as $grade_id => $grade_data) {
                    if (!empty($grade_data['final_grade'])) {
                        $final = trim(strtoupper($grade_data['final_grade']));
                        $completion = !empty($grade_data['completion_grade']) ? trim($grade_data['completion_grade']) : null;
                        
                        $overall = null;
                        $letter = null;
                        $remarks = null;
                        
                        // Handle INC
                        if ($final === 'INC') {
                            $letter = 'INC';
                            $remarks = 'Incomplete';
                            
                            // If completion grade is provided, use it - UPDATED SCALE
                            if ($completion && is_numeric($completion)) {
                                $completion_num = (float)$completion;
                                if ($completion_num >= 1.00 && $completion_num <= 5.00) {
                                    $overall = $completion_num;
                                    
                                    if ($overall >= 1.00 && $overall <= 1.50) {
                                        $letter = 'A'; $remarks = 'Excellent';
                                    } elseif ($overall >= 1.60 && $overall <= 2.00) {
                                        $letter = 'B'; $remarks = 'Very Satisfactory';
                                    } elseif ($overall >= 2.10 && $overall <= 2.50) {
                                        $letter = 'C'; $remarks = 'Satisfactory';
                                    } elseif ($overall >= 2.60 && $overall <= 3.00) {
                                        $letter = 'D'; $remarks = 'Fair';
                                    } else {
                                        $letter = 'F'; $remarks = 'Failed';
                                    }
                                }
                            }
                        }
                        // Handle DRP
                        elseif ($final === 'DRP') {
                            $letter = 'DRP';
                            $remarks = 'Dropped';
                        }
                        // Calculate numeric grades - UPDATED SCALE
                        elseif (is_numeric($final)) {
                            $overall = (float)$final;
                            
                            if ($overall >= 1.00 && $overall <= 1.50) {
                                $letter = 'A'; $remarks = 'Excellent';
                            } elseif ($overall >= 1.60 && $overall <= 2.00) {
                                $letter = 'B'; $remarks = 'Very Satisfactory';
                            } elseif ($overall >= 2.10 && $overall <= 2.50) {
                                $letter = 'C'; $remarks = 'Satisfactory';
                            } elseif ($overall >= 2.60 && $overall <= 3.00) {
                                $letter = 'D'; $remarks = 'Fair';
                            } else {
                                $letter = 'F'; $remarks = 'Failed';
                            }
                        }
                        
                        $stmt = $pdo->prepare("
                            UPDATE grades 
                            SET final_grade = ?, completion_grade = ?, overall_grade = ?, 
                                letter_grade = ?, remarks = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$final, $completion, $overall, $letter, $remarks, $grade_id]);
                        $updated_count++;
                    }
                }
                
                $pdo->commit();
                $message = "Successfully updated $updated_count grades!";
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $message = 'Error updating grades: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Grade update error: " . $e->getMessage());
    }
}

// Get academic years for dropdown
try {
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
    $academic_years = [];
    error_log("Error fetching academic years: " . $e->getMessage());
}

// Fetch data based on navigation level
$departments = [];
$programs = [];
$sections = [];
$students_grades = [];
$current_department = null;
$current_program = null;
$current_section = null;

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
                   COUNT(DISTINCT s.id) as student_count,
                   COUNT(DISTINCT sec.id) as section_count
            FROM programs p
            LEFT JOIN student_profiles sp ON p.id = sp.program_id
            LEFT JOIN users s ON sp.user_id = s.id AND s.role = 'student' AND s.status = 'active'
            LEFT JOIN sections sec ON p.id = sec.program_id AND sec.status = 'active'
            WHERE p.department_id = ? AND p.status = 'active'
            GROUP BY p.id
            ORDER BY p.program_name
        ");
        $stmt->execute([$department_id]);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif (!$section_id) {
        // Level 3: Show sections in selected program
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$department_id]);
        $current_department = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT * FROM programs WHERE id = ?");
        $stmt->execute([$program_id]);
        $current_program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT s.*, u.first_name as adviser_first_name, u.last_name as adviser_last_name,
                   COUNT(DISTINCT sp.user_id) as student_count
            FROM sections s
            LEFT JOIN users u ON s.adviser_id = u.id
            LEFT JOIN student_profiles sp ON s.id = sp.section_id
            WHERE s.program_id = ? AND s.academic_year_id = ? AND s.status = 'active'
            GROUP BY s.id
            ORDER BY s.year_level, s.section_name
        ");
        $stmt->execute([$program_id, $academic_year_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Level 4: Show students and grades in selected section
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$department_id]);
        $current_department = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT * FROM programs WHERE id = ?");
        $stmt->execute([$program_id]);
        $current_program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
        $stmt->execute([$section_id]);
        $current_section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get students with their grades
        $stmt = $pdo->prepare("
            SELECT 
                u.id as student_id,
                u.student_id as student_number,
                u.first_name,
                u.last_name,
                u.email,
                sp.student_status,
                subj.id as subject_id,
                subj.course_code,
                subj.subject_name,
                subj.credits,
                cs.id as class_section_id,
                cs.section_name as class_section_name,
                e.id as enrollment_id,
                g.id as grade_id,
                g.final_grade,
                g.completion_grade,
                g.overall_grade,
                g.letter_grade,
                g.remarks,
                g.graded_at,
                fac.first_name as faculty_first_name,
                fac.last_name as faculty_last_name
            FROM users u
            INNER JOIN student_profiles sp ON u.id = sp.user_id
            INNER JOIN enrollments e ON u.id = e.student_id AND e.status = 'enrolled'
            INNER JOIN class_sections cs ON e.class_section_id = cs.id
            INNER JOIN subjects subj ON cs.subject_id = subj.id
            INNER JOIN users fac ON cs.faculty_id = fac.id
            LEFT JOIN grades g ON e.id = g.enrollment_id
            WHERE sp.section_id = ? 
                AND cs.academic_year_id = ?
                AND u.role = 'student' 
                AND u.status = 'active'
            ORDER BY u.last_name, u.first_name, subj.course_code
        ");
        $stmt->execute([$section_id, $academic_year_id]);
        $raw_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize data by student
        $students_grades = [];
        foreach ($raw_grades as $row) {
            $student_id = $row['student_id'];
            
            if (!isset($students_grades[$student_id])) {
                $students_grades[$student_id] = [
                    'student_info' => [
                        'id' => $row['student_id'],
                        'student_number' => $row['student_number'],
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'email' => $row['email'],
                        'student_status' => $row['student_status']
                    ],
                    'subjects' => []
                ];
            }
            
            // Create grade record if it doesn't exist
            if (!$row['grade_id']) {
                try {
                    $create_grade_stmt = $pdo->prepare("
                        INSERT INTO grades (enrollment_id, graded_by) 
                        VALUES (?, ?)
                    ");
                    $create_grade_stmt->execute([$row['enrollment_id'], $_SESSION['student_id']]);
                    $row['grade_id'] = $pdo->lastInsertId();
                } catch (PDOException $e) {
                    error_log("Error creating grade record: " . $e->getMessage());
                }
            }
            
            $students_grades[$student_id]['subjects'][] = [
                'subject_id' => $row['subject_id'],
                'course_code' => $row['course_code'],
                'subject_name' => $row['subject_name'],
                'credits' => $row['credits'],
                'class_section_name' => $row['class_section_name'],
                'faculty_name' => $row['faculty_first_name'] . ' ' . $row['faculty_last_name'],
                'grade_id' => $row['grade_id'],
                'final_grade' => $row['final_grade'],
                'completion_grade' => $row['completion_grade'],
                'overall_grade' => $row['overall_grade'],
                'letter_grade' => $row['letter_grade'],
                'remarks' => $row['remarks'],
                'graded_at' => $row['graded_at']
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Database error in manage_grades.php: " . $e->getMessage());
    $message = 'Error loading data: ' . $e->getMessage();
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades - ISATU Kiosk System</title>
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
        
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .grades-table th,
        .grades-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .grades-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #374151;
        }
        
        .grades-table tr:hover {
            background: #f8f9fa;
        }
        
        .grade-input {
            width: 80px;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            text-align: center;
        }
        
        .grade-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .completion-input {
            width: 80px;
            padding: 6px 8px;
            border: 2px solid #ffc107;
            border-radius: 4px;
            text-align: center;
            background-color: #fff9e6;
        }
        
        .completion-input:focus {
            outline: none;
            border-color: #ff9800;
            background-color: #ffffff;
            box-shadow: 0 0 0 2px rgba(255, 152, 0, 0.25);
        }
        
        .completion-input:disabled {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .student-header {
            background: #e3f2fd;
            font-weight: bold;
        }
        
        .subject-row {
            background: #fafafa;
        }
        
        .remarks-cell {
            font-weight: 500;
        }
        
        .remarks-excellent { color: #28a745; }
        .remarks-very-satisfactory { color: #20c997; }
        .remarks-satisfactory { color: #17a2b8; }
        .remarks-fair { color: #ffc107; }
        .remarks-failed { color: #dc3545; }
        .remarks-incomplete { color: #fd7e14; font-weight: 500; }
        .remarks-dropped { color: #6c757d; font-weight: 500; }
        
        .bulk-actions {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
        
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .grades-table {
                font-size: 0.9em;
            }
            
            .grade-input, .completion-input {
                width: 60px;
                padding: 4px 6px;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                min-width: unset;
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
                        <a href="?department_id=<?php echo $department_id; ?>&program_id=<?php echo $program_id; ?>&academic_year_id=<?php echo $academic_year_id; ?>">
                            <?php echo htmlspecialchars($current_program['program_name']); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($current_section): ?>
                        <span class="breadcrumb-separator">→</span>
                        <span>
                            <?php echo htmlspecialchars($current_section['year_level'] . ' Year - Section ' . $current_section['section_name']); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Academic Year Filter -->
                <?php if ($department_id): ?>
                <div class="filter-section">
                    <form method="GET" class="filter-row">
                        <?php if ($department_id): ?><input type="hidden" name="department_id" value="<?php echo $department_id; ?>"><?php endif; ?>
                        <?php if ($program_id): ?><input type="hidden" name="program_id" value="<?php echo $program_id; ?>"><?php endif; ?>
                        <?php if ($section_id): ?><input type="hidden" name="section_id" value="<?php echo $section_id; ?>"><?php endif; ?>
                        
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
                                <i class="fas fa-building"></i> Colleges
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
                                <i class="fas fa-graduation-cap"></i> Programs in <?php echo htmlspecialchars($current_department['name']); ?>
                            </h3>
                        </div>
                        <div class="card-content">
                            <?php if (empty($programs)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-graduation-cap"></i>
                                    <h3>No Programs Found</h3>
                                    <p>No active programs are available in this department.</p>
                                </div>
                            <?php else: ?>
                                <div class="cards-grid">
                                    <?php foreach ($programs as $program): ?>
                                        <div class="info-card" onclick="location.href='?department_id=<?php echo $department_id; ?>&program_id=<?php echo $program['id']; ?>&academic_year_id=<?php echo $academic_year_id; ?>'">
                                            <h3><?php echo htmlspecialchars($program['program_name']); ?></h3>
                                            <p><strong>Duration:</strong> <?php echo $program['duration_years']; ?> years</p>
                                            <div class="stats">
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $program['section_count']; ?></div>
                                                    <div class="stat-label">Sections</div>
                                                </div>
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

                <?php elseif (!$section_id): ?>
                    <!-- Level 3: Sections -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users"></i> Sections in <?php echo htmlspecialchars($current_program['program_name']); ?>
                            </h3>
                        </div>
                        <div class="card-content">
                            <?php if (empty($sections)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h3>No Sections Found</h3>
                                    <p>No active sections are available for this program in the selected academic year.</p>
                                </div>
                            <?php else: ?>
                                <div class="cards-grid">
                                    <?php foreach ($sections as $section): ?>
                                        <div class="info-card" onclick="location.href='?department_id=<?php echo $department_id; ?>&program_id=<?php echo $program_id; ?>&section_id=<?php echo $section['id']; ?>&academic_year_id=<?php echo $academic_year_id; ?>'">
                                            <h3>Section <?php echo htmlspecialchars($section['section_name']); ?></h3>
                                            <p><strong>Year Level:</strong> <?php echo htmlspecialchars($section['year_level']); ?> Year</p>
                                            <?php if ($section['adviser_first_name']): ?>
                                                <p><strong>Adviser:</strong> <?php echo htmlspecialchars($section['adviser_first_name'] . ' ' . $section['adviser_last_name']); ?></p>
                                            <?php endif; ?>
                                            <div class="stats">
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $section['student_count']; ?></div>
                                                    <div class="stat-label">Students</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $section['max_students']; ?></div>
                                                    <div class="stat-label">Max Capacity</div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Level 4: Students and Grades -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-line"></i> Grades Management - 
                                <?php echo htmlspecialchars($current_program['program_name']); ?> - 
                                <?php echo htmlspecialchars($current_section['year_level'] . ' Year Section ' . $current_section['section_name']); ?>
                            </h3>
                        </div>
                        <div class="card-content">
                            <?php if (empty($students_grades)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <h3>No Student Records Found</h3>
                                    <p>No enrolled students found for this section in the selected academic year.</p>
                                </div>
                            <?php else: ?>
                                <form method="POST" id="gradesForm">
                                    <input type="hidden" name="action" value="bulk_update_grades">
                                    
                                    <div class="bulk-actions">
                                        <div>
                                            <strong><?php echo count($students_grades); ?></strong> students found
                                        </div>
                                        <div>
                                            <button type="submit" class="btn btn-success" onclick="return confirm('Save all grade changes?')">
                                                <i class="fas fa-save"></i> Save All Grades
                                            </button>
                                            <button type="button" class="btn btn-outline" onclick="exportGrades()">
                                                <i class="fas fa-download"></i> Export
                                            </button>
                                        </div>
                                    </div>

                                    <div style="overflow-x: auto;">
                                        <table class="grades-table">
                                            <thead>
                                                <tr>
                                                    <th>Student</th>
                                                    <th>Subject</th>
                                                    <th>Faculty</th>
                                                    <th>Credits</th>
                                                    <th>Final Grade</th>
                                                    <th>Completion Grade</th>
                                                    <th>Overall</th>
                                                    <th>Remarks</th>
                                                    <th>Last Updated</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($students_grades as $student_data): ?>
                                                    <?php $student = $student_data['student_info']; ?>
                                                    <?php $subjects = $student_data['subjects']; ?>
                                                    
                                                    <?php foreach ($subjects as $index => $subject): ?>
                                                        <tr class="<?php echo $index === 0 ? 'student-header' : 'subject-row'; ?>">
                                                            <?php if ($index === 0): ?>
                                                                <td rowspan="<?php echo count($subjects); ?>" class="student-cell">
                                                                    <div>
                                                                        <strong><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></strong>
                                                                    </div>
                                                                    <div style="font-size: 0.9em; color: #6b7280;">
                                                                        ID: <?php echo htmlspecialchars($student['student_number']); ?>
                                                                    </div>
                                                                    <div style="font-size: 0.8em; color: #6b7280;">
                                                                        Status: <?php echo htmlspecialchars(ucfirst($student['student_status'] ?? 'regular')); ?>
                                                                    </div>
                                                                </td>
                                                            <?php endif; ?>
                                                            
                                                            <td>
                                                                <div><strong><?php echo htmlspecialchars($subject['course_code']); ?></strong></div>
                                                                <div style="font-size: 0.9em; color: #6b7280;">
                                                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                                </div>
                                                                <div style="font-size: 0.8em; color: #6b7280;">
                                                                    Section: <?php echo htmlspecialchars($subject['class_section_name']); ?>
                                                                </div>
                                                            </td>
                                                            
                                                            <td style="font-size: 0.9em;">
                                                                <?php echo htmlspecialchars($subject['faculty_name']); ?>
                                                            </td>
                                                            
                                                            <td class="text-center">
                                                                <?php echo $subject['credits']; ?>
                                                            </td>
                                                            
                                                            <td>
                                                                <input type="text" 
                                                                    name="grades[<?php echo $subject['grade_id']; ?>][final_grade]" 
                                                                    value="<?php echo htmlspecialchars($subject['final_grade'] ?? ''); ?>" 
                                                                    class="grade-input" 
                                                                    data-grade-id="<?php echo $subject['grade_id']; ?>"
                                                                    onchange="handleFinalGradeChange(<?php echo $subject['grade_id']; ?>)">
                                                            </td>

                                                            <td>
                                                                <input type="text" 
                                                                    name="grades[<?php echo $subject['grade_id']; ?>][completion_grade]" 
                                                                    value="<?php echo htmlspecialchars($subject['completion_grade'] ?? ''); ?>" 
                                                                    class="completion-input" 
                                                                    id="completion_<?php echo $subject['grade_id']; ?>"
                                                                    placeholder="1.00-5.00"
                                                                    <?php echo (strtoupper($subject['final_grade'] ?? '') !== 'INC') ? 'disabled' : ''; ?>
                                                                    onchange="calculateOverall(<?php echo $subject['grade_id']; ?>)">
                                                            </td>
                                                            
                                                            <td class="overall-grade" id="overall_<?php echo $subject['grade_id']; ?>">
                                                                <?php echo $subject['overall_grade'] ? number_format($subject['overall_grade'], 4) : '-'; ?>
                                                            </td>
                                                            
                                                            <td class="remarks-cell" id="remarks_<?php echo $subject['grade_id']; ?>">
                                                                <span class="remarks-<?php echo strtolower(str_replace(' ', '-', $subject['remarks'] ?? '')); ?>">
                                                                    <?php echo htmlspecialchars($subject['remarks'] ?? '-'); ?>
                                                                </span>
                                                            </td>
                                                            
                                                            <td style="font-size: 0.8em; color: #6b7280;">
                                                                <?php echo $subject['graded_at'] ? date('M d, Y g:i A', strtotime($subject['graded_at'])) : 'Not graded'; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Handle final grade change to enable/disable completion grade input
        function handleFinalGradeChange(gradeId) {
            const finalInput = document.querySelector(`input[name="grades[${gradeId}][final_grade]"]`);
            const completionInput = document.getElementById(`completion_${gradeId}`);
            const finalValue = finalInput.value.trim().toUpperCase();
            
            // Enable completion input only if final grade is INC
            if (finalValue === 'INC') {
                completionInput.disabled = false;
                completionInput.focus();
            } else {
                completionInput.disabled = true;
                completionInput.value = '';
            }
            
            // Calculate overall grade
            calculateOverall(gradeId);
        }

        // Calculate overall grade and update display - UPDATED SCALE
        function calculateOverall(gradeId) {
            const finalInput = document.querySelector(`input[name="grades[${gradeId}][final_grade]"]`);
            const completionInput = document.getElementById(`completion_${gradeId}`);
            const overallCell = document.getElementById(`overall_${gradeId}`);
            const remarksCell = document.getElementById(`remarks_${gradeId}`);
            
            const finalValue = finalInput.value.toUpperCase().trim();
            const completionValue = completionInput.value.trim();
            
            // Handle INC (Incomplete)
            if (finalValue === 'INC') {
                // If completion grade is provided, use it
                if (completionValue) {
                    const completion = parseFloat(completionValue);
                    if (!isNaN(completion) && completion >= 1.00 && completion <= 5.00) {
                        const overall = completion;
                        overallCell.textContent = overall.toFixed(4);
                        
                        // Determine remarks based on completion grade - UPDATED SCALE
                        let remarks = '';
                        let remarksClass = '';
                        
                        if (overall >= 1.00 && overall <= 1.50) {
                            remarks = 'Excellent'; remarksClass = 'remarks-excellent';
                        } else if (overall >= 1.60 && overall <= 2.00) {
                            remarks = 'Very Satisfactory'; remarksClass = 'remarks-very-satisfactory';
                        } else if (overall >= 2.10 && overall <= 2.50) {
                            remarks = 'Satisfactory'; remarksClass = 'remarks-satisfactory';
                        } else if (overall >= 2.60 && overall <= 3.00) {
                            remarks = 'Fair'; remarksClass = 'remarks-fair';
                        } else {
                            remarks = 'Failed'; remarksClass = 'remarks-failed';
                        }
                        
                        remarksCell.innerHTML = `<span class="${remarksClass}">${remarks}</span>`;
                    } else {
                        // Invalid completion grade
                        overallCell.textContent = '-';
                        remarksCell.innerHTML = '<span class="remarks-incomplete">Incomplete</span>';
                    }
                } else {
                    // No completion grade yet
                    overallCell.textContent = '-';
                    remarksCell.innerHTML = '<span class="remarks-incomplete">Incomplete</span>';
                }
                return;
            }
            
            // Handle DRP (Dropped)
            if (finalValue === 'DRP') {
                overallCell.textContent = '-';
                remarksCell.innerHTML = '<span class="remarks-dropped">Dropped</span>';
                return;
            }
            
            const final = parseFloat(finalValue);
            
            if (!isNaN(final)) {
                const overall = final;
                
                // Update overall grade display
                overallCell.textContent = overall.toFixed(4);
                
                // Determine remarks - UPDATED SCALE
                let remarks = '';
                let remarksClass = '';
                
                if (overall >= 1.00 && overall <= 1.50) {
                    remarks = 'Excellent';
                    remarksClass = 'remarks-excellent';
                } else if (overall >= 1.60 && overall <= 2.00) {
                    remarks = 'Very Satisfactory';
                    remarksClass = 'remarks-very-satisfactory';
                } else if (overall >= 2.10 && overall <= 2.50) {
                    remarks = 'Satisfactory';
                    remarksClass = 'remarks-satisfactory';
                } else if (overall >= 2.60 && overall <= 3.00) {
                    remarks = 'Fair';
                    remarksClass = 'remarks-fair';
                } else {
                    remarks = 'Failed';
                    remarksClass = 'remarks-failed';
                }
                
                remarksCell.innerHTML = `<span class="${remarksClass}">${remarks}</span>`;
            } else {
                overallCell.textContent = '-';
                remarksCell.innerHTML = '<span>-</span>';
            }
        }
        
        // Export grades functionality
        function exportGrades() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('export', 'csv');
            window.open(currentUrl.toString(), '_blank');
        }
        
        // Auto-save functionality (optional)
        let autoSaveTimer;
        
        function scheduleAutoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                // Could implement auto-save here
                console.log('Auto-save would trigger here');
            }, 5000);
        }
        
        // Add event listeners for auto-save
        document.addEventListener('DOMContentLoaded', function() {
            const gradeInputs = document.querySelectorAll('.grade-input, .completion-input');
            gradeInputs.forEach(input => {
                input.addEventListener('input', scheduleAutoSave);
            });
        });
        
        // Form validation
        document.getElementById('gradesForm')?.addEventListener('submit', function(e) {
            const finalInputs = document.querySelectorAll('.grade-input');
            const completionInputs = document.querySelectorAll('.completion-input');
            let hasInvalidGrades = false;
            
            finalInputs.forEach(input => {
                const value = input.value.toUpperCase().trim();
                
                // Check if it's INC or DRP or empty
                if (value === 'INC' || value === 'DRP' || value === '') {
                    input.style.borderColor = '#d1d5db';
                    return;
                }
                
                // Check if it's a valid numeric grade
                const numValue = parseFloat(value);
                if (isNaN(numValue) || numValue < 1.00 || numValue > 5.00) {
                    hasInvalidGrades = true;
                    input.style.borderColor = '#dc3545';
                } else {
                    input.style.borderColor = '#d1d5db';
                }
            });
            
            completionInputs.forEach(input => {
                if (input.disabled || !input.value.trim()) {
                    input.style.borderColor = '#ffc107';
                    return;
                }
                
                const value = parseFloat(input.value.trim());
                if (isNaN(value) || value < 1.00 || value > 5.00) {
                    hasInvalidGrades = true;
                    input.style.borderColor = '#dc3545';
                } else {
                    input.style.borderColor = '#ffc107';
                }
            });
            
            if (hasInvalidGrades) {
                e.preventDefault();
                alert('Please ensure all grades are between 1.00 and 5.00, or enter INC/DRP for final grades');
                return false;
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('gradesForm')?.submit();
            }
            
            // Ctrl+E to export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportGrades();
            }
        });
        
        // Auto-refresh every 5 minutes to check for updates
        setInterval(function() {
            // Only refresh if no inputs are focused
            if (document.activeElement.tagName !== 'INPUT') {
                const lastActivity = localStorage.getItem('lastGradeActivity') || Date.now();
                if (Date.now() - lastActivity > 300000) { // 5 minutes
                    window.location.reload();
                }
            }
        }, 300000);
        
        // Track user activity
        document.addEventListener('input', function() {
            localStorage.setItem('lastGradeActivity', Date.now());
        });
        
        document.addEventListener('click', function() {
            localStorage.setItem('lastGradeActivity', Date.now());
        });
    </script>
</body>
</html>