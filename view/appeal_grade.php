<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: ../index.php');
    exit();
}

// Include database configuration
require_once '../config/database.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_appeal'])) {
    try {
        $grade_id = (int)$_POST['grade_id'];
        $reason = trim($_POST['reason']);
        $uploaded_file = null;
        
        // Verify that this grade belongs to the logged-in student
        $verify_stmt = $pdo->prepare("
            SELECT g.id, e.student_id 
            FROM grades g
            INNER JOIN enrollments e ON g.enrollment_id = e.id
            WHERE g.id = ? AND e.student_id = ?
        ");
        $verify_stmt->execute([$grade_id, $_SESSION['student_id']]);
        $grade_check = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$grade_check) {
            throw new Exception('Invalid grade selection or unauthorized access.');
        }
        
        // Check if appeal already exists for this grade
        $check_stmt = $pdo->prepare("
            SELECT id FROM grade_appeals 
            WHERE grade_id = ? AND student_id = ?
        ");
        $check_stmt->execute([$grade_id, $_SESSION['student_id']]);
        
        if ($check_stmt->fetch()) {
            throw new Exception('You have already submitted an appeal for this grade.');
        }
        
        // Handle file upload
        if (isset($_FILES['supporting_document']) && $_FILES['supporting_document']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['supporting_document'];
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'image/jpg'];
            $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            // Validate file type
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types) || !in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only PDF, DOC, DOCX, JPG, and PNG files are allowed.');
            }
            
            // Validate file size
            if ($file['size'] > $max_file_size) {
                throw new Exception('File size must not exceed 5MB.');
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/grade_appeals/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_name = 'appeal_' . $_SESSION['student_id'] . '_' . $grade_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $file_name;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                throw new Exception('Failed to upload file. Please try again.');
            }
            
            $uploaded_file = 'uploads/grade_appeals/' . $file_name;
        }
        
        // Insert appeal
        $stmt = $pdo->prepare("
            INSERT INTO grade_appeals (grade_id, student_id, reason, supporting_document, status, submitted_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$grade_id, $_SESSION['student_id'], $reason, $uploaded_file]);
        
        // Log the activity
        $log_stmt = $pdo->prepare("
            INSERT INTO user_logs (user_id, activity_type, description, ip_address, created_at)
            VALUES (?, 'grade_appeal_submitted', ?, ?, NOW())
        ");
        $log_description = "Submitted grade appeal for grade ID $grade_id" . ($uploaded_file ? " with supporting document" : "");
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log_stmt->execute([$_SESSION['student_id'], $log_description, $ip_address]);
        
        $success_message = 'Your grade appeal has been submitted successfully and is pending review.';
        
    } catch (Exception $e) {
        error_log("Grade appeal error: " . $e->getMessage());
        $error_message = $e->getMessage();
        
        // Clean up uploaded file if appeal insertion failed
        if (isset($uploaded_file) && file_exists('../' . $uploaded_file)) {
            unlink('../' . $uploaded_file);
        }
    }
}

