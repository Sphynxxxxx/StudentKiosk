<?php

// Start the session first
session_start();



// Include database configuration
require_once '../config/database.php';

// Include EmailService and StudentService
require_once '../includes/EmailService.php';
require_once '../includes/StudentService.php';
require_once '../vendor/autoload.php';


// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            $studentService = new StudentService($pdo);
            $emailService = new EmailService();
            
            switch ($_POST['action']) {
                case 'approve_enrollment':
                    try {
                        $studentId = $_POST['student_id'];
                        $enrollmentIds = isset($_POST['enrollment_ids']) ? $_POST['enrollment_ids'] : [];
                        
                        if (empty($enrollmentIds)) {
                            $message = 'No enrollments selected for approval.';
                            $message_type = 'error';
                            break;
                        }
                        
                        $pdo->beginTransaction();
                        
                        // Update enrollment status to approved
                        $placeholders = str_repeat('?,', count($enrollmentIds) - 1) . '?';
                        $stmt = $pdo->prepare("
                            UPDATE enrollments 
                            SET status = 'enrolled', updated_at = NOW() 
                            WHERE id IN ($placeholders) AND student_id = ?
                        ");
                        $params = array_merge($enrollmentIds, [$studentId]);
                        $stmt->execute($params);
                        
                        // Get student information for email
                        $stmt = $pdo->prepare("
                            SELECT u.*, sp.year_level, p.program_code, p.program_name, s.section_name
                            FROM users u
                            LEFT JOIN student_profiles sp ON u.id = sp.user_id
                            LEFT JOIN programs p ON sp.program_id = p.id
                            LEFT JOIN sections s ON sp.section_id = s.id
                            WHERE u.id = ? AND u.role = 'student'
                        ");
                        $stmt->execute([$studentId]);
                        $student = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Get approved subjects
                        $stmt = $pdo->prepare("
                            SELECT s.course_code, s.subject_name as course_name, s.credits,
                                   cs.section_name, cs.schedule, cs.room
                            FROM enrollments e
                            INNER JOIN class_sections cs ON e.class_section_id = cs.id
                            INNER JOIN subjects s ON cs.subject_id = s.id
                            WHERE e.id IN ($placeholders)
                            ORDER BY s.course_code
                        ");
                        $stmt->execute($enrollmentIds);
                        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Get current academic year
                        $stmt = $pdo->prepare("
                            SELECT year_start, year_end, semester 
                            FROM academic_years 
                            WHERE is_active = 1 
                            LIMIT 1
                        ");
                        $stmt->execute();
                        $currentAY = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $academicYear = $currentAY ? 
                            $currentAY['year_start'] . '-' . $currentAY['year_end'] : 
                            date('Y') . '-' . (date('Y') + 1);
                        $semester = $currentAY ? 
                            strtoupper($currentAY['semester']) . ' SEMESTER' : 
                            'FIRST SEMESTER';
                        
                        // Send enrollment confirmation email (simplified version)
                        $emailResult = sendApprovalEmail($emailService, $student, $subjects, $academicYear, $semester);
                        
                        // Log the approval
                        $stmt = $pdo->prepare("
                            INSERT INTO enrollment_logs (student_id, action_type, confirmed_by, action_date, notes)
                            VALUES (?, 'enrollment_approved', ?, NOW(), ?)
                        ");
                        $stmt->execute([$studentId, $_SESSION['student_id'], 'Approved ' . count($enrollmentIds) . ' enrollments']);
                        
                        $pdo->commit();
                        
                        if ($emailResult['success']) {
                            $message = 'Enrollment approved successfully! Confirmation email sent to ' . $student['email'];
                        } else {
                            $message = 'Enrollment approved successfully! However, email sending failed: ' . $emailResult['message'];
                        }
                        $message_type = 'success';
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = 'Error approving enrollment: ' . $e->getMessage();
                        $message_type = 'error';
                        error_log("Enrollment approval error: " . $e->getMessage());
                    }
                    break;
                    
                case 'reject_enrollment':
                    try {
                        $studentId = $_POST['student_id'];
                        // Fix: Handle both single and array enrollment IDs
                        $enrollmentIds = [];
                        if (isset($_POST['enrollment_ids']) && is_array($_POST['enrollment_ids'])) {
                            $enrollmentIds = $_POST['enrollment_ids'];
                        } else if (isset($_POST['enrollment_ids'])) {
                            // If it's a comma-separated string, split it
                            $enrollmentIds = explode(',', $_POST['enrollment_ids']);
                        }
                        
                        $rejectionReason = $_POST['rejection_reason'] ?? 'No reason provided';
                        
                        if (empty($enrollmentIds)) {
                            $message = 'No enrollments selected for rejection.';
                            $message_type = 'error';
                            break;
                        }
                        
                        $pdo->beginTransaction();
                        
                        // Update enrollment status to rejected
                        $placeholders = str_repeat('?,', count($enrollmentIds) - 1) . '?';
                        $stmt = $pdo->prepare("
                            UPDATE enrollments 
                            SET status = 'rejected', updated_at = NOW() 
                            WHERE id IN ($placeholders) AND student_id = ?
                        ");
                        $params = array_merge($enrollmentIds, [$studentId]);
                        $stmt->execute($params);
                        
                        // Get student information
                        $stmt = $pdo->prepare("
                            SELECT u.*, sp.year_level, p.program_code, p.program_name
                            FROM users u
                            LEFT JOIN student_profiles sp ON u.id = sp.user_id
                            LEFT JOIN programs p ON sp.program_id = p.id
                            WHERE u.id = ? AND u.role = 'student'
                        ");
                        $stmt->execute([$studentId]);
                        $student = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Get rejected subjects
                        $stmt = $pdo->prepare("
                            SELECT s.course_code, s.subject_name, cs.section_name
                            FROM enrollments e
                            INNER JOIN class_sections cs ON e.class_section_id = cs.id
                            INNER JOIN subjects s ON cs.subject_id = s.id
                            WHERE e.id IN ($placeholders)
                            ORDER BY s.course_code
                        ");
                        $stmt->execute($enrollmentIds);
                        $rejectedSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Send rejection email - Fix: Remove $this->
                        $emailResult = sendRejectionEmail($emailService, $student, $rejectedSubjects, $rejectionReason);
                        
                        // Log the rejection
                        $stmt = $pdo->prepare("
                            INSERT INTO enrollment_logs (student_id, action_type, confirmed_by, action_date, notes)
                            VALUES (?, 'enrollment_rejected', ?, NOW(), ?)
                        ");
                        $stmt->execute([$studentId, $_SESSION['user_id'], 'Rejection reason: ' . $rejectionReason]);
                        
                        $pdo->commit();
                        
                        if ($emailResult['success']) {
                            $message = 'Enrollment rejected successfully! Notification email sent to ' . $student['email'];
                        } else {
                            $message = 'Enrollment rejected successfully! However, email sending failed: ' . $emailResult['message'];
                        }
                        $message_type = 'success';
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = 'Error rejecting enrollment: ' . $e->getMessage();
                        $message_type = 'error';
                        error_log("Enrollment rejection error: " . $e->getMessage());
                    }
                    break;
                    
                case 'bulk_approve':
                    try {
                        $selectedStudents = $_POST['selected_students'] ?? [];
                        
                        if (empty($selectedStudents)) {
                            $message = 'No students selected for bulk approval.';
                            $message_type = 'error';
                            break;
                        }
                        
                        $approvedCount = 0;
                        $failedCount = 0;
                        
                        foreach ($selectedStudents as $studentId) {
                            try {
                                $pdo->beginTransaction();
                                
                                // Get pending enrollments for this student
                                $stmt = $pdo->prepare("
                                    SELECT id FROM enrollments 
                                    WHERE student_id = ? AND status = 'pending'
                                ");
                                $stmt->execute([$studentId]);
                                $pendingEnrollments = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                if (!empty($pendingEnrollments)) {
                                    // Approve all pending enrollments
                                    $placeholders = str_repeat('?,', count($pendingEnrollments) - 1) . '?';
                                    $stmt = $pdo->prepare("
                                        UPDATE enrollments 
                                        SET status = 'enrolled', updated_at = NOW() 
                                        WHERE id IN ($placeholders)
                                    ");
                                    $stmt->execute($pendingEnrollments);
                                    
                                    // Log the approval
                                    $stmt = $pdo->prepare("
                                        INSERT INTO enrollment_logs (student_id, action_type, confirmed_by, action_date, notes)
                                        VALUES (?, 'bulk_enrollment_approved', ?, NOW(), ?)
                                    ");
                                    $stmt->execute([$studentId, $_SESSION['student_id'], 'Bulk approved ' . count($pendingEnrollments) . ' enrollments']);
                                    
                                    $approvedCount++;
                                }
                                
                                $pdo->commit();
                                
                            } catch (Exception $e) {
                                $pdo->rollBack();
                                $failedCount++;
                                error_log("Bulk approval failed for student $studentId: " . $e->getMessage());
                            }
                        }
                        
                        $message = "Bulk approval completed: $approvedCount approved, $failedCount failed.";
                        $message_type = $failedCount > 0 ? 'warning' : 'success';
                        
                    } catch (Exception $e) {
                        $message = 'Error in bulk approval: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                    break;
                    
                default:
                    $message = 'Invalid action specified.';
                    $message_type = 'error';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'System Error: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Pre-enrollment management error: " . $e->getMessage());
    }
}

// Function to send approval email
function sendApprovalEmail($emailService, $student, $subjects, $academicYear, $semester) {
    try {
        // Calculate total credits
        $totalCredits = 0;
        foreach ($subjects as $subject) {
            $totalCredits += (int)$subject['credits'];
        }
        
        $emailContent = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <p>Hi {$student['first_name']} {$student['last_name']},</p>
            
            <p>Your <strong>enrollment</strong> was confirmed by ISAT University Registrar. Please see the details below:</p>
            
            <p><strong>Course:</strong> **{$student['program_name']} " . preg_replace('/[^0-9]/', '', $student['year_level']) . " {$student['section_name']}**</p>

            
            <p><strong>Subjects Enrolled:</strong></p>
            
            <table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%; max-width: 600px; margin: 20px 0;'>
                <thead>
                    <tr style='background-color: #f5f5f5;'>
                        <th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Subject Name</th>
                        <th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Descriptive Title</th>
                        <th style='border: 1px solid #ddd; padding: 8px; text-align: center;'>Credit</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach ($subjects as $subject) {
            $emailContent .= "
                    <tr>
                        <td style='border: 1px solid #ddd; padding: 8px;'>{$subject['course_code']}</td>
                        <td style='border: 1px solid #ddd; padding: 8px;'>" . strtoupper($subject['course_name']) . "</td>
                        <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$subject['credits']}</td>
                    </tr>";
        }
        
        $emailContent .= "
                </tbody>
            </table>
            
            <p><strong>Total Credits:</strong> {$totalCredits}</p>
            
            <div style='margin-top: 30px;'>
                <p>Do not reply to this computer-generated email.</p>
                <p>Stay Safe.</p>
                <p><strong>ISAT University</strong></p>
            </div>
        </div>";
        
        $emailService->mail->clearAddresses();
        $emailService->mail->addAddress($student['email'], $student['first_name'] . ' ' . $student['last_name']);
        $emailService->mail->Subject = 'Enrollment S.Y. ' . $academicYear . ' ' . strtoupper($semester) . ' Confirmation';
        $emailService->mail->Body = $emailContent;
        $emailService->mail->AltBody = strip_tags($emailContent);
        
        $result = $emailService->mail->send();
        
        return [
            'success' => $result,
            'message' => $result ? 'Approval email sent successfully' : 'Failed to send approval email'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Email sending failed: ' . $e->getMessage()
        ];
    }
}

// Function to send rejection email
function sendRejectionEmail($emailService, $student, $rejectedSubjects, $reason) {
    try {
        $subjectsList = '';
        foreach ($rejectedSubjects as $subject) {
            $subjectsList .= "- {$subject['course_code']}: {$subject['subject_name']} (Section: {$subject['section_name']})\n";
        }
        
        $emailContent = "
        <h2>Enrollment Request - Action Required</h2>
        <p>Dear {$student['first_name']} {$student['last_name']},</p>
        
        <p>We regret to inform you that your enrollment request for the following subjects has been <strong>rejected</strong>:</p>
        
        <ul>";
        
        foreach ($rejectedSubjects as $subject) {
            $emailContent .= "<li><strong>{$subject['course_code']}</strong>: {$subject['subject_name']} (Section: {$subject['section_name']})</li>";
        }
        
        $emailContent .= "
        </ul>
        
        <div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <strong>Reason for rejection:</strong><br>
            {$reason}
        </div>
        
        <p><strong>What to do next:</strong></p>
        <ol>
            <li>Contact the registrar's office to discuss alternative options</li>
            <li>Review the course prerequisites and requirements</li>
            <li>Consider enrolling in alternative sections or subjects</li>
            <li>Resubmit your enrollment request after addressing the issues</li>
        </ol>
        
        <p>If you have any questions or need assistance, please contact:</p>
        <ul>
            <li><strong>Registrar's Office:</strong> registrar@isatu.edu.ph</li>
            <li><strong>Academic Advisor:</strong> advisor@isatu.edu.ph</li>
        </ul>
        
        <p>Best regards,<br>
        ISATU Registrar's Office</p>
        ";
        
        // Create a temporary EmailService instance to send custom content
        $emailService->mail->clearAddresses();
        $emailService->mail->addAddress($student['email'], $student['first_name'] . ' ' . $student['last_name']);
        $emailService->mail->Subject = 'Enrollment Request Update - Action Required';
        $emailService->mail->Body = $emailContent;
        $emailService->mail->AltBody = strip_tags($emailContent);
        
        $result = $emailService->mail->send();
        
        return [
            'success' => $result,
            'message' => $result ? 'Rejection email sent successfully' : 'Failed to send rejection email'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Email sending failed: ' . $e->getMessage()
        ];
    }
}

// Get pending enrollments for review
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id as student_id,
            u.first_name,
            u.last_name,
            u.email,
            u.student_id as student_number,
            sp.year_level,
            p.program_code,
            p.program_name,
            s.section_name,
            COUNT(e.id) as total_pending,
            GROUP_CONCAT(
                CONCAT(subj.course_code, ' - ', subj.subject_name, ' (', cs.section_name, ')')
                ORDER BY subj.course_code SEPARATOR '; '
            ) as subjects_list,
            GROUP_CONCAT(e.id ORDER BY subj.course_code) as enrollment_ids,
            MAX(e.enrollment_date) as latest_enrollment_date
        FROM users u
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        INNER JOIN enrollments e ON u.id = e.student_id
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN subjects subj ON cs.subject_id = subj.id
        WHERE u.role = 'student' 
        AND e.status = 'pending'
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.student_id, 
                 sp.year_level, p.program_code, p.program_name, s.section_name
        ORDER BY MAX(e.enrollment_date) DESC
    ");
    $stmt->execute();
    $pendingEnrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pendingEnrollments = [];
    if (empty($message)) {
        $message = 'Error loading pending enrollments: ' . $e->getMessage();
        $message_type = 'error';
    }
    error_log("Database error in pending enrollments query: " . $e->getMessage());
}

// Get enrollment statistics
$stats = [
    'total_pending' => 0,
    'total_approved' => 0,
    'total_rejected' => 0,
    'total_students_with_pending' => 0
];

try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as total_pending,
            COUNT(CASE WHEN status = 'enrolled' THEN 1 END) as total_approved,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as total_rejected,
            COUNT(DISTINCT CASE WHEN status = 'pending' THEN student_id END) as total_students_with_pending
        FROM enrollments
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (PDOException $e) {
    error_log("Stats query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pre-Enrollment - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin_dashboard.css" rel="stylesheet">
    <link href="../assets/css/manage_faculty.css" rel="stylesheet">
    <style>
        .enrollment-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .enrollment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .student-info {
            flex: 1;
        }
        
        .student-name {
            font-size: 1.2em;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .student-details {
            color: #6b7280;
            font-size: 0.9em;
        }
        
        .enrollment-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .subjects-list {
            background: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #3b82f6;
        }
        
        .subjects-list h4 {
            margin: 0 0 10px 0;
            color: #1f2937;
            font-size: 1em;
        }
        
        .subject-item {
            background: white;
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
            font-size: 0.9em;
        }
        
        .enrollment-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 0.85em;
            color: #6b7280;
        }
        
        .bulk-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            display: none;
        }
        
        .bulk-actions.active {
            display: block;
        }
        
        .rejection-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .rejection-modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .pending-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .checkbox-column {
            width: 40px;
            text-align: center;
        }
        
        .student-checkbox {
            transform: scale(1.2);
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
        
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
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
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'error'); ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Debug Information (remove in production) -->
                <?php if (isset($_GET['debug'])): ?>
                    <div class="alert alert-info">
                        <strong>Debug Info:</strong><br>
                        Pending Enrollments Count: <?php echo count($pendingEnrollments); ?><br>
                        Session User ID: <?php echo $_SESSION['user_id'] ?? 'Not set'; ?><br>
                        Session Role: <?php echo $_SESSION['role'] ?? 'Not set'; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card warning">
                        <div class="stat-card-header">
                            <div class="stat-icon warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_pending']; ?></div>
                        <div class="stat-label">Pending Enrollments</div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-card-header">
                            <div class="stat-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_approved']; ?></div>
                        <div class="stat-label">Approved Enrollments</div>
                    </div>

                    <div class="stat-card danger">
                        <div class="stat-card-header">
                            <div class="stat-icon danger">
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_rejected']; ?></div>
                        <div class="stat-label">Rejected Enrollments</div>
                    </div>

                    <div class="stat-card info">
                        <div class="stat-card-header">
                            <div class="stat-icon info">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_students_with_pending']; ?></div>
                        <div class="stat-label">Students Waiting</div>
                    </div>
                </div>

                <!-- Page Actions -->
                <div class="page-actions">
                    <button class="btn btn-success" onclick="selectAllPending()" id="selectAllBtn">
                        <i class="fas fa-check-square"></i> Select All
                    </button>
                    <button class="btn btn-outline" onclick="clearSelection()" id="clearSelectionBtn">
                        <i class="fas fa-times"></i> Clear Selection
                    </button>
                    <!--<button class="btn btn-info" onclick="refreshPage()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>-->
                </div>

                <!-- Bulk Actions -->
                <!--div class="bulk-actions" id="bulkActions">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-info-circle"></i> <span id="selectedCount">0</span> students selected</span>
                        <div>
                            <button class="btn btn-success" onclick="bulkApprove()">
                                <i class="fas fa-check"></i> Approve Selected
                            </button>
                        </div>
                    </div>
                </div>-->

                <!-- Pending Enrollments -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-clipboard-list"></i> Pending Pre-Enrollments
                        </h3>
                    </div>

                    <div class="card-content">
                        <?php if (empty($pendingEnrollments)): ?>
                            <div style="text-align: center; padding: 3rem; color: #6b7280;">
                                <i class="fas fa-clipboard-check" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <h3>No Pending Enrollments</h3>
                                <p>All student pre-enrollment requests have been processed.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingEnrollments as $enrollment): ?>
                                <div class="enrollment-card">
                                    <div class="enrollment-header">
                                        <div class="checkbox-column">
                                            <input type="checkbox" class="student-checkbox" 
                                                   value="<?php echo $enrollment['student_id']; ?>" 
                                                   onchange="updateBulkActions()">
                                        </div>
                                        <div class="student-info">
                                            <div class="student-name">
                                                <?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?>
                                                <span class="pending-badge">
                                                    <?php echo $enrollment['total_pending']; ?> pending
                                                </span>
                                            </div>
                                            <div class="student-details">
                                                <strong>ID:</strong> <?php echo htmlspecialchars($enrollment['student_number']); ?> |
                                                <strong>Program:</strong> <?php echo htmlspecialchars($enrollment['program_name'] ?? 'N/A'); ?> |
                                                <strong>Year:</strong> <?php echo htmlspecialchars($enrollment['year_level'] ?? 'N/A'); ?> |
                                                <strong>Section:</strong> <?php echo htmlspecialchars($enrollment['section_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                        <div class="enrollment-actions">
                                            <button class="btn btn-success btn-sm" 
                                                    onclick="approveEnrollment(<?php echo $enrollment['student_id']; ?>, '<?php echo $enrollment['enrollment_ids']; ?>')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="openRejectionModal(<?php echo $enrollment['student_id']; ?>, '<?php echo $enrollment['enrollment_ids']; ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="subjects-list">
                                        <h4><i class="fas fa-book"></i> Requested Subjects:</h4>
                                        <?php 
                                        $subjects = explode('; ', $enrollment['subjects_list']);
                                        foreach ($subjects as $subject): 
                                        ?>
                                            <div class="subject-item"><?php echo htmlspecialchars($subject); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="enrollment-meta">
                                        <span><i class="fas fa-calendar"></i> Submitted: <?php echo date('M d, Y g:i A', strtotime($enrollment['latest_enrollment_date'])); ?></span>
                                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($enrollment['email']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="rejection-modal">
        <div class="rejection-modal-content">
            <div class="modal-header">
                <h3>Reject Enrollment Request</h3>
                <span class="close" onclick="closeRejectionModal()">&times;</span>
            </div>
            <form id="rejectionForm" method="POST">
                <input type="hidden" name="action" value="reject_enrollment">
                <input type="hidden" name="student_id" id="rejection_student_id">
                <input type="hidden" name="enrollment_ids" id="rejection_enrollment_ids">
                
                <div class="form-group">
                    <label for="rejection_reason">Reason for Rejection *</label>
                    <textarea name="rejection_reason" id="rejection_reason" rows="4" 
                              placeholder="Please provide a clear reason for rejecting this enrollment request..." 
                              required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    <small class="form-text">This reason will be included in the notification email sent to the student.</small>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone. The student will be notified via email about the rejection.
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeRejectionModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban"></i> Reject Enrollment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Processing...</p>
        </div>
    </div>

    <script>
        // Track selected students
        let selectedStudents = new Set();

        // Update bulk actions visibility and count
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            selectedStudents.clear();
            
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    selectedStudents.add(checkbox.value);
                }
            });
            
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedStudents.size > 0) {
                bulkActions.classList.add('active');
                selectedCount.textContent = selectedStudents.size;
            } else {
                bulkActions.classList.remove('active');
            }
            
            // Update select all button text
            const selectAllBtn = document.getElementById('selectAllBtn');
            const totalCheckboxes = checkboxes.length;
            const checkedCheckboxes = selectedStudents.size;
            
            if (checkedCheckboxes === totalCheckboxes && totalCheckboxes > 0) {
                selectAllBtn.innerHTML = '<i class="fas fa-minus-square"></i> Deselect All';
                selectAllBtn.onclick = clearSelection;
            } else {
                selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
                selectAllBtn.onclick = selectAllPending;
            }
        }

        // Select all pending enrollments
        function selectAllPending() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateBulkActions();
        }

        // Clear all selections
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkActions();
        }

        // Approve individual enrollment
        function approveEnrollment(studentId, enrollmentIds) {
            if (!confirm('Are you sure you want to approve this enrollment? The student will be notified via email.')) {
                return;
            }
            
            showLoading();
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            // Add form fields
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'approve_enrollment';
            form.appendChild(actionInput);
            
            const studentIdInput = document.createElement('input');
            studentIdInput.type = 'hidden';
            studentIdInput.name = 'student_id';
            studentIdInput.value = studentId;
            form.appendChild(studentIdInput);
            
            // Add enrollment IDs
            const enrollmentIdArray = enrollmentIds.split(',');
            enrollmentIdArray.forEach(id => {
                const enrollmentInput = document.createElement('input');
                enrollmentInput.type = 'hidden';
                enrollmentInput.name = 'enrollment_ids[]';
                enrollmentInput.value = id.trim();
                form.appendChild(enrollmentInput);
            });
            
            document.body.appendChild(form);
            form.submit();
        }

        // Open rejection modal
        function openRejectionModal(studentId, enrollmentIds) {
            document.getElementById('rejection_student_id').value = studentId;
            document.getElementById('rejection_enrollment_ids').value = enrollmentIds;
            document.getElementById('rejection_reason').value = '';
            document.getElementById('rejectionModal').style.display = 'block';
        }

        // Close rejection modal
        function closeRejectionModal() {
            document.getElementById('rejectionModal').style.display = 'none';
        }

        // Bulk approve selected students
        function bulkApprove() {
            if (selectedStudents.size === 0) {
                alert('Please select at least one student to approve.');
                return;
            }
            
            const message = `Are you sure you want to approve enrollment for ${selectedStudents.size} student(s)? ` +
                          'This will approve all pending enrollments for the selected students and send confirmation emails.';
            
            if (!confirm(message)) {
                return;
            }
            
            showLoading();
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_approve';
            form.appendChild(actionInput);
            
            // Add selected student IDs
            selectedStudents.forEach(studentId => {
                const studentInput = document.createElement('input');
                studentInput.type = 'hidden';
                studentInput.name = 'selected_students[]';
                studentInput.value = studentId;
                form.appendChild(studentInput);
            });
            
            document.body.appendChild(form);
            form.submit();
        }

        // Show loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        // Hide loading overlay
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // Refresh page
        function refreshPage() {
            window.location.reload();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('rejectionModal');
            if (event.target === modal) {
                closeRejectionModal();
            }
        }

        // Handle rejection form submission
        document.getElementById('rejectionForm').addEventListener('submit', function(e) {
            const reason = document.getElementById('rejection_reason').value.trim();
            if (reason.length < 10) {
                e.preventDefault();
                alert('Please provide a more detailed reason for rejection (at least 10 characters).');
                return false;
            }
            
            showLoading();
            return true;
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
            
            // Hide loading on page load
            hideLoading();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+A for select all
            if (e.ctrlKey && e.key === 'a' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                selectAllPending();
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                closeRejectionModal();
            }
            
            // F5 or Ctrl+R for refresh
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                e.preventDefault();
                refreshPage();
            }
        });

        // Add confirmation before leaving page if there are unsaved selections
        window.addEventListener('beforeunload', function(e) {
            if (selectedStudents.size > 0) {
                const message = 'You have selected students that haven\'t been processed. Are you sure you want to leave?';
                e.returnValue = message;
                return message;
            }
        });

        // Auto-refresh every 5 minutes to check for new enrollments
        setInterval(function() {
            // Only auto-refresh if no students are selected to avoid losing user input
            if (selectedStudents.size === 0) {
                const currentTime = new Date().getTime();
                const lastActivity = localStorage.getItem('lastActivity') || currentTime;
                
                // Only refresh if user has been inactive for more than 2 minutes
                if (currentTime - lastActivity > 120000) {
                    window.location.reload();
                }
            }
        }, 300000); // 5 minutes

        // Track user activity
        document.addEventListener('click', function() {
            localStorage.setItem('lastActivity', new Date().getTime());
        });

        document.addEventListener('keypress', function() {
            localStorage.setItem('lastActivity', new Date().getTime());
        });

    </script>

    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .loading-spinner i {
            font-size: 2rem;
            color: #3b82f6;
            margin-bottom: 1rem;
        }

        .loading-spinner p {
            margin: 0;
            color: #6b7280;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-header h3 {
            margin: 0;
            color: #1f2937;
        }

        .close {
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            color: #6b7280;
            background: none;
            border: none;
        }

        .close:hover {
            color: #1f2937;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .d-flex {
            display: flex;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .align-items-center {
            align-items: center;
        }

        /* Responsive improvements */
        @media (max-width: 768px) {
            .enrollment-header {
                flex-direction: column;
                align-items: stretch;
            }

            .enrollment-actions {
                margin-top: 1rem;
                justify-content: center;
            }

            .student-details {
                font-size: 0.8rem;
            }

            .rejection-modal-content {
                margin: 10% auto;
                width: 95%;
            }

            .modal-actions {
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .enrollment-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        /* Accessibility improvements */
        .btn:focus,
        .student-checkbox:focus {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }

        .alert {
            transition: opacity 0.3s ease;
        }

        /* Print styles */
        @media print {
            .page-actions,
            .bulk-actions,
            .enrollment-actions,
            .sidebar,
            .header {
                display: none !important;
            }

            .enrollment-card {
                break-inside: avoid;
                margin-bottom: 1rem;
            }
        }
    </style>
</body>
</html>