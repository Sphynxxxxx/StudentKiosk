<?php
session_start();

// Check if faculty is logged in
if (!isset($_SESSION['faculty_id'])) {
    header('Location: ../faculty.php');
    exit();
}

// Include database configuration
require_once '../config/database.php';

// Get parameters
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;

if (!$student_id || !$section_id) {
    $_SESSION['error_message'] = 'Invalid parameters provided.';
    header('Location: faculty_dashboard.php');
    exit();
}

// Additional security check: Verify that the section_id belongs to the faculty
$section_auth_check = $pdo->prepare("
    SELECT cs.id, s.course_code, s.subject_name
    FROM class_sections cs
    INNER JOIN subjects s ON cs.subject_id = s.id
    INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
    WHERE cs.id = ? 
      AND cs.faculty_id = ? 
      AND cs.status = 'active'
      AND ay.is_active = 1
");
$section_auth_check->execute([$section_id, $_SESSION['faculty_id']]);
$section_auth = $section_auth_check->fetch(PDO::FETCH_ASSOC);

if (!$section_auth) {
    error_log("SECURITY ALERT: Faculty ID {$_SESSION['faculty_id']} attempted to access unauthorized section ID $section_id");
    $_SESSION['error_message'] = 'Access denied: You are not authorized to access this class section.';
    header('Location: faculty_dashboard.php');
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'update_grades') {
            $pdo->beginTransaction();
            
            $updated_count = 0;
            foreach ($_POST['grades'] as $enrollment_id => $grade_data) {
                // CRITICAL SECURITY CHECK: Verify that this enrollment belongs to the current faculty
                $security_check = $pdo->prepare("
                    SELECT e.id, e.student_id, cs.faculty_id, s.course_code
                    FROM enrollments e
                    INNER JOIN class_sections cs ON e.class_section_id = cs.id
                    INNER JOIN subjects s ON cs.subject_id = s.id
                    INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
                    WHERE e.id = ? 
                      AND cs.faculty_id = ? 
                      AND e.status = 'enrolled'
                      AND ay.is_active = 1
                      AND cs.status = 'active'
                      AND s.status = 'active'
                ");
                $security_check->execute([$enrollment_id, $_SESSION['faculty_id']]);
                $authorized_enrollment = $security_check->fetch(PDO::FETCH_ASSOC);
                
                // If faculty is not authorized for this enrollment, skip it and log the attempt
                if (!$authorized_enrollment) {
                    error_log("SECURITY ALERT: Faculty ID {$_SESSION['faculty_id']} attempted to grade unauthorized enrollment ID $enrollment_id");
                    continue; // Skip this enrollment
                }
                
                // Additional check: Verify the student matches the URL parameter
                if ($authorized_enrollment['student_id'] != $student_id) {
                    error_log("SECURITY ALERT: Student ID mismatch in grade submission. Expected: $student_id, Got: {$authorized_enrollment['student_id']}");
                    continue; // Skip this enrollment
                }
                
                $midterm = !empty($grade_data['midterm']) ? (float)$grade_data['midterm'] : null;
                $final = !empty($grade_data['final']) ? (float)$grade_data['final'] : null;
                
                // Validate grades
                if (($midterm !== null && ($midterm < 1.00 || $midterm > 5.00)) ||
                    ($final !== null && ($final < 1.00 || $final > 5.00))) {
                    throw new Exception('Grades must be between 1.00 and 5.00');
                }
                
                // Calculate overall grade and determine letter grade/remarks
                $overall = null;
                $letter_grade = null;
                $remarks = null;
                
                if ($midterm !== null && $final !== null) {
                    $overall = ($midterm + $final) / 2;
                    
                    // Determine letter grade and remarks
                    if ($overall >= 1.00 && $overall <= 1.25) {
                        $letter_grade = 'A';
                        $remarks = 'Excellent';
                    } elseif ($overall >= 1.26 && $overall <= 1.75) {
                        $letter_grade = 'B';
                        $remarks = 'Very Good';
                    } elseif ($overall >= 1.76 && $overall <= 2.25) {
                        $letter_grade = 'C';
                        $remarks = 'Good';
                    } elseif ($overall >= 2.26 && $overall <= 2.75) {
                        $letter_grade = 'D';
                        $remarks = 'Satisfactory';
                    } elseif ($overall >= 2.76 && $overall <= 3.00) {
                        $letter_grade = 'E';
                        $remarks = 'Passed';
                    } else {
                        $letter_grade = 'F';
                        $remarks = 'Failed';
                    }
                }
                
                // Check if grade record exists for this specific enrollment
                $stmt = $pdo->prepare("SELECT id FROM grades WHERE enrollment_id = ?");
                $stmt->execute([$enrollment_id]);
                $existing_grade = $stmt->fetch();
                
                if ($existing_grade) {
                    // Update existing grade - with additional security check
                    $stmt = $pdo->prepare("
                        UPDATE grades g
                        INNER JOIN enrollments e ON g.enrollment_id = e.id
                        INNER JOIN class_sections cs ON e.class_section_id = cs.id
                        SET g.midterm_grade = ?, g.final_grade = ?, g.overall_grade = ?, 
                            g.letter_grade = ?, g.remarks = ?, g.graded_by = ?, g.updated_at = NOW()
                        WHERE g.enrollment_id = ? 
                          AND cs.faculty_id = ?
                          AND e.student_id = ?
                    ");
                    $stmt->execute([$midterm, $final, $overall, $letter_grade, $remarks, $_SESSION['faculty_id'], $enrollment_id, $_SESSION['faculty_id'], $student_id]);
                } else {
                    // Create new grade record - with security validation
                    $stmt = $pdo->prepare("
                        INSERT INTO grades (enrollment_id, midterm_grade, final_grade, overall_grade, 
                                          letter_grade, remarks, graded_by, graded_at)
                        SELECT ?, ?, ?, ?, ?, ?, ?, NOW()
                        FROM enrollments e
                        INNER JOIN class_sections cs ON e.class_section_id = cs.id
                        WHERE e.id = ? 
                          AND cs.faculty_id = ?
                          AND e.student_id = ?
                          AND e.status = 'enrolled'
                    ");
                    $stmt->execute([$enrollment_id, $midterm, $final, $overall, $letter_grade, $remarks, $_SESSION['faculty_id'], $enrollment_id, $_SESSION['faculty_id'], $student_id]);
                }
                
                // Log the grade update for audit trail
                $audit_stmt = $pdo->prepare("
                    INSERT INTO user_logs (user_id, activity_type, description, ip_address, created_at)
                    VALUES (?, 'grade_update', ?, ?, NOW())
                ");
                $audit_description = "Updated grades for student ID $student_id, subject {$authorized_enrollment['course_code']}, enrollment ID $enrollment_id";
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $audit_stmt->execute([$_SESSION['faculty_id'], $audit_description, $ip_address]);
                
                $updated_count++;
            }
            
            $pdo->commit();
            
            if ($updated_count > 0) {
                $message = "Successfully updated grades for $updated_count subject(s)!";
                $message_type = 'success';
            } else {
                $message = "No grades were updated. Please check your permissions.";
                $message_type = 'warning';
            }
            
        } elseif (isset($_POST['action']) && $_POST['action'] === 'submit_final_grades') {
            // Additional security check for final grade submission
            $final_grades_check = $pdo->prepare("
                SELECT COUNT(*) as authorized_count
                FROM enrollments e
                INNER JOIN class_sections cs ON e.class_section_id = cs.id
                INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
                WHERE e.student_id = ? 
                  AND cs.faculty_id = ? 
                  AND e.status = 'enrolled'
                  AND ay.is_active = 1
            ");
            $final_grades_check->execute([$student_id, $_SESSION['faculty_id']]);
            $final_auth = $final_grades_check->fetch(PDO::FETCH_ASSOC);
            
            if ($final_auth['authorized_count'] > 0) {
                // Mark grades as finalized (you can add a finalized column to grades table)
                $message = 'Final grades submitted successfully!';
                $message_type = 'success';
                
                // Log the final submission
                $audit_stmt = $pdo->prepare("
                    INSERT INTO user_logs (user_id, activity_type, description, ip_address, created_at)
                    VALUES (?, 'final_grades_submit', ?, ?, NOW())
                ");
                $audit_description = "Submitted final grades for student ID $student_id";
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $audit_stmt->execute([$_SESSION['faculty_id'], $audit_description, $ip_address]);
            } else {
                $message = 'Access denied: You are not authorized to submit final grades for this student.';
                $message_type = 'error';
            }
        }
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $message = 'Error updating grades: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Grade update error: " . $e->getMessage());
        
        // Log security-related errors
        if (strpos($e->getMessage(), 'authorized') !== false) {
            $audit_stmt = $pdo->prepare("
                INSERT INTO user_logs (user_id, activity_type, description, ip_address, created_at)
                VALUES (?, 'security_violation', ?, ?, NOW())
            ");
            $audit_description = "Attempted unauthorized grade access: " . $e->getMessage();
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $audit_stmt->execute([$_SESSION['faculty_id'], $audit_description, $ip_address]);
        }
    }
}

try {
    // First, verify that the faculty has permission to grade this student
    // Check if the faculty is assigned to any subjects that this student is enrolled in
    $authorization_stmt = $pdo->prepare("
        SELECT COUNT(*) as authorized_subjects
        FROM enrollments e
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
        WHERE e.student_id = ? 
          AND cs.faculty_id = ? 
          AND e.status = 'enrolled'
          AND ay.is_active = 1
    ");
    $authorization_stmt->execute([$student_id, $_SESSION['faculty_id']]);
    $auth_result = $authorization_stmt->fetch(PDO::FETCH_ASSOC);
    
    // If faculty has no subjects with this student, deny access
    if ($auth_result['authorized_subjects'] == 0) {
        $_SESSION['error_message'] = 'Access denied: You are not authorized to grade this student.';
        header('Location: faculty_dashboard.php');
        exit();
    }
    
    // Get student information (only if authorized)
    $stmt = $pdo->prepare("
        SELECT u.*, sp.year_level, p.program_code, p.program_name, s.section_name
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $_SESSION['error_message'] = 'Student not found.';
        header('Location: faculty_dashboard.php');
        exit();
    }
    
    // Get ONLY faculty's subjects that this student is enrolled in (double security check)
    $stmt = $pdo->prepare("
        SELECT 
            e.id as enrollment_id,
            s.id as subject_id,
            s.course_code,
            s.subject_name,
            s.credits,
            cs.id as class_section_id,
            cs.section_name as class_section_name,
            cs.schedule,
            cs.room,
            ay.year_start,
            ay.year_end,
            ay.semester,
            g.id as grade_id,
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
        INNER JOIN subjects s ON cs.subject_id = s.id
        INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
        INNER JOIN users fac ON cs.faculty_id = fac.id
        LEFT JOIN grades g ON e.id = g.enrollment_id
        WHERE e.student_id = ? 
          AND cs.faculty_id = ? 
          AND e.status = 'enrolled'
          AND ay.is_active = 1
          AND cs.status = 'active'
          AND s.status = 'active'
        ORDER BY s.course_code
    ");
    $stmt->execute([$student_id, $_SESSION['faculty_id']]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get academic year info
    $stmt = $pdo->prepare("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $academic_year = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Input grades error: " . $e->getMessage());
    $student = null;
    $enrollments = [];
    $academic_year = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Grades - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/faculty.css" rel="stylesheet">
    <style>
        .grades-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .student-header {
            background: #1e3a8a;
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .student-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .info-label {
            font-size: 0.9em;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.1em;
            font-weight: 600;
        }
        
        .grades-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .form-header {
            background: #f8f9fa;
            padding: 25px 30px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-title {
            margin: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .grades-table th,
        .grades-table td {
            padding: 20px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .grades-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }
        
        .grades-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .subject-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .course-code {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        .subject-name {
            color: #6c757d;
            font-size: 0.95em;
        }
        
        .subject-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .grade-input {
            width: 100px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .grade-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            transform: scale(1.02);
        }
        
        .grade-input:invalid {
            border-color: #dc3545;
        }
        
        .calculated-grade {
            text-align: center;
            font-weight: 700;
            font-size: 1.1em;
            color: #28a745;
        }
        
        .letter-grade {
            text-align: center;
            font-weight: 700;
            font-size: 1.2em;
            padding: 8px 12px;
            border-radius: 6px;
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .remarks {
            text-align: center;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        
        .remarks-excellent { background: #d4edda; color: #155724; }
        .remarks-very-good { background: #d1ecf1; color: #0c5460; }
        .remarks-good { background: #cce5ff; color: #004085; }
        .remarks-satisfactory { background: #fff3cd; color: #856404; }
        .remarks-passed { background: #ffeaa7; color: #8c7700; }
        .remarks-failed { background: #f8d7da; color: #721c24; }
        
        .form-actions {
            padding: 30px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.95em;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .btn-outline {
            background: white;
            color: #6c757d;
            border: 2px solid #e9ecef;
        }
        
        .btn-outline:hover {
            background: #f8f9fa;
            border-color: #6c757d;
        }
        
        .alert {
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: rgba(255,255,255,0.3);
            transform: translateX(-5px);
        }
        
        .no-subjects {
            text-align: center;
            padding: 60px 30px;
            color: #6c757d;
        }
        
        .no-subjects i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .grade-summary {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            text-align: center;
        }
        
        .summary-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .summary-value {
            font-size: 1.5em;
            font-weight: 700;
            color: #1976d2;
        }
        
        .summary-label {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .grades-container {
                padding: 0 10px;
            }
            
            .student-header {
                padding: 20px;
            }
            
            .student-info-grid {
                grid-template-columns: 1fr;
            }
            
            .grades-table {
                font-size: 0.9em;
            }
            
            .grades-table th,
            .grades-table td {
                padding: 12px 8px;
            }
            
            .grade-input {
                width: 80px;
                padding: 8px 10px;
            }
            
            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo">
                    <img src="../assets/images/ISATU Logo.png" alt="ISATU Logo">
                </div>
                <div class="header-text">
                    <div class="university-name">ILOILO SCIENCE AND TECHNOLOGY UNIVERSITY</div>
                    <div class="system-title">FACULTY GRADE INPUT</div>
                </div>
            </div>
            <div class="user-section">
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-info" onclick="toggleDropdown()">
                        <i class="fas fa-user-tie"></i>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['faculty_name']); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="dropdown-user-name"><?php echo htmlspecialchars($_SESSION['faculty_name']); ?></div>
                            <div class="dropdown-user-role">Faculty Member</div>
                        </div>
                        <a href="faculty_profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../auth/faculty_logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="grades-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Student Header -->
        <div class="student-header">
            <a href="faculty_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            
            <h1 style="margin: 20px 0 0 0; font-size: 2em;">
                <i class="fas fa-user-graduate"></i>
                Grade Input for <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
            </h1>
            
            <div class="student-info-grid">
                <div class="info-item">
                    <div class="info-label">Student ID</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Program</div>
                    <div class="info-value"><?php echo htmlspecialchars(($student['program_name'] ?? 'N/A')); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Year & Section</div>
                    <div class="info-value"><?php echo htmlspecialchars(($student['year_level'] ?? 'N/A') . ' Year - Section ' . ($student['section_name'] ?? 'N/A')); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Academic Year</div>
                    <div class="info-value">
                        <?php 
                        if ($academic_year) {
                            echo $academic_year['year_start'] . '-' . $academic_year['year_end'] . ' (' . ucfirst($academic_year['semester']) . ')';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($enrollments)): ?>
            <div class="grades-form">
                <div class="no-subjects">
                    <i class="fas fa-book-open"></i>
                    <h3>No Subjects Found</h3>
                    <p>This student is not enrolled in any of your subjects, or no active enrollments were found.</p>
                    <a href="faculty_dashboard.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Grade Summary -->
            <!--<div class="grade-summary">
                <h3 style="margin: 0 0 15px 0; color: #1976d2;">
                    <i class="fas fa-chart-bar"></i> Grade Summary
                </h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-value" id="totalSubjects"><?php echo count($enrollments); ?></div>
                        <div class="summary-label">Total Subjects</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value" id="gradedSubjects">0</div>
                        <div class="summary-label">Graded Subjects</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value" id="averageGrade">-</div>
                        <div class="summary-label">Average Grade</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value" id="overallStatus">-</div>
                        <div class="summary-label">Status</div>
                    </div>
                </div>
            </div>-->

            <!-- Grades Form -->
            <form method="POST" class="grades-form" id="gradesForm">
                <input type="hidden" name="action" value="update_grades">
                
                <div class="form-header">
                    <h2 class="form-title">
                        <i class="fas fa-edit"></i>
                        Grade Input & Management
                    </h2>
                </div>

                <table class="grades-table">
                    <thead>
                        <tr>
                            <th>Subject Details</th>
                            <th>Credits</th>
                            <th>Midterm Grade</th>
                            <th>Final Grade</th>
                            <th>Overall Grade</th>
                            <th>Letter Grade</th>
                            <th>Remarks</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrollments as $enrollment): ?>
                            <tr>
                                <td>
                                    <div class="subject-info">
                                        <div class="course-code"><?php echo htmlspecialchars($enrollment['course_code']); ?></div>
                                        <div class="subject-name"><?php echo htmlspecialchars($enrollment['subject_name']); ?></div>
                                        <div class="subject-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-layer-group"></i>
                                                <span>Section <?php echo htmlspecialchars($enrollment['class_section_name']); ?></span>
                                            </div>
                                            <?php if ($enrollment['schedule']): ?>
                                                <div class="meta-item">
                                                    <i class="fas fa-clock"></i>
                                                    <span><?php echo htmlspecialchars($enrollment['schedule']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($enrollment['room']): ?>
                                                <div class="meta-item">
                                                    <i class="fas fa-door-open"></i>
                                                    <span><?php echo htmlspecialchars($enrollment['room']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <td style="text-align: center; font-weight: 600; color: #495057;">
                                    <?php echo $enrollment['credits']; ?>
                                </td>
                                
                                <td>
                                    <input type="number" 
                                           name="grades[<?php echo $enrollment['enrollment_id']; ?>][midterm]" 
                                           value="<?php echo $enrollment['midterm_grade']; ?>" 
                                           class="grade-input" 
                                           min="1.00" 
                                           max="5.00" 
                                           step="0.01"
                                           onchange="calculateGrade(<?php echo $enrollment['enrollment_id']; ?>)">
                                </td>
                                
                                <td>
                                    <input type="number" 
                                           name="grades[<?php echo $enrollment['enrollment_id']; ?>][final]" 
                                           value="<?php echo $enrollment['final_grade']; ?>" 
                                           class="grade-input" 
                                           min="1.00" 
                                           max="5.00" 
                                           step="0.01"
                                           onchange="calculateGrade(<?php echo $enrollment['enrollment_id']; ?>)">
                                </td>
                                
                                <td class="calculated-grade" id="overall_<?php echo $enrollment['enrollment_id']; ?>">
                                    <?php echo $enrollment['overall_grade'] ? number_format($enrollment['overall_grade'], 2) : '-'; ?>
                                </td>
                                
                                <td class="letter-grade" id="letter_<?php echo $enrollment['enrollment_id']; ?>">
                                    <?php echo htmlspecialchars($enrollment['letter_grade'] ?? '-'); ?>
                                </td>
                                
                                <td id="remarks_<?php echo $enrollment['enrollment_id']; ?>">
                                    <?php if ($enrollment['remarks']): ?>
                                        <span class="remarks remarks-<?php echo strtolower(str_replace(' ', '-', $enrollment['remarks'])); ?>">
                                            <?php echo htmlspecialchars($enrollment['remarks']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="remarks">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="font-size: 0.9em; color: #6c757d;">
                                    <?php echo $enrollment['graded_at'] ? date('M d, Y g:i A', strtotime($enrollment['graded_at'])) : 'Not graded yet'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="form-actions">
                    <div>
                        <span style="color: #6c757d; font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i>
                            Grades are on a 1.00-5.00 scale (1.00 = Highest, 5.00 = Failed)
                        </span>
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <button type="submit" class="btn btn-primary" onclick="return validateGrades()">
                            <i class="fas fa-save"></i> Save Grades
                        </button>
                        <button type="submit" name="action" value="submit_final_grades" class="btn btn-success" onclick="return confirm('Submit final grades? This action may not be reversible.')">
                            <i class="fas fa-check-circle"></i> Submit Final Grades
                        </button>
                        <a href="faculty_dashboard.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Calculate grade for individual student
        function calculateGrade(enrollmentId) {
            const midtermInput = document.querySelector(`input[name="grades[${enrollmentId}][midterm]"]`);
            const finalInput = document.querySelector(`input[name="grades[${enrollmentId}][final]"]`);
            const overallCell = document.getElementById(`overall_${enrollmentId}`);
            const letterCell = document.getElementById(`letter_${enrollmentId}`);
            const remarksCell = document.getElementById(`remarks_${enrollmentId}`);
            
            const midterm = parseFloat(midtermInput.value);
            const final = parseFloat(finalInput.value);
            
            if (!isNaN(midterm) && !isNaN(final)) {
                const overall = (midterm + final) / 2;
                
                // Update overall grade display
                overallCell.textContent = overall.toFixed(2);
                
                // Determine letter grade and remarks
                let letter = '';
                let remarks = '';
                let remarksClass = '';
                
                if (overall >= 1.00 && overall <= 1.25) {
                    letter = 'A';
                    remarks = 'Excellent';
                    remarksClass = 'remarks-excellent';
                } else if (overall >= 1.26 && overall <= 1.75) {
                    letter = 'B';
                    remarks = 'Very Good';
                    remarksClass = 'remarks-very-good';
                } else if (overall >= 1.76 && overall <= 2.25) {
                    letter = 'C';
                    remarks = 'Good';
                    remarksClass = 'remarks-good';
                } else if (overall >= 2.26 && overall <= 2.75) {
                    letter = 'D';
                    remarks = 'Satisfactory';
                    remarksClass = 'remarks-satisfactory';
                } else if (overall >= 2.76 && overall <= 3.00) {
                    letter = 'E';
                    remarks = 'Passed';
                    remarksClass = 'remarks-passed';
                } else {
                    letter = 'F';
                    remarks = 'Failed';
                    remarksClass = 'remarks-failed';
                }
                
                letterCell.textContent = letter;
                remarksCell.innerHTML = `<span class="remarks ${remarksClass}">${remarks}</span>`;
            } else {
                overallCell.textContent = '-';
                letterCell.textContent = '-';
                remarksCell.innerHTML = '<span class="remarks">-</span>';
            }
            
            // Update summary
            updateSummary();
        }
        
        // Update grade summary
        function updateSummary() {
            const gradeInputs = document.querySelectorAll('.grade-input');
            const totalSubjects = <?php echo count($enrollments); ?>;
            let gradedSubjects = 0;
            let totalGrades = 0;
            let gradeSum = 0;
            let passedSubjects = 0;
            
            // Count graded subjects and calculate average
            for (let i = 0; i < gradeInputs.length; i += 2) {
                const midterm = parseFloat(gradeInputs[i].value);
                const final = parseFloat(gradeInputs[i + 1].value);
                
                if (!isNaN(midterm) && !isNaN(final)) {
                    gradedSubjects++;
                    const overall = (midterm + final) / 2;
                    gradeSum += overall;
                    totalGrades++;
                    
                    if (overall <= 3.00) {
                        passedSubjects++;
                    }
                }
            }
            
            // Update summary display
            document.getElementById('gradedSubjects').textContent = gradedSubjects;
            
            if (totalGrades > 0) {
                const average = gradeSum / totalGrades;
                document.getElementById('averageGrade').textContent = average.toFixed(2);
                
                // Determine overall status
                let status = '';
                if (gradedSubjects === totalSubjects) {
                    if (passedSubjects === totalSubjects) {
                        status = 'PASSED';
                        document.getElementById('overallStatus').style.color = '#28a745';
                    } else {
                        status = 'FAILED';
                        document.getElementById('overallStatus').style.color = '#dc3545';
                    }
                } else {
                    status = 'INCOMPLETE';
                    document.getElementById('overallStatus').style.color = '#ffc107';
                }
                document.getElementById('overallStatus').textContent = status;
            } else {
                document.getElementById('averageGrade').textContent = '-';
                document.getElementById('overallStatus').textContent = '-';
                document.getElementById('overallStatus').style.color = '#6c757d';
            }
        }
        
        // Validate grades before submission
        function validateGrades() {
            const gradeInputs = document.querySelectorAll('.grade-input');
            let hasInvalidGrades = false;
            let hasGrades = false;
            
            gradeInputs.forEach(input => {
                const value = parseFloat(input.value);
                if (input.value) {
                    hasGrades = true;
                    if (isNaN(value) || value < 1.00 || value > 5.00) {
                        hasInvalidGrades = true;
                        input.style.borderColor = '#dc3545';
                        input.style.backgroundColor = '#fff5f5';
                    } else {
                        input.style.borderColor = '#28a745';
                        input.style.backgroundColor = '#f8fff8';
                    }
                } else {
                    input.style.borderColor = '#e9ecef';
                    input.style.backgroundColor = 'white';
                }
            });
            
            if (hasInvalidGrades) {
                alert('Please ensure all grades are between 1.00 and 5.00');
                return false;
            }
            
            if (!hasGrades) {
                return confirm('No grades entered. Continue anyway?');
            }
            
            return confirm('Save all entered grades?');
        }
        
        // Auto-save functionality (optional)
        let autoSaveTimer;
        let hasUnsavedChanges = false;
        
        function scheduleAutoSave() {
            hasUnsavedChanges = true;
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                // Auto-save could be implemented here
                console.log('Auto-save would trigger here');
            }, 30000); // 30 seconds
        }
        
        // User dropdown functionality
        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                if (validateGrades()) {
                    document.getElementById('gradesForm').submit();
                }
            }
            
            // Ctrl+B to go back
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                if (!hasUnsavedChanges || confirm('You have unsaved changes. Are you sure you want to leave?')) {
                    window.location.href = 'faculty_dashboard.php';
                }
            }
        });
        
        // Add event listeners for grade inputs
        document.addEventListener('DOMContentLoaded', function() {
            const gradeInputs = document.querySelectorAll('.grade-input');
            
            gradeInputs.forEach(input => {
                input.addEventListener('input', function() {
                    scheduleAutoSave();
                    
                    // Real-time validation
                    const value = parseFloat(this.value);
                    if (this.value && (isNaN(value) || value < 1.00 || value > 5.00)) {
                        this.style.borderColor = '#dc3545';
                        this.style.backgroundColor = '#fff5f5';
                    } else if (this.value) {
                        this.style.borderColor = '#28a745';
                        this.style.backgroundColor = '#f8fff8';
                    } else {
                        this.style.borderColor = '#e9ecef';
                        this.style.backgroundColor = 'white';
                    }
                });
                
                // Calculate grade on blur
                input.addEventListener('blur', function() {
                    const enrollmentId = this.name.match(/\[(\d+)\]/)[1];
                    calculateGrade(enrollmentId);
                });
            });
            
            // Initial summary update
            updateSummary();
            
            // Mark form as clean initially
            hasUnsavedChanges = false;
        });
        
        // Form submission handler
        document.getElementById('gradesForm').addEventListener('submit', function(e) {
            hasUnsavedChanges = false; // Clear unsaved changes flag
        });
        
        // Warn before leaving if there are unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                const message = 'You have unsaved changes. Are you sure you want to leave?';
                e.returnValue = message;
                return message;
            }
        });
        
        // Initialize calculations for existing grades
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($enrollments as $enrollment): ?>
                <?php if ($enrollment['midterm_grade'] && $enrollment['final_grade']): ?>
                    calculateGrade(<?php echo $enrollment['enrollment_id']; ?>);
                <?php endif; ?>
            <?php endforeach; ?>
        });
        
        // Print functionality
        function printGrades() {
            window.print();
        }
        
        // Add print styles
        const printStyles = `
            @media print {
                .header, .back-link, .form-actions { display: none !important; }
                .grades-container { margin: 0; padding: 20px; }
                .student-header { background: #f8f9fa !important; color: #000 !important; }
                .grades-table { page-break-inside: avoid; }
                .btn { display: none; }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>