try {
    // Get student information
    $stmt = $pdo->prepare("
        SELECT u.*, sp.year_level, sp.program_id, p.program_name, p.program_code,
               s.section_name
        FROM users u 
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$_SESSION['student_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get student's grades that can be appealed
    $stmt = $pdo->prepare("
        SELECT 
            g.id as grade_id,
            g.midterm_grade,
            g.final_grade,
            g.overall_grade,
            g.letter_grade,
            g.remarks,
            g.graded_at,
            s.course_code,
            s.subject_name,
            s.credits,
            ay.year_start,
            ay.year_end,
            ay.semester,
            cs.section_name,
            f.first_name as faculty_first_name,
            f.last_name as faculty_last_name,
            e.status as enrollment_status,
            CASE WHEN ga.id IS NOT NULL THEN 1 ELSE 0 END as has_appeal,
            ga.status as appeal_status
        FROM grades g
        INNER JOIN enrollments e ON g.enrollment_id = e.id
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN subjects s ON cs.subject_id = s.id
        INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
        INNER JOIN users f ON cs.faculty_id = f.id
        LEFT JOIN grade_appeals ga ON g.id = ga.grade_id AND ga.student_id = e.student_id
        WHERE e.student_id = ? 
        AND (g.overall_grade IS NOT NULL 
            OR g.letter_grade IN ('INC', 'DRP')
            OR g.remarks IN ('Incomplete', 'Dropped'))
        AND e.status IN ('enrolled', 'dropped', 'completed')
        ORDER BY g.graded_at DESC
    ");
    $stmt->execute([$_SESSION['student_id']]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get existing appeals
    $appeals_stmt = $pdo->prepare("
        SELECT 
            ga.id,
            ga.grade_id,
            ga.reason,
            ga.supporting_document,
            ga.status,
            ga.submitted_at,
            ga.reviewed_at,
            ga.admin_remarks,
            s.course_code,
            s.subject_name,
            g.overall_grade,
            g.letter_grade,
            reviewer.first_name as reviewer_first_name,
            reviewer.last_name as reviewer_last_name
        FROM grade_appeals ga
        INNER JOIN grades g ON ga.grade_id = g.id
        INNER JOIN enrollments e ON g.enrollment_id = e.id
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN subjects s ON cs.subject_id = s.id
        LEFT JOIN users reviewer ON ga.reviewed_by = reviewer.id
        WHERE ga.student_id = ?
        ORDER BY ga.submitted_at DESC
    ");
    $appeals_stmt->execute([$_SESSION['student_id']]);
    $my_appeals = $appeals_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Appeal form error: " . $e->getMessage());
    $grades = [];
    $my_appeals = [];
}

// Helper function for grade class
function getGradeClass($overall_grade) {
    if ($overall_grade >= 1.00 && $overall_grade <= 1.25) return 'grade-excellent';
    if ($overall_grade >= 1.26 && $overall_grade <= 1.75) return 'grade-very-good';
    if ($overall_grade >= 1.76 && $overall_grade <= 2.25) return 'grade-good';
    if ($overall_grade >= 2.26 && $overall_grade <= 2.75) return 'grade-satisfactory';
    if ($overall_grade >= 2.76 && $overall_grade <= 3.00) return 'grade-passing';
    return 'grade-failing';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Appeal - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/student_dashboard.css" rel="stylesheet">
    <style>
        .appeal-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #3498db;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 0.95em;
        }

        .back-link:hover {
            color: #2980b9;
        }

        .page-title {
            font-size: 2em;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: #6c757d;
            margin-bottom: 25px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 1.4em;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .grades-grid {
            display: grid;
            gap: 20px;
        }

        .grade-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            background: #f8f9fa;
            transition: box-shadow 0.3s;
        }

        .grade-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .grade-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .grade-subject h3 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 1.2em;
        }

        .grade-meta {
            font-size: 0.9em;
            color: #6c757d;
        }

        .grade-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 70px;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.3em;
        }

        .grade-excellent { background: #d4edda; color: #155724; }
        .grade-very-good { background: #d1ecf1; color: #0c5460; }
        .grade-good { background: #cce5ff; color: #004085; }
        .grade-satisfactory { background: #fff3cd; color: #856404; }
        .grade-passing { background: #f8d7da; color: #856404; }
        .grade-failing { background: #f8d7da; color: #721c24; }

        .grade-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
        }

        .detail-item {
            text-align: center;
        }

        .detail-label {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .detail-value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }

        .grade-actions {
            margin-top: 15px;
        }

        .btn-appeal {
            padding: 10px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-appeal:hover {
            background: #2980b9;
        }

        .btn-appeal:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        .appeals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .appeals-table th {
            background: #f8f9fa;
            padding: 14px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        .appeals-table td {
            padding: 14px;
            border-bottom: 1px solid #dee2e6;
        }

        .appeals-table tr:hover {
            background: #f8f9fa;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            backdrop-filter: blur(2px);
        }

        .modal-content {
            background: white;
            max-width: 650px;
            margin: 60px auto;
            border-radius: 12px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }

        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.8em;
            cursor: pointer;
            color: #6c757d;
            line-height: 1;
        }

        .close-btn:hover {
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 0.95em;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }

        textarea.form-control {
            min-height: 140px;
            resize: vertical;
            font-family: inherit;
        }

        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: #3498db;
            background: #e7f3ff;
        }

        .file-upload-area.drag-over {
            border-color: #28a745;
            background: #d4edda;
        }

        .file-upload-icon {
            font-size: 3em;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .file-upload-text {
            color: #495057;
            margin-bottom: 5px;
        }

        .file-upload-hint {
            font-size: 0.85em;
            color: #6c757d;
        }

        #supporting_document {
            display: none;
        }

        .file-preview {
            margin-top: 15px;
            padding: 12px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            display: none;
            align-items: center;
            justify-content: space-between;
        }

        .file-preview.show {
            display: flex;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-icon {
            font-size: 1.5em;
            color: #3498db;
        }

        .file-details {
            display: flex;
            flex-direction: column;
        }

        .file-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .file-size {
            font-size: 0.85em;
            color: #6c757d;
        }

        .remove-file-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .remove-file-btn:hover {
            background: #c82333;
        }

        .btn-submit {
            padding: 12px 32px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #218838;
        }

        .required {
            color: #dc3545;
        }

        .document-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #3498db;
            text-decoration: none;
            font-size: 0.9em;
        }

        .document-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Header (matching student_dashboard.php) -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo">
                    <img src="../assets/images/ISATU Logo.png" alt="ISATU Logo">
                </div>
                <div class="header-text">
                    <div class="university-name">ILOILO SCIENCE AND TECHNOLOGY UNIVERSITY</div>
                    <div class="system-title">STUDENT PORTAL</div>
                </div>
            </div>
            <div class="user-section">
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-info" onclick="toggleDropdown()">
                        <i class="fas fa-user-graduate"></i>
                        <span class="user-name"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="dropdown-user-name"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></div>
                            <div class="dropdown-user-role">Student</div>
                            <?php if (!empty($student['student_id'])): ?>
                            <div class="dropdown-user-id">ID: <?php echo htmlspecialchars($student['student_id']); ?></div>
                            <?php endif; ?>
                        </div>
                        <a href="student_dashboard.php" class="dropdown-item">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="student_profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <a href="student_pre_enrollment.php" class="dropdown-item">
                            <i class="fas fa-file-alt"></i>
                            <span>Pre-enrollment</span>
                        </a>
                        <a href="appeal_grade.php" class="dropdown-item">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Grade Appeal</span>
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../auth/student_logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="appeal-container">
        <a href="student_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Grades
        </a>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success_message); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <h1 class="page-title"><i class="fas fa-exclamation-triangle"></i> Grade Appeal</h1>
        <p class="page-subtitle">Submit an appeal if you believe there is an error in your grade calculation</p>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Important:</strong> Grade appeals should only be submitted if you believe there is a genuine error in grade calculation or recording. 
                Appeals are reviewed by administrators and faculty.
            </div>
        </div>

        <!-- Available Grades for Appeal -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-clipboard-list"></i>
                Your Grades
            </h2>

            <?php if (empty($grades)): ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h3>No Grades Available</h3>
                <p>You don't have any graded subjects at this time.</p>
            </div>
            <?php else: ?>
            <div class="grades-grid">
                <?php foreach ($grades as $grade): ?>
                <div class="grade-card">
                    <div class="grade-header">
                        <div class="grade-subject">
                            <h3><?php echo htmlspecialchars($grade['course_code']); ?> - <?php echo htmlspecialchars($grade['subject_name']); ?></h3>
                            <div class="grade-meta">
                                <div><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($grade['faculty_first_name'] . ' ' . $grade['faculty_last_name']); ?></div>
                                <div><i class="fas fa-calendar"></i> <?php echo $grade['year_start'] . '-' . $grade['year_end'] . ' (' . ucfirst($grade['semester']) . ')'; ?></div>
                                <?php if ($grade['section_name']): ?>
                                <div><i class="fas fa-users"></i> Section <?php echo htmlspecialchars($grade['section_name']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="grade-badge <?php echo getGradeClass($grade['overall_grade']); ?>">
                            <?php echo number_format($grade['overall_grade'], 2); ?>
                        </div>
                    </div>

                    <div class="grade-details">
                        <div class="detail-item">
                            <div class="detail-label">Midterm</div>
                            <div class="detail-value"><?php echo $grade['midterm_grade'] ?? 'N/A'; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Final</div>
                            <div class="detail-value"><?php echo $grade['final_grade'] ?? 'N/A'; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Letter</div>
                            <div class="detail-value"><?php echo htmlspecialchars($grade['letter_grade']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Remarks</div>
                            <div class="detail-value"><?php echo htmlspecialchars($grade['remarks']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Credits</div>
                            <div class="detail-value"><?php echo $grade['credits']; ?></div>
                        </div>
                    </div>

                    <div class="grade-actions">
                        <?php if ($grade['has_appeal']): ?>
                            <span class="status-badge status-<?php echo $grade['appeal_status']; ?>">
                                <i class="fas fa-info-circle"></i>
                                Appeal: <?php echo ucfirst($grade['appeal_status']); ?>
                            </span>
                        <?php else: ?>
                            <button class="btn-appeal" 
                                    data-grade-id="<?php echo $grade['grade_id']; ?>"
                                    data-course-code="<?php echo htmlspecialchars($grade['course_code']); ?>"
                                    data-subject-name="<?php echo htmlspecialchars($grade['subject_name']); ?>"
                                    onclick="openAppealModal(this)">
                                <i class="fas fa-flag"></i> Submit Appeal
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- My Appeals History -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Appeals History
            </h2>

            <?php if (empty($my_appeals)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Appeals Submitted</h3>
                <p>You haven't submitted any grade appeals yet.</p>
            </div>
            <?php else: ?>
            <table class="appeals-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Grade</th>
                        <th>Reason</th>
                        <th>Document</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Admin Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_appeals as $appeal): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($appeal['course_code']); ?></strong><br>
                            <small style="color: #6c757d;"><?php echo htmlspecialchars($appeal['subject_name']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo number_format($appeal['overall_grade'], 2); ?></strong>
                            (<?php echo htmlspecialchars($appeal['letter_grade']); ?>)
                        </td>
                        <td><?php echo htmlspecialchars($appeal['reason']); ?></td>
                        <td>
                            <?php if ($appeal['supporting_document']): ?>
                                <a href="../<?php echo htmlspecialchars($appeal['supporting_document']); ?>" target="_blank" class="document-link">
                                    <i class="fas fa-file-alt"></i> View Document
                                </a>
                            <?php else: ?>
                                <span style="color: #6c757d;">No document</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $appeal['status']; ?>">
                                <?php echo ucfirst($appeal['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($appeal['submitted_at'])); ?></td>
                        <td><?php echo $appeal['admin_remarks'] ? htmlspecialchars($appeal['admin_remarks']) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Appeal Modal -->
    <div id="appealModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Submit Grade Appeal</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="appealForm">
                <input type="hidden" name="submit_appeal" value="1">
                <input type="hidden" name="grade_id" id="modal_grade_id">
                
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" id="modal_subject" class="form-control" readonly>
                </div>

                <div class="form-group">
                    <label>Reason for Appeal <span class="required">*</span></label>
                    <textarea name="reason" class="form-control" required 
                              placeholder="Explain why you are appealing this grade. Be specific about any errors you believe were made in the calculation or recording of your grade."></textarea>
                </div>

                <div class="form-group">
                    <label>Supporting Document (Optional)</label>
                    <div class="file-upload-area" id="fileUploadArea" onclick="document.getElementById('supporting_document').click()">
                        <div class="file-upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="file-upload-text">
                            Click to upload or drag and drop
                        </div>
                        <div class="file-upload-hint">
                            PDF, DOC, DOCX, JPG, PNG (Max 5MB)
                        </div>
                    </div>
                    <input type="file" name="supporting_document" id="supporting_document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    
                    <div class="file-preview" id="filePreview">
                        <div class="file-info">
                            <div class="file-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="file-details">
                                <div class="file-name" id="fileName"></div>
                                <div class="file-size" id="fileSize"></div>
                            </div>
                        </div>
                        <button type="button" class="remove-file-btn" onclick="removeFile()">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <small>Your appeal will be reviewed by administrators and faculty. Please provide clear and specific reasons for your appeal. You may optionally attach supporting documents such as scanned test papers, email communications, or other relevant evidence.</small>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit Appeal
                </button>
            </form>
        </div>
    </div>

    <script>
        // User dropdown functionality (from student_dashboard.php)
        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
        }

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Modal functions - UPDATED TO FIX SUBJECT DISPLAY
        function openAppealModal(button) {
            const gradeId = button.getAttribute('data-grade-id');
            const courseCode = button.getAttribute('data-course-code');
            const subjectName = button.getAttribute('data-subject-name');
            
            document.getElementById('modal_grade_id').value = gradeId;
            document.getElementById('modal_subject').value = courseCode + ' - ' + subjectName;
            document.getElementById('appealModal').style.display = 'block';
            
            // Reset form and hide file preview
            const form = document.getElementById('appealForm');
            const reasonField = form.querySelector('textarea[name="reason"]');
            reasonField.value = '';
            document.getElementById('filePreview').classList.remove('show');
        }

        function closeModal() {
            document.getElementById('appealModal').style.display = 'none';
            document.getElementById('appealForm').reset();
            document.getElementById('filePreview').classList.remove('show');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('appealModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // File upload handling
        const fileInput = document.getElementById('supporting_document');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const filePreview = document.getElementById('filePreview');

        fileInput.addEventListener('change', function(e) {
            handleFile(e.target.files[0]);
        });

        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('drag-over');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFile(files[0]);
            }
        });

        function handleFile(file) {
            if (!file) return;

            // Validate file type
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
            const allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            const fileExtension = file.name.split('.').pop().toLowerCase();

            if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
                alert('Invalid file type. Only PDF, DOC, DOCX, JPG, and PNG files are allowed.');
                fileInput.value = '';
                return;
            }

            // Validate file size (5MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File size must not exceed 5MB.');
                fileInput.value = '';
                return;
            }

            // Show file preview
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatFileSize(file.size);
            filePreview.classList.add('show');

            // Update file icon based on type
            const fileIcon = filePreview.querySelector('.file-icon i');
            if (file.type.includes('pdf')) {
                fileIcon.className = 'fas fa-file-pdf';
            } else if (file.type.includes('word') || file.type.includes('document')) {
                fileIcon.className = 'fas fa-file-word';
            } else if (file.type.includes('image')) {
                fileIcon.className = 'fas fa-file-image';
            } else {
                fileIcon.className = 'fas fa-file-alt';
            }
        }

        function removeFile() {
            fileInput.value = '';
            filePreview.classList.remove('show');
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    </script>
</body>
</html>