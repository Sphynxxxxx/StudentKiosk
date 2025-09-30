<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session first
session_start();

// Check if user is logged in and has appropriate role
//if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['administrator', 'faculty'])) {
//    header('Location: ../auth/login.php');
//    exit();
//}

// Include database configuration
require_once '../config/database.php';

$message = '';
$message_type = '';
$report_data = null;
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';

// Get filter parameters
$academic_year_id = isset($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : null;
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : null;
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $report_type) {
    // Will handle CSV export after generating report data
    $export_csv = true;
} else {
    $export_csv = false;
}

try {
    // Get academic years for dropdown
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
    
    // Get departments for dropdown
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get ALL programs for dropdown (we'll filter with JavaScript)
    $stmt = $pdo->prepare("SELECT id, program_name, program_code, department_id FROM programs WHERE status = 'active' ORDER BY program_name");
    $stmt->execute();
    $all_programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get programs for current department selection
    $programs = [];
    if ($department_id) {
        $stmt = $pdo->prepare("SELECT * FROM programs WHERE department_id = ? AND status = 'active' ORDER BY program_name");
        $stmt->execute([$department_id]);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // If no department selected, show all programs
        $programs = $all_programs;
    }
    
    // Get ALL sections for dropdown (we'll filter with JavaScript)
    $stmt = $pdo->prepare("
        SELECT s.id, s.section_name, s.year_level, s.program_id, s.academic_year_id
        FROM sections s
        WHERE s.status = 'active' 
        ORDER BY s.year_level, s.section_name
    ");
    $stmt->execute();
    $all_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sections for current filters
    $sections = [];
    if ($program_id && $academic_year_id) {
        $stmt = $pdo->prepare("SELECT * FROM sections WHERE program_id = ? AND academic_year_id = ? AND status = 'active' ORDER BY year_level, section_name");
        $stmt->execute([$program_id, $academic_year_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($academic_year_id) {
        // Show all sections for the academic year if no program selected
        $stmt = $pdo->prepare("SELECT * FROM sections WHERE academic_year_id = ? AND status = 'active' ORDER BY year_level, section_name");
        $stmt->execute([$academic_year_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Generate report based on type
    switch ($report_type) {
        case 'grade_distribution':
            $report_data = generateGradeDistributionReport($pdo, $academic_year_id, $program_id, $section_id);
            break;
            
        case 'enrollment_summary':
            $report_data = generateEnrollmentSummaryReport($pdo, $academic_year_id, $department_id, $program_id);
            break;
            
        case 'faculty_workload':
            $report_data = generateFacultyWorkloadReport($pdo, $academic_year_id, $department_id);
            break;
            
        case 'student_grades':
            $report_data = generateStudentGradesReport($pdo, $student_id, $academic_year_id);
            break;
            
        case 'section_performance':
            $report_data = generateSectionPerformanceReport($pdo, $section_id, $academic_year_id);
            break;
            
        case 'failing_students':
            $report_data = generateFailingStudentsReport($pdo, $academic_year_id, $program_id);
            break;
            
        case 'honors_list':
            $report_data = generateHonorsListReport($pdo, $academic_year_id, $program_id);
            break;
            
        case 'pre_enrollment_status':
            $report_data = generatePreEnrollmentStatusReport($pdo, $academic_year_id);
            break;
    }
    
    // Handle CSV export
    if ($export_csv && $report_data) {
        exportToCSV($report_data, $report_type);
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Report generation error: " . $e->getMessage());
    $message = 'Error generating report: ' . $e->getMessage();
    $message_type = 'error';
}

// Report Generation Functions
function generateGradeDistributionReport($pdo, $academic_year_id, $program_id = null, $section_id = null) {
    $query = "
        SELECT 
            g.letter_grade,
            g.remarks,
            COUNT(*) as count,
            s.course_code,
            s.subject_name,
            p.program_name,
            sec.section_name,
            sec.year_level
        FROM grades g
        INNER JOIN enrollments e ON g.enrollment_id = e.id
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN subjects s ON cs.subject_id = s.id
        INNER JOIN users u ON e.student_id = u.id
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        INNER JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections sec ON sp.section_id = sec.id
        WHERE cs.academic_year_id = ?
          AND e.status = 'enrolled'
          AND g.overall_grade IS NOT NULL
    ";
    
    $params = [$academic_year_id];
    
    if ($program_id) {
        $query .= " AND sp.program_id = ?";
        $params[] = $program_id;
    }
    
    if ($section_id) {
        $query .= " AND sp.section_id = ?";
        $params[] = $section_id;
    }
    
    $query .= " GROUP BY g.letter_grade, g.remarks, s.id, p.id, sec.id ORDER BY s.course_code, g.letter_grade";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return [
        'title' => 'Grade Distribution Report',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'type' => 'grade_distribution'
    ];
}

function generateEnrollmentSummaryReport($pdo, $academic_year_id, $department_id = null, $program_id = null) {
    $query = "
        SELECT 
            p.program_name,
            d.name as department_name,
            COUNT(DISTINCT CASE WHEN e.status = 'pending' THEN e.student_id END) as pending,
            COUNT(DISTINCT CASE WHEN e.status = 'enrolled' THEN e.student_id END) as enrolled,
            COUNT(DISTINCT CASE WHEN e.status = 'rejected' THEN e.student_id END) as rejected,
            COUNT(DISTINCT e.student_id) as total
        FROM enrollments e
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN users u ON e.student_id = u.id
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        INNER JOIN programs p ON sp.program_id = p.id
        INNER JOIN departments d ON p.department_id = d.id
        WHERE cs.academic_year_id = ?
    ";
    
    $params = [$academic_year_id];
    
    if ($department_id) {
        $query .= " AND d.id = ?";
        $params[] = $department_id;
    }
    
    if ($program_id) {
        $query .= " AND p.id = ?";
        $params[] = $program_id;
    }
    
    $query .= " GROUP BY p.id, d.id ORDER BY d.name, p.program_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return [
        'title' => 'Enrollment Summary Report',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'type' => 'enrollment_summary'
    ];
}

function generateFacultyWorkloadReport($pdo, $academic_year_id, $department_id = null) {
    $query = "
        SELECT 
            u.first_name,
            u.last_name,
            u.employee_id,
            d.name as department_name,
            COUNT(DISTINCT cs.id) as total_sections,
            COUNT(DISTINCT e.student_id) as total_students,
            GROUP_CONCAT(DISTINCT CONCAT(s.course_code, ' (', cs.section_name, ')') SEPARATOR ', ') as subjects
        FROM users u
        INNER JOIN class_sections cs ON u.id = cs.faculty_id
        INNER JOIN subjects s ON cs.subject_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
        WHERE u.role = 'faculty'
          AND cs.academic_year_id = ?
          AND cs.status = 'active'
    ";
    
    $params = [$academic_year_id];
    
    if ($department_id) {
        $query .= " AND d.id = ?";
        $params[] = $department_id;
    }
    
    $query .= " GROUP BY u.id, d.id ORDER BY d.name, u.last_name, u.first_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return [
        'title' => 'Faculty Workload Report',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'type' => 'faculty_workload'
    ];
}

function generateStudentGradesReport($pdo, $student_id, $academic_year_id) {
    if (!$student_id) {
        return ['title' => 'Student Grades Report', 'data' => [], 'type' => 'student_grades', 'error' => 'Please select a student'];
    }
    
    // Get student info
    $stmt = $pdo->prepare("
        SELECT u.*, sp.year_level, p.program_code, p.program_name, s.section_name
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get grades
    $stmt = $pdo->prepare("
        SELECT 
            s.course_code,
            s.subject_name,
            s.credits,
            cs.section_name,
            g.midterm_grade,
            g.final_grade,
            g.overall_grade,
            g.letter_grade,
            g.remarks,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name
        FROM enrollments e
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN subjects s ON cs.subject_id = s.id
        INNER JOIN users f ON cs.faculty_id = f.id
        LEFT JOIN grades g ON e.id = g.enrollment_id
        WHERE e.student_id = ? 
          AND cs.academic_year_id = ?
          AND e.status = 'enrolled'
        ORDER BY s.course_code
    ");
    $stmt->execute([$student_id, $academic_year_id]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate GPA
    $total_grade_points = 0;
    $total_credits = 0;
    foreach ($grades as $grade) {
        if ($grade['overall_grade'] !== null && $grade['credits']) {
            $total_grade_points += ($grade['overall_grade'] * $grade['credits']);
            $total_credits += $grade['credits'];
        }
    }
    $gpa = $total_credits > 0 ? $total_grade_points / $total_credits : 0;
    
    return [
        'title' => 'Student Grades Report',
        'data' => $grades,
        'student_info' => $student_info,
        'gpa' => $gpa,
        'total_credits' => $total_credits,
        'type' => 'student_grades'
    ];
}

function generateSectionPerformanceReport($pdo, $section_id, $academic_year_id) {
    if (!$section_id) {
        return ['title' => 'Section Performance Report', 'data' => [], 'type' => 'section_performance', 'error' => 'Please select a section'];
    }
    
    // Get section info
    $stmt = $pdo->prepare("
        SELECT s.*, p.program_name, CONCAT(u.first_name, ' ', u.last_name) as adviser_name
        FROM sections s
        INNER JOIN programs p ON s.program_id = p.id
        LEFT JOIN users u ON s.adviser_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$section_id]);
    $section_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get student performance
    $stmt = $pdo->prepare("
        SELECT 
            u.student_id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            AVG(g.overall_grade) as gpa,
            COUNT(DISTINCT e.id) as total_subjects,
            COUNT(DISTINCT CASE WHEN g.overall_grade <= 3.00 THEN e.id END) as passed_subjects,
            COUNT(DISTINCT CASE WHEN g.overall_grade > 3.00 THEN e.id END) as failed_subjects
        FROM users u
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        INNER JOIN enrollments e ON u.id = e.student_id
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        LEFT JOIN grades g ON e.id = g.enrollment_id
        WHERE sp.section_id = ?
          AND cs.academic_year_id = ?
          AND e.status = 'enrolled'
          AND u.role = 'student'
        GROUP BY u.id
        ORDER BY gpa ASC, u.last_name, u.first_name
    ");
    $stmt->execute([$section_id, $academic_year_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'title' => 'Section Performance Report',
        'data' => $students,
        'section_info' => $section_info,
        'type' => 'section_performance'
    ];
}

function generateFailingStudentsReport($pdo, $academic_year_id, $program_id = null) {
    $query = "
        SELECT 
            u.student_id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            u.email,
            p.program_name,
            s.section_name,
            sp.year_level,
            subj.course_code,
            subj.subject_name,
            g.overall_grade,
            g.remarks
        FROM users u
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        INNER JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        INNER JOIN enrollments e ON u.id = e.student_id
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN subjects subj ON cs.subject_id = subj.id
        INNER JOIN grades g ON e.id = g.enrollment_id
        WHERE g.overall_grade > 3.00
          AND cs.academic_year_id = ?
          AND e.status = 'enrolled'
    ";
    
    $params = [$academic_year_id];
    
    if ($program_id) {
        $query .= " AND sp.program_id = ?";
        $params[] = $program_id;
    }
    
    $query .= " ORDER BY p.program_name, u.last_name, u.first_name, subj.course_code";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return [
        'title' => 'Failing Students Report',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'type' => 'failing_students'
    ];
}

function generateHonorsListReport($pdo, $academic_year_id, $program_id = null) {
    $query = "
        SELECT 
            u.student_id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            p.program_name,
            s.section_name,
            sp.year_level,
            AVG(g.overall_grade) as gpa,
            COUNT(e.id) as total_subjects,
            CASE 
                WHEN AVG(g.overall_grade) <= 1.25 THEN 'President\\'s List'
                WHEN AVG(g.overall_grade) <= 1.75 THEN 'Dean\\'s List'
                ELSE 'Honor Roll'
            END as honor_level
        FROM users u
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        INNER JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        INNER JOIN enrollments e ON u.id = e.student_id
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN grades g ON e.id = g.enrollment_id
        WHERE cs.academic_year_id = ?
          AND e.status = 'enrolled'
          AND g.overall_grade IS NOT NULL
    ";
    
    $params = [$academic_year_id];
    
    if ($program_id) {
        $query .= " AND sp.program_id = ?";
        $params[] = $program_id;
    }
    
    $query .= " GROUP BY u.id HAVING AVG(g.overall_grade) <= 2.00 ORDER BY gpa ASC, u.last_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return [
        'title' => 'Honors List Report',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'type' => 'honors_list'
    ];
}

function generatePreEnrollmentStatusReport($pdo, $academic_year_id) {
    $stmt = $pdo->prepare("
        SELECT 
            p.program_name,
            d.name as department_name,
            COUNT(DISTINCT CASE WHEN e.status = 'pending' THEN e.student_id END) as pending_students,
            COUNT(CASE WHEN e.status = 'pending' THEN 1 END) as pending_enrollments,
            COUNT(DISTINCT CASE WHEN e.status = 'enrolled' THEN e.student_id END) as approved_students,
            COUNT(CASE WHEN e.status = 'enrolled' THEN 1 END) as approved_enrollments,
            COUNT(DISTINCT CASE WHEN e.status = 'rejected' THEN e.student_id END) as rejected_students,
            COUNT(CASE WHEN e.status = 'rejected' THEN 1 END) as rejected_enrollments
        FROM enrollments e
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN users u ON e.student_id = u.id
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        INNER JOIN programs p ON sp.program_id = p.id
        INNER JOIN departments d ON p.department_id = d.id
        WHERE cs.academic_year_id = ?
        GROUP BY p.id, d.id
        ORDER BY d.name, p.program_name
    ");
    $stmt->execute([$academic_year_id]);
    
    return [
        'title' => 'Pre-Enrollment Status Report',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'type' => 'pre_enrollment_status'
    ];
}

function exportToCSV($report_data, $report_type) {
    $filename = $report_type . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write title
    fputcsv($output, [$report_data['title']]);
    fputcsv($output, []); // Empty line
    
    // Write data based on report type
    if (!empty($report_data['data'])) {
        // Write headers
        fputcsv($output, array_keys($report_data['data'][0]));
        
        // Write data rows
        foreach ($report_data['data'] as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin_dashboard.css" rel="stylesheet">
    <style>
        .report-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        
        .report-card.active {
            border-color: #007bff;
            background: #f0f7ff;
        }
        
        .report-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: #007bff;
        }
        
        .report-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .report-description {
            font-size: 0.9em;
            color: #6c757d;
            line-height: 1.5;
        }
        
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #374151;
            font-size: 0.9em;
        }
        
        .filter-select {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95em;
        }
        
        .report-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .report-table th,
        .report-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .report-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            font-size: 0.85em;
        }
        
        .report-table tr:hover {
            background: #f8f9fa;
        }
        
        .report-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .student-info-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .grade-excellent { color: black; }
        .grade-very-good { color: black; }
        .grade-good { color: black; }
        .grade-satisfactory { color: black; }
        .grade-passing { color: black; }
        .grade-failing { color: black; }
        
        .gpa-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .gpa-value {
            font-size: 3em;
            font-weight: 700;
        }
        
        .gpa-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        @media print {
            .sidebar, .header, .filters-section, .report-actions, .report-selector {
                display: none !important;
            }
            
            .report-table {
                page-break-inside: avoid;
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
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-file-alt"></i> Generate Reports
                        </h3>
                    </div>

                    <div class="card-content">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Report Type Selector -->
                        <h4 style="margin-bottom: 20px;">Select Report Type:</h4>
                        <div class="report-selector">
                            <div class="report-card <?php echo $report_type === 'grade_distribution' ? 'active' : ''; ?>" 
                                 onclick="selectReport('grade_distribution')">
                                <div class="report-icon"><i class="fas fa-chart-pie"></i></div>
                                <div class="report-title">Grade Distribution</div>
                                <div class="report-description">View grade distribution across programs and sections</div>
                            </div>

                            <div class="report-card <?php echo $report_type === 'enrollment_summary' ? 'active' : ''; ?>" 
                                 onclick="selectReport('enrollment_summary')">
                                <div class="report-icon"><i class="fas fa-users"></i></div>
                                <div class="report-title">Enrollment Summary</div>
                                <div class="report-description">Overview of enrollment statistics by program</div>
                            </div>

                            <div class="report-card <?php echo $report_type === 'faculty_workload' ? 'active' : ''; ?>" 
                                 onclick="selectReport('faculty_workload')">
                                <div class="report-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                <div class="report-title">Faculty Workload</div>
                                <div class="report-description">Teaching load and student count per faculty member</div>
                            </div>

                            <div class="report-card <?php echo $report_type === 'student_grades' ? 'active' : ''; ?>" 
                                 onclick="selectReport('student_grades')">
                                <div class="report-icon"><i class="fas fa-user-graduate"></i></div>
                                <div class="report-title">Student Grades</div>
                                <div class="report-description">Individual student grade report and GPA</div>
                            </div>

                            <div class="report-card <?php echo $report_type === 'section_performance' ? 'active' : ''; ?>" 
                                 onclick="selectReport('section_performance')">
                                <div class="report-icon"><i class="fas fa-chart-line"></i></div>
                                <div class="report-title">Section Performance</div>
                                <div class="report-description">Performance analysis of students in a section</div>
                            </div>

                            <div class="report-card <?php echo $report_type === 'failing_students' ? 'active' : ''; ?>" 
                                 onclick="selectReport('failing_students')">
                                <div class="report-icon"><i class="fas fa-exclamation-triangle"></i></div>
                                <div class="report-title">Failing Students</div>
                                <div class="report-description">List of students with failing grades</div>
                            </div>

                            <div class="report-card <?php echo $report_type === 'honors_list' ? 'active' : ''; ?>" 
                                 onclick="selectReport('honors_list')">
                                <div class="report-icon"><i class="fas fa-trophy"></i></div>
                                <div class="report-title">Honors List</div>
                                <div class="report-description">Dean's List and Honor Roll students</div>
                            </div>

                            <div class="report-card <?php echo $report_type === 'pre_enrollment_status' ? 'active' : ''; ?>" 
                                 onclick="selectReport('pre_enrollment_status')">
                                <div class="report-icon"><i class="fas fa-clipboard-list"></i></div>
                                <div class="report-title">Pre-Enrollment Status</div>
                                <div class="report-description">Status of pre-enrollment requests</div>
                            </div>
                        </div>

                        <!-- Filters Section -->
                        <?php if ($report_type): ?>
                        <div class="filters-section">
                            <h4 style="margin-bottom: 15px;">
                                <i class="fas fa-filter"></i> Report Filters
                            </h4>
                            <form method="GET" id="filterForm">
                                <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
                                
                                <div class="filters-grid">
                                    <!-- Academic Year Filter -->
                                    <div class="filter-group">
                                        <label for="academic_year_id">Academic Year</label>
                                        <select name="academic_year_id" id="academic_year_id" class="filter-select">
                                            <?php foreach ($academic_years as $ay): ?>
                                            <option value="<?php echo $ay['id']; ?>" <?php echo ($academic_year_id == $ay['id']) ? 'selected' : ''; ?>>
                                                <?php echo $ay['year_start'] . '-' . $ay['year_end'] . ' (' . ucfirst($ay['semester']) . ')'; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Department Filter -->
                                    <?php if (in_array($report_type, ['enrollment_summary', 'faculty_workload'])): ?>
                                    <div class="filter-group">
                                        <label for="department_id">Department (Optional)</label>
                                        <select name="department_id" id="department_id" class="filter-select">
                                            <option value="">All Departments</option>
                                            <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>" <?php echo ($department_id == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Program Filter -->
                                    <?php if (in_array($report_type, ['grade_distribution', 'enrollment_summary', 'failing_students', 'honors_list'])): ?>
                                    <div class="filter-group">
                                        <label for="program_id">Program (Optional)</label>
                                        <select name="program_id" id="program_id" class="filter-select">
                                            <option value="">All Programs</option>
                                            <?php foreach ($programs as $prog): ?>
                                            <option value="<?php echo $prog['id']; ?>" <?php echo ($program_id == $prog['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($prog['program_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Section Filter -->
                                    <?php if (in_array($report_type, ['grade_distribution', 'section_performance'])): ?>
                                    <div class="filter-group">
                                        <label for="section_id">Section <?php echo $report_type === 'section_performance' ? '(Required)' : '(Optional)'; ?></label>
                                        <select name="section_id" id="section_id" class="filter-select">
                                            <option value="">Select Section</option>
                                            <?php foreach ($sections as $sect): ?>
                                            <option value="<?php echo $sect['id']; ?>" <?php echo ($section_id == $sect['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sect['year_level'] . ' Year - Section ' . $sect['section_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Student Filter for Student Grades Report -->
                                    <?php if ($report_type === 'student_grades'): ?>
                                    <div class="filter-group">
                                        <label for="student_id">Student ID (Required)</label>
                                        <input type="number" name="student_id" id="student_id" class="filter-select" 
                                               value="<?php echo $student_id ?? ''; ?>" 
                                               placeholder="Enter student ID">
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="report-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sync-alt"></i> Generate Report
                                    </button>
                                    <?php if ($report_data && !empty($report_data['data'])): ?>
                                    <button type="button" class="btn btn-success" onclick="exportReport()">
                                        <i class="fas fa-download"></i> Export CSV
                                    </button>
                                    <button type="button" class="btn btn-outline" onclick="window.print()">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>

                        <!-- Report Display -->
                        <?php if ($report_data): ?>
                            <div class="report-header">
                                <h3><?php echo htmlspecialchars($report_data['title']); ?></h3>
                                <p style="color: #6c757d; margin-top: 5px;">
                                    Generated on: <?php echo date('F d, Y g:i A'); ?>
                                </p>
                            </div>

                            <?php if (isset($report_data['error'])): ?>
                                <div class="alert alert-error">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo htmlspecialchars($report_data['error']); ?>
                                </div>
                            <?php elseif (empty($report_data['data'])): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h3>No Data Found</h3>
                                    <p>No data available for the selected filters.</p>
                                </div>
                            <?php else: ?>
                                <?php
                                // Display report based on type
                                switch ($report_data['type']) {
                                    case 'student_grades':
                                        displayStudentGradesReport($report_data);
                                        break;
                                    case 'section_performance':
                                        displaySectionPerformanceReport($report_data);
                                        break;
                                    default:
                                        displayGenericReport($report_data);
                                        break;
                                }
                                ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Store all programs and sections data for dynamic filtering
        const allPrograms = <?php echo json_encode($all_programs); ?>;
        const allSections = <?php echo json_encode($all_sections); ?>;
        
        function selectReport(reportType) {
            window.location.href = '?report_type=' + reportType;
        }

        function exportReport() {
            const form = document.getElementById('filterForm');
            const currentAction = form.action;
            const exportInput = document.createElement('input');
            exportInput.type = 'hidden';
            exportInput.name = 'export';
            exportInput.value = 'csv';
            form.appendChild(exportInput);
            form.submit();
            form.removeChild(exportInput);
        }
        
        // Dynamic filtering functions
        function updateProgramDropdown() {
            const departmentId = document.getElementById('department_id')?.value;
            const programSelect = document.getElementById('program_id');
            
            if (!programSelect) return;
            
            // Clear current options except the first one
            programSelect.innerHTML = '<option value="">All Programs</option>';
            
            // Filter and add programs based on selected department
            const filteredPrograms = departmentId 
                ? allPrograms.filter(p => p.department_id == departmentId)
                : allPrograms;
            
            filteredPrograms.forEach(program => {
                const option = document.createElement('option');
                option.value = program.id;
                option.textContent = program.program_name;
                option.selected = program.id == <?php echo $program_id ?? 0; ?>;
                programSelect.appendChild(option);
            });
        }
        
        function updateSectionDropdown() {
            const programId = document.getElementById('program_id')?.value;
            const academicYearId = document.getElementById('academic_year_id')?.value;
            const sectionSelect = document.getElementById('section_id');
            
            if (!sectionSelect) return;
            
            // Clear current options except the first one
            sectionSelect.innerHTML = '<option value="">Select Section</option>';
            
            // Filter sections based on program and academic year
            const filteredSections = allSections.filter(section => {
                const matchProgram = !programId || section.program_id == programId;
                const matchAY = !academicYearId || section.academic_year_id == academicYearId;
                return matchProgram && matchAY;
            });
            
            filteredSections.forEach(section => {
                const option = document.createElement('option');
                option.value = section.id;
                option.textContent = section.year_level + ' Year - Section ' + section.section_name;
                option.selected = section.id == <?php echo $section_id ?? 0; ?>;
                sectionSelect.appendChild(option);
            });
        }
        
        // Event listeners for cascade filtering
        document.addEventListener('DOMContentLoaded', function() {
            // Department change triggers program update
            const departmentSelect = document.getElementById('department_id');
            if (departmentSelect) {
                departmentSelect.addEventListener('change', function() {
                    updateProgramDropdown();
                    // Reset program and section selections
                    const programSelect = document.getElementById('program_id');
                    if (programSelect) programSelect.value = '';
                    updateSectionDropdown();
                });
            }
            
            // Program change triggers section update
            const programSelect = document.getElementById('program_id');
            if (programSelect) {
                programSelect.addEventListener('change', function() {
                    updateSectionDropdown();
                });
            }
            
            // Academic year change triggers section update
            const academicYearSelect = document.getElementById('academic_year_id');
            if (academicYearSelect) {
                academicYearSelect.addEventListener('change', function() {
                    updateSectionDropdown();
                });
            }
            
            // Initialize dropdowns with current values
            updateProgramDropdown();
            updateSectionDropdown();
        });
    </script>
</body>
</html>

<?php
// Display Functions
function displayGenericReport($report_data) {
    if (empty($report_data['data'])) return;
    
    echo '<div style="overflow-x: auto;">';
    echo '<table class="report-table">';
    echo '<thead><tr>';
    
    // Headers
    foreach (array_keys($report_data['data'][0]) as $header) {
        echo '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . '</th>';
    }
    
    echo '</tr></thead><tbody>';
    
    // Data rows
    foreach ($report_data['data'] as $row) {
        echo '<tr>';
        foreach ($row as $key => $value) {
            // Format based on column type
            if ($key === 'gpa' || strpos($key, 'grade') !== false) {
                if (is_numeric($value)) {
                    $value = number_format($value, 2);
                }
            }
            echo '<td>' . htmlspecialchars($value ?? '-') . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</tbody></table></div>';
}

function displayStudentGradesReport($report_data) {
    $student = $report_data['student_info'];
    $grades = $report_data['data'];
    $gpa = $report_data['gpa'];
    $total_credits = $report_data['total_credits'];
    
    // Student Information
    echo '<div class="student-info-box">';
    echo '<h4 style="margin-bottom: 15px;">Student Information</h4>';
    echo '<div class="info-grid">';
    echo '<div class="info-item"><span class="info-label">Student ID</span><span class="info-value">' . htmlspecialchars($student['student_id']) . '</span></div>';
    echo '<div class="info-item"><span class="info-label">Name</span><span class="info-value">' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . '</span></div>';
    echo '<div class="info-item"><span class="info-label">Program</span><span class="info-value">' . htmlspecialchars($student['program_name']) . '</span></div>';
    echo '<div class="info-item"><span class="info-label">Year & Section</span><span class="info-value">' . htmlspecialchars($student['year_level'] . ' - ' . $student['section_name']) . '</span></div>';
    echo '</div>';
    echo '</div>';
    
    // GPA Box
    echo '<div class="gpa-box" style="margin-bottom: 20px;">';
    echo '<div class="gpa-value">' . number_format($gpa, 2) . '</div>';
    echo '<div class="gpa-label">General Weighted Average (GWA)</div>';
    echo '<div style="margin-top: 10px; font-size: 0.9em;">Total Credits: ' . $total_credits . '</div>';
    echo '</div>';
    
    // Grades Table
    echo '<div style="overflow-x: auto;">';
    echo '<table class="report-table">';
    echo '<thead><tr>';
    echo '<th>Course Code</th>';
    echo '<th>Subject Name</th>';
    echo '<th>Section</th>';
    echo '<th>Credits</th>';
    echo '<th>Midterm</th>';
    echo '<th>Final</th>';
    echo '<th>Overall</th>';
    echo '<th>Grade</th>';
    echo '<th>Remarks</th>';
    echo '<th>Faculty</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($grades as $grade) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($grade['course_code']) . '</td>';
        echo '<td>' . htmlspecialchars($grade['subject_name']) . '</td>';
        echo '<td>' . htmlspecialchars($grade['section_name']) . '</td>';
        echo '<td>' . htmlspecialchars($grade['credits']) . '</td>';
        echo '<td>' . ($grade['midterm_grade'] ? number_format($grade['midterm_grade'], 2) : '-') . '</td>';
        echo '<td>' . ($grade['final_grade'] ? number_format($grade['final_grade'], 2) : '-') . '</td>';
        echo '<td>' . ($grade['overall_grade'] ? number_format($grade['overall_grade'], 2) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($grade['letter_grade'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($grade['remarks'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($grade['faculty_name']) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table></div>';
}

function displaySectionPerformanceReport($report_data) {
    $section = $report_data['section_info'];
    $students = $report_data['data'];
    
    // Section Information
    echo '<div class="student-info-box">';
    echo '<h4 style="margin-bottom: 15px;">Section Information</h4>';
    echo '<div class="info-grid">';
    echo '<div class="info-item"><span class="info-label">Program</span><span class="info-value">' . htmlspecialchars($section['program_name']) . '</span></div>';
    echo '<div class="info-item"><span class="info-label">Year Level</span><span class="info-value">' . htmlspecialchars($section['year_level']) . '</span></div>';
    echo '<div class="info-item"><span class="info-label">Section</span><span class="info-value">' . htmlspecialchars($section['section_name']) . '</span></div>';
    if ($section['adviser_name']) {
        echo '<div class="info-item"><span class="info-label">Adviser</span><span class="info-value">' . htmlspecialchars($section['adviser_name']) . '</span></div>';
    }
    echo '</div>';
    echo '</div>';
    
    // Performance Table
    displayGenericReport($report_data);
}
?>