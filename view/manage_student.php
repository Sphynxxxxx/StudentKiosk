<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session first
session_start();

// Include database configuration
require_once '../config/database.php';

// Include PHPMailer autoload 
require_once '../vendor/autoload.php'; 

// Include the real EmailService
require_once '../includes/EmailService.php';

// Create basic YearLevelHelper class if it doesn't exist
if (!class_exists('YearLevelHelper')) {
    class YearLevelHelper {
        public static function getYearLevelOptions() {
            return [
                ['value' => '1st', 'label' => '1st Year'],
                ['value' => '2nd', 'label' => '2nd Year'],
                ['value' => '3rd', 'label' => '3rd Year'],
                ['value' => '4th', 'label' => '4th Year'],
                ['value' => '5th', 'label' => '5th Year']
            ];
        }
    }
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST received: " . print_r($_POST, true));
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_student':
                    try {
                        // Begin transaction
                        $pdo->beginTransaction();
                        
                        // Generate username from first and last name
                        $username = strtolower($_POST['first_name'][0] . $_POST['last_name']);
                        $username = preg_replace('/[^a-z0-9]/', '', $username);
                        
                        // Check if username exists, add number if needed
                        $base_username = $username;
                        $counter = 1;
                        while (true) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                            $stmt->execute([$username]);
                            if ($stmt->fetchColumn() == 0) break;
                            $username = $base_username . $counter;
                            $counter++;
                        }
                        
                        // Store the plain password for email
                        $plain_password = $_POST['password'];
                        
                        // Add new user record
                        $stmt = $pdo->prepare("
                            INSERT INTO users (
                                username, email, password, first_name, last_name, role, student_id, status,
                                birth_date, gender, address, contact_number, emergency_contact, emergency_phone
                            ) VALUES (?, ?, ?, ?, ?, 'student', ?, 'active', ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
                        $stmt->execute([
                            $username,
                            $_POST['email'],
                            $hashed_password,
                            $_POST['first_name'],
                            $_POST['last_name'],
                            $_POST['student_id'],
                            $_POST['birth_date'] ?? null,
                            $_POST['gender'] ?? null,
                            $_POST['address'] ?? null,
                            $_POST['contact_number'] ?? null,
                            $_POST['emergency_contact'] ?? null,
                            $_POST['emergency_phone'] ?? null
                        ]);
                        
                        $user_id = $pdo->lastInsertId();
                        
                        // Get academic year from form or use active one
                        $academic_year_id = null;
                        if (isset($_POST['academic_year_id']) && !empty($_POST['academic_year_id'])) {
                            $academic_year_id = $_POST['academic_year_id'];
                        } else {
                            // Get the active academic year or use a default
                            $stmt = $pdo->prepare("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
                            $stmt->execute();
                            $academic_year_id = $stmt->fetchColumn();
                            
                            if (!$academic_year_id) {
                                // If no active academic year, get the most recent one
                                $stmt = $pdo->prepare("SELECT id FROM academic_years ORDER BY year_start DESC LIMIT 1");
                                $stmt->execute();
                                $academic_year_id = $stmt->fetchColumn();
                            }
                        }
                        
                        if (!$academic_year_id) {
                            throw new Exception('No academic year found. Please create an academic year first.');
                        }
                        
                        // Add student profile record - Updated to include academic_year_id
                        $stmt = $pdo->prepare("
                            INSERT INTO student_profiles (
                                user_id, year_level, program_id, section_id, admission_date, 
                                student_type, guardian_name, guardian_contact, academic_year_id
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $user_id,
                            $_POST['year_level'],
                            $_POST['program_id'],
                            !empty($_POST['section_id']) ? $_POST['section_id'] : null,
                            $_POST['admission_date'],
                            $_POST['student_type'],
                            $_POST['guardian_name'] ?? null,
                            $_POST['guardian_contact'] ?? null,
                            $academic_year_id
                        ]);
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        // Prepare student data for email
                        $studentData = [
                            'id' => $user_id,
                            'username' => $username,
                            'email' => $_POST['email'],
                            'first_name' => $_POST['first_name'],
                            'last_name' => $_POST['last_name'],
                            'student_id' => $_POST['student_id']
                        ];
                        
                        // Send welcome email
                        if (isset($_POST['send_email']) && $_POST['send_email'] == '1') {
                            try {
                                $emailService = new EmailService();
                                $emailResult = $emailService->sendStudentWelcomeEmail($studentData, $plain_password);
                                
                                if ($emailResult['success']) {
                                    $message = 'Student added successfully! Username: ' . $username . '. ' . $emailResult['message'];
                                } else {
                                    $message = 'Student added successfully! Username: ' . $username . '. However, email sending failed: ' . $emailResult['message'];
                                }
                            } catch (Exception $e) {
                                $message = 'Student added successfully! Username: ' . $username . '. However, email sending failed: ' . $e->getMessage();
                            }
                        } else {
                            $message = 'Student added successfully! Username: ' . $username;
                        }
                        
                        $message_type = 'success';
                        
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $message = 'Database Error: ' . $e->getMessage();
                        $message_type = 'error';
                        error_log("Database Error: " . $e->getMessage());
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = 'System Error: ' . $e->getMessage();
                        $message_type = 'error';
                        error_log("System Error: " . $e->getMessage());
                    }
                    break;
                    
                case 'update_student':
                    try {
                        // Begin transaction
                        $pdo->beginTransaction();
                        
                        // Update user information
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET first_name = ?, last_name = ?, email = ?, student_id = ?, status = ?,
                                birth_date = ?, gender = ?, address = ?, contact_number = ?, 
                                emergency_contact = ?, emergency_phone = ?
                            WHERE id = ? AND role = 'student'
                        ");
                        $stmt->execute([
                            $_POST['first_name'],
                            $_POST['last_name'],
                            $_POST['email'],
                            $_POST['student_id'],
                            $_POST['status'],
                            $_POST['birth_date'] ?? null,
                            $_POST['gender'] ?? null,
                            $_POST['address'] ?? null,
                            $_POST['contact_number'] ?? null,
                            $_POST['emergency_contact'] ?? null,
                            $_POST['emergency_phone'] ?? null,
                            $_POST['student_id_pk']
                        ]);
                        
                        // Update student profile information
                        $stmt = $pdo->prepare("
                            UPDATE student_profiles 
                            SET year_level = ?, program_id = ?, section_id = ?, student_type = ?,
                                guardian_name = ?, guardian_contact = ?, academic_year_id = ?
                            WHERE user_id = ?
                        ");
                        $stmt->execute([
                            $_POST['year_level'],
                            $_POST['program_id'],
                            !empty($_POST['section_id']) ? $_POST['section_id'] : null,
                            $_POST['student_type'],
                            $_POST['guardian_name'] ?? null,
                            $_POST['guardian_contact'] ?? null,
                            isset($_POST['academic_year_id']) ? $_POST['academic_year_id'] : null,
                            $_POST['student_id_pk']
                        ]);
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        $message = 'Student information updated successfully!';
                        $message_type = 'success';
                        
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $message = 'Database Error: ' . $e->getMessage();
                        $message_type = 'error';
                        error_log("Update student error: " . $e->getMessage());
                    }
                    break;
                    
                case 'delete_student':
                    // Delete student
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
                    $stmt->execute([$_POST['student_id_pk']]);
                    
                    $message = 'Student deleted successfully!';
                    $message_type = 'success';
                    break;
                    
                case 'reset_password':
                    // Generate a new password
                    $new_password = 'ISATU' . date('Y') . rand(100, 999);
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Get student data for email
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
                    $stmt->execute([$_POST['student_id_pk']]);
                    $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($studentData) {
                        // Update password
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'student'");
                        $stmt->execute([$hashed_password, $_POST['student_id_pk']]);
                        
                        // Send password reset email
                        if (isset($_POST['send_reset_email']) && $_POST['send_reset_email'] == '1') {
                            try {
                                $emailService = new EmailService();
                                $emailResult = $emailService->sendPasswordResetEmail($studentData, $new_password);
                                
                                if ($emailResult['success']) {
                                    $message = 'Password reset successfully! New password: ' . $new_password . '. ' . $emailResult['message'];
                                } else {
                                    $message = 'Password reset successfully! New password: ' . $new_password . '. However, email sending failed: ' . $emailResult['message'];
                                }
                            } catch (Exception $e) {
                                $message = 'Password reset successfully! New password: ' . $new_password . '. However, email sending failed: ' . $e->getMessage();
                            }
                        } else {
                            $message = 'Password reset successfully! New password: ' . $new_password;
                        }
                        
                        $message_type = 'success';
                    } else {
                        $message = 'Student not found!';
                        $message_type = 'error';
                    }
                    break;
                    
                case 'test_email':
                    // Test email configuration
                    try {
                        $emailService = new EmailService();
                        $testResult = $emailService->testEmail();
                        
                        if ($testResult) {
                            $message = 'Email configuration test successful! Check your inbox.';
                            $message_type = 'success';
                        } else {
                            $message = 'Email configuration test failed!';
                            $message_type = 'error';
                        }
                    } catch (Exception $e) {
                        $message = 'Email configuration test failed: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                    break;
                    
                default:
                    $message = 'Invalid action specified.';
                    $message_type = 'error';
                    break;
            }
        }
    } catch (PDOException $e) {
        $message = 'Database Error: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Database Error: " . $e->getMessage());
    } catch (Exception $e) {
        $message = 'System Error: ' . $e->getMessage();
        $message_type = 'error';
        error_log("System Error: " . $e->getMessage());
    }
}

// Get all students with their enrollment information
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.username, u.email, u.first_name, u.last_name, u.student_id, u.status, u.created_at,
            u.birth_date, u.gender, u.contact_number, u.address, u.emergency_contact, u.emergency_phone,
            sp.year_level, sp.program_id, sp.section_id, sp.admission_date, sp.student_type, 
            sp.guardian_name, sp.guardian_contact, sp.academic_year_id,
            p.program_code, p.program_name,
            s.section_name,
            d.name as department_name,
            ay.year_start, ay.year_end, ay.semester,
            COUNT(e.id) as total_enrollments,
            COUNT(CASE WHEN e.status = 'enrolled' THEN 1 END) as active_enrollments
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN academic_years ay ON sp.academic_year_id = ay.id
        LEFT JOIN enrollments e ON u.id = e.student_id
        WHERE u.role = 'student'
        GROUP BY u.id
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $students = [];
    if (empty($message)) {
        $message = 'Error loading student data: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get student statistics
$stats = ['total_students' => 0, 'active_students' => 0, 'enrolled_students' => 0, 'total_enrollments' => 0];

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $stats['total_students'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'");
    $stats['active_students'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE status = 'enrolled'");
    $stats['enrolled_students'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'enrolled'");
    $stats['total_enrollments'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Student stats query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin_dashboard.css" rel="stylesheet">
    <link href="../assets/css/manage_faculty.css" rel="stylesheet">
    <style>
        /* Additional modal styles to ensure visibility */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 8px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #007bff;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .email-option {
            flex-direction: row !important;
            align-items: flex-start;
        }
        
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon primary">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-card-header">
                            <div class="stat-icon success">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['active_students']; ?></div>
                        <div class="stat-label">Active Students</div>
                    </div>

                    <div class="stat-card warning">
                        <div class="stat-card-header">
                            <div class="stat-icon warning">
                                <i class="fas fa-book-reader"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['enrolled_students']; ?></div>
                        <div class="stat-label">Enrolled Students</div>
                    </div>

                    <div class="stat-card info">
                        <div class="stat-card-header">
                            <div class="stat-icon info">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_enrollments']; ?></div>
                        <div class="stat-label">Total Enrollments</div>
                    </div>
                </div>

                <!-- Page Actions -->
                <div class="page-actions">
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add New Student
                    </button>
                    <button class="btn btn-info" onclick="testEmail()">
                        <i class="fas fa-envelope-circle-check"></i> Test Email
                    </button>
                </div>

                <!-- Students Table -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i> Students
                        </h3>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Joined Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 2rem; color: #6b7280;">
                                            <i class="fas fa-graduation-cap" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                            <p>No students found. Click "Add New Student" to get started.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></strong></td>
                                            <td>
                                                <div class="user-info">
                                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                                    <small>@<?php echo htmlspecialchars($student['username']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $student['status']; ?>">
                                                    <?php echo ucfirst($student['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-icon btn-warning" onclick="editStudent(<?php echo $student['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn-icon btn-info" onclick="resetPassword(<?php echo $student['id']; ?>)" title="Reset Password">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button class="btn-icon btn-danger" onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div id="addStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Student</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" class="faculty-form">
                <input type="hidden" name="action" value="add_student">
                
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Personal Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_date">Birthday *</label>
                            <input type="date" id="birth_date" name="birth_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number" placeholder="+63 XXX XXX XXXX">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="2" placeholder="Complete address"></textarea>
                    </div>
                </div>

                <!-- Academic Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Academic Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="student_id">Student ID *</label>
                            <input type="text" id="student_id" name="student_id" required placeholder="e.g., 2025-001">
                        </div>
                        
                        <div class="form-group">
                            <label for="academic_year_id">Academic Year *</label>
                            <select id="academic_year_id" name="academic_year_id" required>
                                <option value="">Select Academic Year</option>
                                <?php
                                try {
                                    $stmt = $pdo->prepare("SELECT id, CONCAT(year_start, '-', year_end, ' (', semester, ' Semester)') as academic_year FROM academic_years ORDER BY is_active DESC, year_start DESC");
                                    $stmt->execute();
                                    $academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($academic_years as $ay) {
                                        echo "<option value=\"{$ay['id']}\">{$ay['academic_year']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=\"\" disabled>Error loading academic years</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="program_id">Program *</label>
                            <select id="program_id" name="program_id" required>
                                <option value="">Select Program</option>
                                <?php
                                try {
                                    $stmt = $pdo->prepare("SELECT p.id, p.program_code, p.program_name FROM programs p WHERE p.status = 'active' ORDER BY p.program_name");
                                    $stmt->execute();
                                    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($programs as $program) {
                                        echo "<option value=\"{$program['id']}\">{$program['program_code']} - {$program['program_name']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=\"\" disabled>Error loading programs</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="year_level">Year Level *</label>
                            <select id="year_level" name="year_level" required>
                                <option value="">Select Year Level</option>
                                <option value="1st">1st Year</option>
                                <option value="2nd">2nd Year</option>
                                <option value="3rd">3rd Year</option>
                                <option value="4th">4th Year</option>
                                <option value="5th">5th Year</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="section_id">Section</label>
                            <select id="section_id" name="section_id">
                                <option value="">Select Section</option>
                                <?php
                                try {
                                    $stmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE status = 'active' ORDER BY section_name");
                                    $stmt->execute();
                                    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($sections as $section) {
                                        echo "<option value=\"{$section['id']}\">{$section['section_name']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=\"\" disabled>Error loading sections</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="student_type">Student Type *</label>
                            <select id="student_type" name="student_type" required>
                                <option value="">Select Type</option>
                                <option value="regular">Regular</option>
                                <option value="irregular">Irregular</option>
                                <option value="transferee">Transferee</option>
                                <option value="returning">Returning</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="admission_date">Admission Date *</label>
                            <input type="date" id="admission_date" name="admission_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact Section -->
                <div class="form-section">
                    <h3 class="section-title">Emergency Contact</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="guardian_name">Guardian/Parent Name</label>
                            <input type="text" id="guardian_name" name="guardian_name">
                        </div>
                        
                        <div class="form-group">
                            <label for="guardian_contact">Guardian Contact Number</label>
                            <input type="tel" id="guardian_contact" name="guardian_contact" placeholder="+63 XXX XXX XXXX">
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact Name</label>
                            <input type="text" id="emergency_contact" name="emergency_contact">
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_phone">Emergency Contact Number</label>
                            <input type="tel" id="emergency_phone" name="emergency_phone" placeholder="+63 XXX XXX XXXX">
                        </div>
                    </div>
                </div>

                <!-- Account Settings Section -->
                <div class="form-section">
                    <h3 class="section-title">Account Settings</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required>
                            <small class="form-text">Student will use this to login to the system</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <!-- Email Option -->
                    <div class="form-group email-option">
                        <label class="checkbox-label">
                            <input type="checkbox" id="send_email" name="send_email" value="1" checked>
                            Send welcome email with login credentials
                        </label>
                        <small class="form-text">The student will receive an email with their username and password.</small>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add Student
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Student Information</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" class="faculty-form" id="editStudentForm">
                <input type="hidden" name="action" value="update_student">
                <input type="hidden" name="student_id_pk" id="edit_student_id">
                
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Personal Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_first_name">First Name *</label>
                            <input type="text" id="edit_first_name" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_last_name">Last Name *</label>
                            <input type="text" id="edit_last_name" name="last_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_birth_date">Birthday *</label>
                            <input type="date" id="edit_birth_date" name="birth_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_gender">Gender *</label>
                            <select id="edit_gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_email">Email *</label>
                            <input type="email" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_contact_number">Contact Number</label>
                            <input type="tel" id="edit_contact_number" name="contact_number" placeholder="+63 XXX XXX XXXX">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="edit_address">Address</label>
                        <textarea id="edit_address" name="address" rows="2" placeholder="Complete address"></textarea>
                    </div>
                </div>

                <!-- Academic Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Academic Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_student_id">Student ID *</label>
                            <input type="text" id="edit_student_id_field" name="student_id" required placeholder="e.g., 2025-001">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_academic_year_id">Academic Year *</label>
                            <select id="edit_academic_year_id" name="academic_year_id" required>
                                <option value="">Select Academic Year</option>
                                <?php
                                try {
                                    $stmt = $pdo->prepare("SELECT id, CONCAT(year_start, '-', year_end, ' (', semester, ' Semester)') as academic_year FROM academic_years ORDER BY is_active DESC, year_start DESC");
                                    $stmt->execute();
                                    $academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($academic_years as $ay) {
                                        echo "<option value=\"{$ay['id']}\">{$ay['academic_year']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=\"\" disabled>Error loading academic years</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_program_id">Program *</label>
                            <select id="edit_program_id" name="program_id" required>
                                <option value="">Select Program</option>
                                <?php
                                try {
                                    $stmt = $pdo->prepare("SELECT p.id, p.program_code, p.program_name FROM programs p WHERE p.status = 'active' ORDER BY p.program_name");
                                    $stmt->execute();
                                    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($programs as $program) {
                                        echo "<option value=\"{$program['id']}\">{$program['program_code']} - {$program['program_name']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=\"\" disabled>Error loading programs</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_year_level">Year Level *</label>
                            <select id="edit_year_level" name="year_level" required>
                                <option value="">Select Year Level</option>
                                <option value="1st">1st Year</option>
                                <option value="2nd">2nd Year</option>
                                <option value="3rd">3rd Year</option>
                                <option value="4th">4th Year</option>
                                <option value="5th">5th Year</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_section_id">Section</label>
                            <select id="edit_section_id" name="section_id">
                                <option value="">Select Section</option>
                                <?php
                                try {
                                    $stmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE status = 'active' ORDER BY section_name");
                                    $stmt->execute();
                                    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($sections as $section) {
                                        echo "<option value=\"{$section['id']}\">{$section['section_name']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=\"\" disabled>Error loading sections</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_student_type">Student Type *</label>
                            <select id="edit_student_type" name="student_type" required>
                                <option value="">Select Type</option>
                                <option value="regular">Regular</option>
                                <option value="irregular">Irregular</option>
                                <option value="transferee">Transferee</option>
                                <option value="returning">Returning</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_status">Status *</label>
                            <select id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact Section -->
                <div class="form-section">
                    <h3 class="section-title">Emergency Contact</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_guardian_name">Guardian/Parent Name</label>
                            <input type="text" id="edit_guardian_name" name="guardian_name">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_guardian_contact">Guardian Contact Number</label>
                            <input type="tel" id="edit_guardian_contact" name="guardian_contact" placeholder="+63 XXX XXX XXXX">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_emergency_contact">Emergency Contact Name</label>
                            <input type="text" id="edit_emergency_contact" name="emergency_contact">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_emergency_phone">Emergency Contact Number</label>
                            <input type="tel" id="edit_emergency_phone" name="emergency_phone" placeholder="+63 XXX XXX XXXX">
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Student
                    </button>
                </div>
            </form>
        </div>
    </div>
                                

    <script>
    // Pass PHP data to JavaScript
    const allStudentData = <?php echo json_encode($students, JSON_HEX_QUOT | JSON_HEX_APOS); ?>;

    // Debug function to test button
    function testButton() {
        console.log('Button clicked!');
        alert('Button is working!');
    }

    // Modal functions
    function openAddModal() {
        console.log('Opening modal...');
        const modal = document.getElementById('addStudentModal');
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            console.log('Modal opened successfully');
        } else {
            console.error('Modal element not found');
        }
    }

    function closeAddModal() {
        const modal = document.getElementById('addStudentModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            // Reset form
            const form = modal.querySelector('form');
            if (form) form.reset();
        }
    }

    function closeEditModal() {
        const modal = document.getElementById('editStudentModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            // Reset form
            const form = modal.querySelector('form');
            if (form) form.reset();
        }
    }

    function editStudent(studentId) {
        console.log('=== EDIT STUDENT DEBUG START ===');
        console.log('editStudent called with ID:', studentId);
        
        // Check if modal exists
        const modal = document.getElementById('editStudentModal');
        console.log('Edit modal element found:', !!modal);
        if (!modal) {
            alert('Edit modal not found in DOM. Please refresh the page.');
            return;
        }
        
        // Check student data
        console.log('allStudentData available:', !!allStudentData);
        console.log('allStudentData is array:', Array.isArray(allStudentData));
        console.log('allStudentData length:', allStudentData ? allStudentData.length : 'N/A');
        
        if (!allStudentData || !Array.isArray(allStudentData)) {
            console.error('Student data not available');
            alert('Student data not available. Please refresh the page.');
            return;
        }
        
        const student = allStudentData.find(s => s.id == studentId);
        console.log('Student found:', !!student);
        console.log('Student data:', student);
        
        if (!student) {
            console.error('Student not found:', studentId);
            alert('Student not found. Please refresh the page.');
            return;
        }
        
        try {
            // Check if form elements exist before populating
            const formElements = [
                'edit_student_id', 'edit_first_name', 'edit_last_name', 'edit_birth_date',
                'edit_gender', 'edit_email', 'edit_contact_number', 'edit_address',
                'edit_student_id_field', 'edit_academic_year_id', 'edit_program_id', 'edit_year_level', 
                'edit_section_id', 'edit_student_type', 'edit_status',
                'edit_guardian_name', 'edit_guardian_contact', 'edit_emergency_contact', 'edit_emergency_phone'
            ];
            
            let missingElements = [];
            formElements.forEach(elementId => {
                const element = document.getElementById(elementId);
                if (!element) {
                    missingElements.push(elementId);
                }
            });
            
            if (missingElements.length > 0) {
                console.error('Missing form elements:', missingElements);
                alert('Some form elements are missing: ' + missingElements.join(', '));
                return;
            }
            
            console.log('All form elements found, populating...');
            
            // Populate the edit form with student data
            document.getElementById('edit_student_id').value = student.id || '';
            document.getElementById('edit_first_name').value = student.first_name || '';
            document.getElementById('edit_last_name').value = student.last_name || '';
            document.getElementById('edit_birth_date').value = student.birth_date || '';
            document.getElementById('edit_gender').value = student.gender || '';
            document.getElementById('edit_email').value = student.email || '';
            document.getElementById('edit_contact_number').value = student.contact_number || '';
            document.getElementById('edit_address').value = student.address || '';
            document.getElementById('edit_student_id_field').value = student.student_id || '';
            document.getElementById('edit_academic_year_id').value = student.academic_year_id || '';
            document.getElementById('edit_program_id').value = student.program_id || '';
            document.getElementById('edit_year_level').value = student.year_level || '';
            document.getElementById('edit_section_id').value = student.section_id || '';
            document.getElementById('edit_student_type').value = student.student_type || '';
            document.getElementById('edit_status').value = student.status || 'active';
            document.getElementById('edit_guardian_name').value = student.guardian_name || '';
            document.getElementById('edit_guardian_contact').value = student.guardian_contact || '';
            document.getElementById('edit_emergency_contact').value = student.emergency_contact || '';
            document.getElementById('edit_emergency_phone').value = student.emergency_phone || '';
            
            console.log('Form populated successfully');
            
            // Show the edit modal
            console.log('Showing modal...');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            console.log('Modal display style set to:', modal.style.display);
            console.log('Modal visibility:', getComputedStyle(modal).visibility);
            console.log('Modal z-index:', getComputedStyle(modal).zIndex);
            
            // Force a reflow
            modal.offsetHeight;
            
            console.log('Edit modal opened successfully');
            console.log('=== EDIT STUDENT DEBUG END ===');
            
        } catch (error) {
            console.error('Error in editStudent function:', error);
            console.error('Error stack:', error.stack);
            alert('Error opening edit form: ' + error.message);
        }
    }

    function deleteStudent(studentId, studentName) {
        if (!confirm(`Are you sure you want to delete ${studentName}?\n\nThis action cannot be undone.`)) {
            return;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_student">
            <input type="hidden" name="student_id_pk" value="${studentId}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }

    function resetPassword(studentId) {
        if (!confirm('Reset this student\'s password?\n\nA new password will be generated.')) {
            return;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = `
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="student_id_pk" value="${studentId}">
            <input type="hidden" name="send_reset_email" value="1">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }

    function testEmail() {
        if (!confirm('This will send a test email to verify your email configuration. Continue?')) {
            return;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = '<input type="hidden" name="action" value="test_email">';
        
        document.body.appendChild(form);
        form.submit();
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addStudentModal');
        const editModal = document.getElementById('editStudentModal');
        
        if (event.target === addModal) {
            closeAddModal();
        } else if (event.target === editModal) {
            closeEditModal();
        }
    }

    // Debug function to test edit modal manually
    function testEditModal() {
        console.log('=== TESTING EDIT MODAL ===');
        const modal = document.getElementById('editStudentModal');
        console.log('Modal found:', !!modal);
        
        if (modal) {
            console.log('Modal current display:', modal.style.display);
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            console.log('Modal should now be visible');
            
            // Test form elements
            const testElements = ['edit_first_name', 'edit_last_name', 'edit_email'];
            testElements.forEach(id => {
                const el = document.getElementById(id);
                console.log(`Element ${id}:`, !!el);
                if (el) {
                    el.value = 'TEST';
                }
            });
        }
    }

    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        console.log('=== DOM LOADED ===');
        
        // Test both modals exist
        const addModal = document.getElementById('addStudentModal');
        const editModal = document.getElementById('editStudentModal');
        
        console.log('Add modal found:', !!addModal);
        console.log('Edit modal found:', !!editModal);
        
        if (!editModal) {
            console.error('CRITICAL: Edit modal not found in DOM!');
        } else {
            console.log('Edit modal classes:', editModal.className);
            console.log('Edit modal style:', editModal.style.cssText);
        }
        
        // Test student data
        console.log('Student data available:', !!allStudentData);
        console.log('Student data length:', allStudentData ? allStudentData.length : 0);
        
        // Add a global test function
        window.testEditModal = testEditModal;
        console.log('Added testEditModal to window object - you can call it from console');
        
        // Password confirmation validation for add form
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        
        if (passwordField && confirmPasswordField) {
            function validatePasswords() {
                if (passwordField.value !== confirmPasswordField.value) {
                    confirmPasswordField.setCustomValidity('Passwords do not match');
                } else {
                    confirmPasswordField.setCustomValidity('');
                }
            }
            
            passwordField.addEventListener('input', validatePasswords);
            confirmPasswordField.addEventListener('input', validatePasswords);
        }
        
        // Form submission validation
        const addForm = document.querySelector('#addStudentModal form');
        const editForm = document.querySelector('#editStudentModal form');
        
        console.log('Add form found:', !!addForm);
        console.log('Edit form found:', !!editForm);
        
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                const requiredFields = addForm.querySelectorAll('input[required], select[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#ddd';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
            });
        }
        
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                const requiredFields = editForm.querySelectorAll('input[required], select[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#ddd';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
            });
        }
        
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });
        
        // Test if buttons are accessible
        const addButton = document.querySelector('button[onclick="openAddModal()"]');
        const editButtons = document.querySelectorAll('button[onclick*="editStudent"]');
        
        console.log('Add button found:', !!addButton);
        console.log('Edit buttons found:', editButtons.length);
        
        if (editButtons.length === 0) {
            console.warn('No edit buttons found - this might indicate students data is empty');
        }
        
        console.log('=== DOM SETUP COMPLETE ===');
    });

    // ESC key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
        }
    });
    </script>
    
</body>
</html>