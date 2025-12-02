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
                    SELECT e.id, e.student_id, cs.faculty_id, s.course_code, g.is_finalized
                    FROM enrollments e
                    INNER JOIN class_sections cs ON e.class_section_id = cs.id
                    INNER JOIN subjects s ON cs.subject_id = s.id
                    INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
                    LEFT JOIN grades g ON e.id = g.enrollment_id
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
                
                // Check if grade is finalized - if so, skip updating final grade but allow completion grade
                $is_finalized = $authorized_enrollment['is_finalized'] ?? 0;
                
                // Handle final grade (can be numeric, INC, or DRP) - only if not finalized
                $final = null;
                $final_status = null;
                $completion_grade = null;
                
                if (!$is_finalized && !empty($grade_data['final'])) {
                    $final_input = trim($grade_data['final']);
                    if (strtoupper($final_input) === 'INC') {
                        $final = 'INC';
                    } elseif (strtoupper($final_input) === 'DRP') {
                        $final = 'DRP';
                    } else {
                        $final = (float)$final_input;
                        if ($final < 1.00 || $final > 5.00) {
                            throw new Exception('Numeric grades must be between 1.00 and 5.00');
                        }
                    }
                }
                
                // Handle completion grade - always allow updating (even if finalized)
                if (!empty($grade_data['completion'])) {
                    $completion_input = trim($grade_data['completion']);
                    $completion_grade = (float)$completion_input;
                    if ($completion_grade < 1.00 || $completion_grade > 5.00) {
                        throw new Exception('Completion grades must be between 1.00 and 5.00');
                    }
                }
                
                // Calculate overall grade and determine remarks - UPDATED SCALE
                $overall = null;
                $remarks = null;
                
                // Get current final grade if finalized
                if ($is_finalized && !$final) {
                    $get_final = $pdo->prepare("SELECT final_grade FROM grades WHERE enrollment_id = ?");
                    $get_final->execute([$enrollment_id]);
                    $current = $get_final->fetch(PDO::FETCH_ASSOC);
                    $final = $current['final_grade'];
                }
                
                // If DRP, mark as dropped
                if ($final === 'DRP') {
                    $remarks = 'Dropped';
                }
                // If INC with completion grade, use completion grade
                elseif ($final === 'INC' && $completion_grade) {
                    $overall = $completion_grade;
                    
                    // Determine remarks based on completion grade - UPDATED SCALE
                    if ($overall >= 1.00 && $overall <= 1.50) {
                        $remarks = 'Excellent';
                    } elseif ($overall >= 1.60 && $overall <= 2.00) {
                        $remarks = 'Very Satisfactory';
                    } elseif ($overall >= 2.10 && $overall <= 2.50) {
                        $remarks = 'Satisfactory';
                    } elseif ($overall >= 2.60 && $overall <= 3.00) {
                        $remarks = 'Fair';
                    } else {
                        $remarks = 'Failed';
                    }
                }
                // If INC without completion grade, mark as incomplete
                elseif ($final === 'INC') {
                    $remarks = 'Incomplete';
                }
                // If numeric, use the final grade as overall
                elseif (is_numeric($final)) {
                    $overall = $final;
                    
                    // Determine remarks - UPDATED SCALE
                    if ($overall >= 1.00 && $overall <= 1.50) {
                        $remarks = 'Excellent';
                    } elseif ($overall >= 1.60 && $overall <= 2.00) {
                        $remarks = 'Very Satisfactory';
                    } elseif ($overall >= 2.10 && $overall <= 2.50) {
                        $remarks = 'Satisfactory';
                    } elseif ($overall >= 2.60 && $overall <= 3.00) {
                        $remarks = 'Fair';
                    } else {
                        $remarks = 'Failed';
                    }
                }
                
                // Check if grade record exists for this specific enrollment
                $stmt = $pdo->prepare("SELECT id, is_finalized FROM grades WHERE enrollment_id = ?");
                $stmt->execute([$enrollment_id]);
                $existing_grade = $stmt->fetch();
                
                if ($existing_grade) {
                    // Update existing grade - with additional security check
                    if ($is_finalized) {
                        // Only update completion grade and recalculate overall if finalized
                        $stmt = $pdo->prepare("
                            UPDATE grades g
                            INNER JOIN enrollments e ON g.enrollment_id = e.id
                            INNER JOIN class_sections cs ON e.class_section_id = cs.id
                            SET g.completion_grade = ?, g.overall_grade = ?, 
                                g.remarks = ?, g.updated_at = NOW()
                            WHERE g.enrollment_id = ? 
                              AND cs.faculty_id = ?
                              AND e.student_id = ?
                        ");
                        $stmt->execute([
                            $completion_grade,
                            $overall, 
                            $remarks, 
                            $enrollment_id, 
                            $_SESSION['faculty_id'], 
                            $student_id
                        ]);
                    } else {
                        // Update all fields if not finalized
                        $stmt = $pdo->prepare("
                            UPDATE grades g
                            INNER JOIN enrollments e ON g.enrollment_id = e.id
                            INNER JOIN class_sections cs ON e.class_section_id = cs.id
                            SET g.final_grade = ?, g.completion_grade = ?, g.overall_grade = ?, 
                                g.remarks = ?, g.graded_by = ?, g.updated_at = NOW()
                            WHERE g.enrollment_id = ? 
                              AND cs.faculty_id = ?
                              AND e.student_id = ?
                        ");
                        $stmt->execute([
                            $final, 
                            $completion_grade,
                            $overall, 
                            $remarks, 
                            $_SESSION['faculty_id'], 
                            $enrollment_id, 
                            $_SESSION['faculty_id'], 
                            $student_id
                        ]);
                    }
                } else {
                    // Create new grade record - with security validation
                    $stmt = $pdo->prepare("
                        INSERT INTO grades (enrollment_id, final_grade, completion_grade, overall_grade, 
                                          remarks, graded_by, graded_at, is_finalized)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)
                    ");
                    $stmt->execute([
                        $enrollment_id, 
                        $final, 
                        $completion_grade,
                        $overall, 
                        $remarks, 
                        $_SESSION['faculty_id']
                    ]);
                    
                    // Verify the insert was for authorized enrollment
                    if ($stmt->rowCount() == 0) {
                        error_log("SECURITY WARNING: Insert failed for enrollment ID $enrollment_id");
                        continue;
                    }
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
                // Mark grades as finalized
                $pdo->beginTransaction();
                
                $finalize_stmt = $pdo->prepare("
                    UPDATE grades g
                    INNER JOIN enrollments e ON g.enrollment_id = e.id
                    INNER JOIN class_sections cs ON e.class_section_id = cs.id
                    INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
                    SET g.is_finalized = 1, g.finalized_at = NOW(), g.finalized_by = ?
                    WHERE e.student_id = ? 
                      AND cs.faculty_id = ?
                      AND e.status = 'enrolled'
                      AND ay.is_active = 1
                      AND g.is_finalized = 0
                ");
                $finalize_stmt->execute([$_SESSION['faculty_id'], $student_id, $_SESSION['faculty_id']]);
                
                $pdo->commit();
                
                $message = 'Final grades submitted successfully! Grades are now locked (except completion grades for INC).';
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
            g.final_grade,
            g.completion_grade,
            g.overall_grade,
            g.remarks,
            g.graded_at,
            g.is_finalized,
            g.finalized_at,
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
    
    // Check if all grades are finalized
    $all_finalized = true;
    $has_grades = false;
    foreach ($enrollments as $enrollment) {
        if ($enrollment['grade_id']) {
            $has_grades = true;
            if (!$enrollment['is_finalized']) {
                $all_finalized = false;
                break;
            }
        }
    }
    
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
    <link href="../assets/css/input_grade.css" rel="stylesheet">
    <style>
        /* Additional styles for INC/DRP support */
        .remarks-incomplete {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .remarks-dropped {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
        }

        .grade-helper-text {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
            font-style: italic;
        }

        /* Completion grade column styling */
        .completion-grade-cell {
            position: relative;
        }

        .completion-grade-input {
            width: 100%;
            padding: 8px;
            border: 2px solid #ffc107;
            border-radius: 4px;
            font-size: 1em;
            text-align: center;
            background-color: #fff9e6;
            transition: all 0.3s ease;
        }

        .completion-grade-input:focus {
            outline: none;
            border-color: #ff9800;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
        }

        .completion-grade-input:disabled {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .completion-grade-label {
            display: block;
            font-size: 0.75em;
            color: #856404;
            margin-bottom: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .completion-grade-hint {
            font-size: 0.7em;
            color: #6c757d;
            margin-top: 2px;
            font-style: italic;
        }

        /* Hide completion grade column by default */
        .completion-grade-header,
        .completion-grade-cell {
            display: none;
        }

        /* Show when there's at least one INC grade */
        .has-incomplete .completion-grade-header,
        .has-incomplete .completion-grade-cell {
            display: table-cell;
        }

        /* Finalized grade styling */
        .grade-input:disabled:not(.completion-grade-input) {
            background-color: #e9ecef;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .finalized-badge {
            display: inline-block;
            padding: 4px 8px;
            background-color: #28a745;
            color: white;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: 600;
            margin-left: 8px;
        }

        .finalized-notice {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .finalized-notice i {
            font-size: 1.5em;
        }

        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
        }

        .toast {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast-success {
            border-left: 4px solid #28a745;
        }

        .toast-error {
            border-left: 4px solid #dc3545;
        }

        .toast-warning {
            border-left: 4px solid #ffc107;
        }

        .toast-icon {
            font-size: 1.5em;
            flex-shrink: 0;
        }

        .toast-success .toast-icon {
            color: #28a745;
        }

        .toast-error .toast-icon {
            color: #dc3545;
        }

        .toast-warning .toast-icon {
            color: #ffc107;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 0.95em;
        }

        .toast-message {
            font-size: 0.85em;
            color: #6c757d;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 1.2em;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .toast-close:hover {
            color: #000;
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
        <!-- Toast Notification Container -->
        <div class="toast-container" id="toastContainer"></div>

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

            <!-- Grades Form -->
            <form method="POST" class="grades-form" id="gradesForm">
                <input type="hidden" name="action" value="update_grades">
                
                <div class="form-header">
                    <h2 class="form-title">
                        <i class="fas fa-edit"></i>
                        Grade Input & Management
                    </h2>
                </div>

                <table class="grades-table" id="gradesTable">
                    <thead>
                        <tr>
                            <th>Subject Details</th>
                            <th>Credits</th>
                            <th>Final Grade</th>
                            <th class="completion-grade-header">Completion Grade</th>
                            <th>Overall Grade</th>
                            <th>Remarks</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrollments as $enrollment): ?>
                            <?php $is_finalized = $enrollment['is_finalized'] ?? 0; ?>
                            <tr>
                                <td>
                                    <div class="subject-info">
                                        <div class="course-code">
                                            <?php echo htmlspecialchars($enrollment['course_code']); ?>
                                            <?php if ($is_finalized): ?>
                                                <span class="finalized-badge"><i class="fas fa-lock"></i></span>
                                            <?php endif; ?>
                                        </div>
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
                                    <input type="text" 
                                           name="grades[<?php echo $enrollment['enrollment_id']; ?>][final]" 
                                           value="<?php echo htmlspecialchars($enrollment['final_grade']); ?>" 
                                           class="grade-input" 
                                           placeholder="1.00-5.00, INC, DRP"
                                           data-enrollment-id="<?php echo $enrollment['enrollment_id']; ?>"
                                           onchange="handleFinalGradeChange(<?php echo $enrollment['enrollment_id']; ?>)"
                                           <?php echo $is_finalized ? 'disabled title="Grade is finalized"' : ''; ?>>
                                    <?php if ($is_finalized): ?>
                                        <input type="hidden" name="grades[<?php echo $enrollment['enrollment_id']; ?>][final]" value="<?php echo htmlspecialchars($enrollment['final_grade']); ?>">
                                    <?php endif; ?>
                                </td>

                                <td class="completion-grade-cell">
                                    <label class="completion-grade-label">When Completed:</label>
                                    <input type="text" 
                                           name="grades[<?php echo $enrollment['enrollment_id']; ?>][completion]" 
                                           value="<?php echo htmlspecialchars($enrollment['completion_grade']); ?>" 
                                           class="completion-grade-input" 
                                           id="completion_<?php echo $enrollment['enrollment_id']; ?>"
                                           placeholder="1.00-5.00"
                                           <?php echo (strtoupper($enrollment['final_grade']) !== 'INC') ? 'disabled' : ''; ?>
                                           onchange="calculateGrade(<?php echo $enrollment['enrollment_id']; ?>)">
                                    <div class="completion-grade-hint">Grade after completing requirements</div>
                                </td>
                                
                                <td class="calculated-grade" id="overall_<?php echo $enrollment['enrollment_id']; ?>">
                                    <?php echo $enrollment['overall_grade'] ? number_format($enrollment['overall_grade'], 4) : '-'; ?>
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
                                    <?php if ($is_finalized && $enrollment['finalized_at']): ?>
                                        <br><small style="color: #28a745;"><i class="fas fa-lock"></i> Finalized: <?php echo date('M d, Y', strtotime($enrollment['finalized_at'])); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="form-actions">
                    <div>
                        <span style="color: #6c757d; font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i>
                            Grades: 1.00-5.00 (1.00=Highest, 5.00=Failed) or INC (Incomplete) or DRP (Dropped)
                            <?php if ($all_finalized): ?>
                                <br><i class="fas fa-lock"></i> <strong>Finalized grades cannot be changed (except completion grades)</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <button type="submit" class="btn btn-primary" onclick="return validateGrades()">
                            <i class="fas fa-save"></i> Save Grades
                        </button>
                        <?php if (!$all_finalized): ?>
                        <button type="submit" name="action" value="submit_final_grades" class="btn btn-success" onclick="return confirm('Submit final grades? This will lock all final grades and prevent further changes (except completion grades for INC).\n\nAre you sure?')">
                            <i class="fas fa-check-circle"></i> Submit Final Grades
                        </button>
                        <?php endif; ?>
                        <a href="faculty_dashboard.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Toast notification system
        function showToast(message, type = 'success', title = '') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Determine icon based on title or type
            let icon;
            if (title === 'Grades Finalized') {
                icon = 'lock';
            } else {
                icon = type === 'success' ? 'check-circle' : 
                      type === 'error' ? 'exclamation-circle' : 
                      'exclamation-triangle';
            }
            
            const defaultTitle = type === 'success' ? 'Success' : 
                                type === 'error' ? 'Error' : 
                                'Warning';
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title || defaultTitle}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'slideInRight 0.3s ease-out reverse';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 5000);
        }

        // Show PHP messages as toasts on page load
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast(
                <?php echo json_encode($message); ?>,
                <?php echo json_encode($message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'error')); ?>
            );
        });
        <?php endif; ?>

        // Show finalized grades notice as toast
        <?php if ($all_finalized && $has_grades): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast(
                'Final grades have been submitted and locked. Only completion grades for INC can be updated.',
                'warning',
                'Grades Finalized'
            );
        });
        <?php endif; ?>

        // Handle final grade change to show/hide completion column
        function handleFinalGradeChange(enrollmentId) {
            const finalInput = document.querySelector(`input[name="grades[${enrollmentId}][final]"]:not([type="hidden"])`);
            const completionInput = document.getElementById(`completion_${enrollmentId}`);
            
            if (!finalInput || finalInput.disabled) {
                return; // Grade is finalized, don't do anything
            }
            
            const finalValue = finalInput.value.trim().toUpperCase();
            
            // Enable completion input only if final grade is INC
            if (finalValue === 'INC') {
                completionInput.disabled = false;
                completionInput.focus();
                updateCompletionColumnVisibility();
            } else {
                completionInput.disabled = true;
                completionInput.value = '';
            }
            
            // Calculate grade
            calculateGrade(enrollmentId);
        }

        // Update visibility of completion grade column
        function updateCompletionColumnVisibility() {
            const table = document.getElementById('gradesTable');
            const gradeInputs = document.querySelectorAll('.grade-input:not([type="hidden"])');
            let hasIncomplete = false;
            
            // Check if any grade is INC
            gradeInputs.forEach(input => {
                if (input.value.trim().toUpperCase() === 'INC') {
                    hasIncomplete = true;
                }
            });
            
            // Toggle completion column visibility
            if (hasIncomplete) {
                table.classList.add('has-incomplete');
            } else {
                table.classList.remove('has-incomplete');
            }
        }

        // Calculate grade for individual student - UPDATED SCALE
        function calculateGrade(enrollmentId) {
            const finalInput = document.querySelector(`input[name="grades[${enrollmentId}][final]"]:not([type="hidden"])`);
            const completionInput = document.getElementById(`completion_${enrollmentId}`);
            const overallCell = document.getElementById(`overall_${enrollmentId}`);
            const remarksCell = document.getElementById(`remarks_${enrollmentId}`);
            
            const finalValue = finalInput ? finalInput.value.trim().toUpperCase() : '';
            const completionValue = completionInput.value.trim();
            
            let remarks = '';
            let remarksClass = '';
            let overall = null;
            
            // Check if DRP
            if (finalValue === 'DRP') {
                remarks = 'Dropped';
                remarksClass = 'remarks-dropped';
                overallCell.textContent = '-';
            }
            // Check if INC
            else if (finalValue === 'INC') {
                // If completion grade is provided, use it
                if (completionValue) {
                    const completion = parseFloat(completionValue);
                    if (!isNaN(completion) && completion >= 1.00 && completion <= 5.00) {
                        overall = completion;
                        overallCell.textContent = overall.toFixed(4);
                        
                        // Determine remarks based on completion grade - UPDATED SCALE
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
                    } else {
                        // Invalid completion grade
                        remarks = 'Incomplete';
                        remarksClass = 'remarks-incomplete';
                        overallCell.textContent = '-';
                    }
                } else {
                    // No completion grade yet
                    remarks = 'Incomplete';
                    remarksClass = 'remarks-incomplete';
                    overallCell.textContent = '-';
                }
            }
            // Must be numeric
            else {
                const final = parseFloat(finalValue);
                
                if (!isNaN(final)) {
                    overall = final;
                    
                    // Update overall grade display
                    overallCell.textContent = overall.toFixed(4);
                    
                    // Determine remarks - UPDATED SCALE
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
                } else {
                    overallCell.textContent = '-';
                    remarksCell.innerHTML = '<span class="remarks">-</span>';
                    updateSummary();
                    return;
                }
            }
            
            remarksCell.innerHTML = `<span class="remarks ${remarksClass}">${remarks}</span>`;
            
            // Update summary
            updateSummary();
        }
        
        // Update grade summary
        function updateSummary() {
            const gradeInputs = document.querySelectorAll('.grade-input:not([type="hidden"])');
            const totalSubjects = <?php echo count($enrollments); ?>;
            let gradedSubjects = 0;
            let totalGrades = 0;
            let gradeSum = 0;
            let passedSubjects = 0;
            
            // Count graded subjects and calculate average
            for (let i = 0; i < gradeInputs.length; i++) {
                const enrollmentId = gradeInputs[i].dataset.enrollmentId;
                const finalValue = gradeInputs[i].value.trim().toUpperCase();
                const completionInput = document.getElementById(`completion_${enrollmentId}`);
                
                // Skip DRP from calculations
                if (finalValue === 'DRP') {
                    if (finalValue) {
                        gradedSubjects++;
                    }
                    continue;
                }
                
                // Handle INC with completion grade
                if (finalValue === 'INC') {
                    if (finalValue) {
                        gradedSubjects++;
                    }
                    
                    if (completionInput && completionInput.value.trim()) {
                        const completion = parseFloat(completionInput.value.trim());
                        if (!isNaN(completion)) {
                            gradeSum += completion;
                            totalGrades++;
                            if (completion <= 3.00) {
                                passedSubjects++;
                            }
                        }
                    }
                    continue;
                }
                
                // Handle numeric grades
                const final = parseFloat(finalValue);
                
                if (!isNaN(final)) {
                    gradedSubjects++;
                    gradeSum += final;
                    totalGrades++;
                    
                    if (final <= 3.00) {
                        passedSubjects++;
                    }
                }
            }
        }
        
        // Validate grades before submission
        function validateGrades() {
            const gradeInputs = document.querySelectorAll('.grade-input:not([type="hidden"])');
            let hasInvalidGrades = false;
            let hasGrades = false;
            
            gradeInputs.forEach(input => {
                if (input.disabled) return; // Skip finalized grades
                
                const enrollmentId = input.dataset.enrollmentId;
                const value = input.value.trim().toUpperCase();
                const completionInput = document.getElementById(`completion_${enrollmentId}`);
                
                if (value) {
                    hasGrades = true;
                    // Check if it's INC or DRP
                    if (value === 'INC' || value === 'DRP') {
                        input.style.borderColor = '#17a2b8';
                        input.style.backgroundColor = '#f0f9ff';
                        
                        // Validate completion grade if INC
                        if (value === 'INC' && completionInput && completionInput.value.trim()) {
                            const completion = parseFloat(completionInput.value.trim());
                            if (isNaN(completion) || completion < 1.00 || completion > 5.00) {
                                hasInvalidGrades = true;
                                completionInput.style.borderColor = '#dc3545';
                                completionInput.style.backgroundColor = '#fff5f5';
                            } else {
                                completionInput.style.borderColor = '#28a745';
                                completionInput.style.backgroundColor = '#f8fff8';
                            }
                        }
                    } else {
                        // It should be numeric
                        const numValue = parseFloat(value);
                        if (isNaN(numValue) || numValue < 1.00 || numValue > 5.00) {
                            hasInvalidGrades = true;
                            input.style.borderColor = '#dc3545';
                            input.style.backgroundColor = '#fff5f5';
                        } else {
                            input.style.borderColor = '#28a745';
                            input.style.backgroundColor = '#f8fff8';
                        }
                    }
                } else {
                    input.style.borderColor = '#e9ecef';
                    input.style.backgroundColor = 'white';
                }
            });
            
            if (hasInvalidGrades) {
                alert('Please ensure all grades are either:\n- Between 1.00 and 5.00\n- "INC" for Incomplete (with optional completion grade 1.00-5.00)\n- "DRP" for Dropped');
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
            const gradeInputs = document.querySelectorAll('.grade-input:not([type="hidden"])');
            
            gradeInputs.forEach(input => {
                const enrollmentId = input.dataset.enrollmentId;
                const completionInput = document.getElementById(`completion_${enrollmentId}`);
                
                input.addEventListener('input', function() {
                    if (!this.disabled) {
                        scheduleAutoSave();
                        
                        // Real-time validation
                        const value = this.value.trim().toUpperCase();
                        if (value) {
                            // Check if it's INC or DRP
                            if (value === 'INC' || value === 'DRP') {
                                this.style.borderColor = '#17a2b8';
                                this.style.backgroundColor = '#f0f9ff';
                            } else {
                                // It should be numeric
                                const numValue = parseFloat(value);
                                if (isNaN(numValue) || numValue < 1.00 || numValue > 5.00) {
                                    this.style.borderColor = '#dc3545';
                                    this.style.backgroundColor = '#fff5f5';
                                } else {
                                    this.style.borderColor = '#28a745';
                                    this.style.backgroundColor = '#f8fff8';
                                }
                            }
                        } else {
                            this.style.borderColor = '#e9ecef';
                            this.style.backgroundColor = 'white';
                        }
                    }
                });
                
                // Calculate grade on blur
                input.addEventListener('blur', function() {
                    if (!this.disabled) {
                        handleFinalGradeChange(enrollmentId);
                    }
                });

                // Add listener for completion input
                if (completionInput) {
                    completionInput.addEventListener('input', function() {
                        scheduleAutoSave();
                        
                        const value = this.value.trim();
                        if (value) {
                            const numValue = parseFloat(value);
                            if (isNaN(numValue) || numValue < 1.00 || numValue > 5.00) {
                                this.style.borderColor = '#dc3545';
                                this.style.backgroundColor = '#fff5f5';
                            } else {
                                this.style.borderColor = '#28a745';
                                this.style.backgroundColor = '#f8fff8';
                            }
                        } else {
                            this.style.borderColor = '#ffc107';
                            this.style.backgroundColor = '#fff9e6';
                        }
                    });

                    completionInput.addEventListener('blur', function() {
                        calculateGrade(enrollmentId);
                    });
                }
            });
            
            // Initial summary update and column visibility check
            updateSummary();
            updateCompletionColumnVisibility();
            
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
                <?php if ($enrollment['final_grade']): ?>
                    calculateGrade(<?php echo $enrollment['enrollment_id']; ?>);
                    <?php if ($enrollment['final_grade'] === 'INC'): ?>
                        const completionInput_<?php echo $enrollment['enrollment_id']; ?> = document.getElementById('completion_<?php echo $enrollment['enrollment_id']; ?>');
                        if (completionInput_<?php echo $enrollment['enrollment_id']; ?>) {
                            completionInput_<?php echo $enrollment['enrollment_id']; ?>.disabled = false;
                        }
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Update completion column visibility after loading existing data
            updateCompletionColumnVisibility();
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
                .completion-grade-header,
                .completion-grade-cell {
                    display: table-cell !important;
                }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>