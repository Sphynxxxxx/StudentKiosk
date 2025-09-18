<?php
// Include database configuration
require_once '../config/database.php';

// Include PHPMailer autoload (adjust path based on your installation)
require_once '../vendor/autoload.php'; // If using Composer
// OR if using manual installation:
// require_once '../includes/phpmailer/src/PHPMailer.php';
// require_once '../includes/phpmailer/src/SMTP.php';
// require_once '../includes/phpmailer/src/Exception.php';

// Include Email Service
require_once '../includes/StudentService.php';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                        
                        // Add student profile record
                        $stmt = $pdo->prepare("
                            INSERT INTO student_profiles (
                                user_id, year_level, program_id, section_id, admission_date, 
                                student_type, guardian_name, guardian_contact
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $user_id,
                            $_POST['year_level'],
                            $_POST['program_id'],
                            !empty($_POST['section_id']) ? $_POST['section_id'] : null,
                            $_POST['admission_date'],
                            $_POST['student_type'],
                            $_POST['guardian_name'] ?? null,
                            $_POST['guardian_contact'] ?? null
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
                                $emailResult = $emailService->sendStudentEnrollmentEmail($studentData, $plain_password);
                                
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
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = 'System Error: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                    break;
                    
                case 'update_student':
                    // Update student information
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, email = ?, student_id = ?, status = ?
                        WHERE id = ? AND role = 'student'
                    ");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['student_id'],
                        $_POST['status'],
                        $_POST['student_id_pk']
                    ]);
                    
                    $message = 'Student information updated successfully!';
                    $message_type = 'success';
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
                    $new_password = 'ISATU' . date('Y') . rand(100, 999); // More secure password
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
                    
                case 'confirm_enrollment':
                    // Confirm student enrollment in a class section
                    $stmt = $pdo->prepare("
                        INSERT INTO enrollments (student_id, class_section_id, enrollment_date, status) 
                        VALUES (?, ?, NOW(), 'enrolled')
                        ON DUPLICATE KEY UPDATE status = 'enrolled'
                    ");
                    $stmt->execute([
                        $_POST['student_id_pk'],
                        $_POST['class_section_id']
                    ]);
                    
                    $message = 'Student enrollment confirmed successfully!';
                    $message_type = 'success';
                    break;
                    
                case 'remove_enrollment':
                    // Remove student enrollment
                    $stmt = $pdo->prepare("DELETE FROM enrollments WHERE student_id = ? AND class_section_id = ?");
                    $stmt->execute([
                        $_POST['student_id_pk'],
                        $_POST['class_section_id']
                    ]);
                    
                    $message = 'Student enrollment removed successfully!';
                    $message_type = 'success';
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
    } catch (Exception $e) {
        $message = 'System Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all students with their enrollment information
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.username, u.email, u.first_name, u.last_name, u.student_id, u.status, u.created_at,
            u.birth_date, u.gender, u.contact_number, u.address, u.emergency_contact, u.emergency_phone,
            sp.year_level, sp.program_id, sp.section_id, sp.admission_date, sp.student_type, 
            sp.guardian_name, sp.guardian_contact,
            p.program_code, p.program_name,
            s.section_name,
            d.name as department_name,
            COUNT(e.id) as total_enrollments,
            COUNT(CASE WHEN e.status = 'enrolled' THEN 1 END) as active_enrollments
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        LEFT JOIN departments d ON p.department_id = d.id
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

// Get available class sections for enrollment
try {
    $stmt = $pdo->prepare("
        SELECT 
            cs.id, cs.section_name, cs.schedule, cs.room, cs.max_students,
            c.course_name, c.course_code,
            CONCAT(uf.first_name, ' ', uf.last_name) as faculty_name,
            d.name as department_name,
            COUNT(e.id) as current_enrollments
        FROM class_sections cs
        LEFT JOIN courses c ON cs.course_id = c.id
        LEFT JOIN users uf ON cs.faculty_id = uf.id
        LEFT JOIN departments d ON c.department_id = d.id
        LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
        WHERE cs.status = 'active'
        GROUP BY cs.id
        ORDER BY c.course_name, cs.section_name
    ");
    $stmt->execute();
    $class_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $class_sections = [];
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
    // Keep default stats if queries fail
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
                    <button class="btn btn-secondary" onclick="exportStudents()">
                        <i class="fas fa-download"></i> Export Student List
                    </button>
                    <button class="btn btn-outline" onclick="printStudentList()">
                        <i class="fas fa-print"></i> Print List
                    </button>
                    <button class="btn btn-info" onclick="testEmail()">
                        <i class="fas fa-envelope-circle-check"></i> Test Email
                    </button>
                </div>

                <!-- Bulk Actions (hidden by default) -->
                <div class="bulk-actions">
                    <span><i class="fas fa-check-square"></i> <span class="bulk-count">0</span> students selected</span>
                    <div class="bulk-action-buttons">
                        <button class="btn btn-sm btn-secondary" onclick="exportSelected()">
                            <i class="fas fa-download"></i> Export Selected
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="clearSelection()">
                            <i class="fas fa-times"></i> Clear Selection
                        </button>
                    </div>
                </div>

                <!-- Students Table -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i> Students
                        </h3>
                    </div>

                    <!-- Search and Filter Section -->
                    <div class="search-container">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Search students by name, email, student ID..." onkeyup="searchStudents()">
                            <i class="fas fa-search"></i>
                        </div>
                        
                        <div class="search-filters">
                            <select class="filter-select" id="statusFilter" onchange="filterStudents()">
                                <option value="">All Status</option>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                            
                            <select class="filter-select" id="enrollmentFilter" onchange="filterStudents()">
                                <option value="">All Enrollment Status</option>
                                <option value="enrolled">Enrolled</option>
                                <option value="not-enrolled">Not Enrolled</option>
                            </select>
                            
                            <button class="btn btn-outline" onclick="clearAllFilters()">
                                <i class="fas fa-times"></i> Clear Filters
                            </button>
                        </div>

                        <div class="filter-summary"></div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table" id="studentsTable">
                            <thead>
                                <tr>
                                    <th class="select-all-header">
                                        <input type="checkbox" id="selectAll" onchange="selectAllStudents()">
                                    </th>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Enrollments</th>
                                    <th>Joined Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 2rem; color: #6b7280;">
                                            <i class="fas fa-graduation-cap" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                            <p>No students found. Click "Add New Student" to get started.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="student-checkbox" value="<?php echo $student['id']; ?>" onchange="updateBulkActions()">
                                            </td>
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
                                            <td>
                                                <div class="enrollment-info">
                                                    <span class="badge badge-primary"><?php echo $student['active_enrollments']; ?> Active</span>
                                                    <?php if ($student['total_enrollments'] > $student['active_enrollments']): ?>
                                                        <span class="badge badge-secondary"><?php echo ($student['total_enrollments'] - $student['active_enrollments']); ?> Past</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-icon btn-primary" onclick="viewStudent(<?php echo $student['id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn-icon btn-success" onclick="manageEnrollment(<?php echo $student['id']; ?>)" title="Manage Enrollment">
                                                        <i class="fas fa-clipboard-list"></i>
                                                    </button>
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
                                    $stmt = $pdo->prepare("SELECT id, CONCAT(year_start, '-', year_end, ' (', semester, ' Semester)') as academic_year FROM academic_years WHERE is_active = 1 ORDER BY year_start DESC");
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
                            <select id="program_id" name="program_id" required onchange="loadSections()">
                                <option value="">Select Program</option>
                                <?php
                                try {
                                    $stmt = $pdo->prepare("SELECT p.id, p.program_code, p.program_name, d.name as department_name FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.status = 'active' ORDER BY p.program_name");
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
                            <select id="year_level" name="year_level" required onchange="loadSections()">
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
                                <!-- Options will be loaded dynamically -->
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
                            <span class="checkmark"></span>
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
                <h2>Edit Student</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" class="faculty-form" id="editStudentForm">
                <input type="hidden" name="action" value="update_student">
                <input type="hidden" name="student_id_pk" id="edit_student_id">
                
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
                        <label for="edit_email">Email *</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_student_id">Student ID *</label>
                        <input type="text" id="edit_student_id_field" name="student_id" required>
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
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Student
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Enrollment Modal -->
    <div id="enrollmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manage Student Enrollment</h2>
                <span class="close" onclick="closeEnrollmentModal()">&times;</span>
            </div>
            <div id="enrollmentContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- View Student Modal -->
    <div id="viewStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Student Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div id="viewStudentContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Include Scripts -->
    <script>
        // Pass PHP data to JavaScript
        const allStudentData = <?php echo json_encode($students); ?>;
        const classSections = <?php echo json_encode($class_sections); ?>;
        
        // Modal functions
        function openAddModal() {
            document.getElementById('addStudentModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addStudentModal').style.display = 'none';
        }
        
        function closeEditModal() {
            document.getElementById('editStudentModal').style.display = 'none';
        }
        
        function closeEnrollmentModal() {
            document.getElementById('enrollmentModal').style.display = 'none';
        }
        
        function closeViewModal() {
            document.getElementById('viewStudentModal').style.display = 'none';
        }
        
        // Student management functions
        function viewStudent(studentId) {
            const student = allStudentData.find(s => s.id == studentId);
            if (!student) return;
            
            // Fetch student's enrollments via AJAX
            fetch(`get_student_enrollments.php?student_id=${studentId}`)
                .then(response => response.json())
                .then(enrollments => {
                    let enrollmentsHTML = '';
                    if (enrollments.length > 0) {
                        enrollmentsHTML = `
                            <div class="enrollments-section">
                                <h4>Current Enrollments</h4>
                                <div class="enrollments-list">
                        `;
                        enrollments.forEach(enrollment => {
                            enrollmentsHTML += `
                                <div class="enrollment-item">
                                    <div class="course-info">
                                        <h5>${enrollment.course_code} - ${enrollment.course_name}</h5>
                                        <p><strong>Section:</strong> ${enrollment.section_name}</p>
                                        <p><strong>Faculty:</strong> ${enrollment.faculty_name}</p>
                                        <p><strong>Schedule:</strong> ${enrollment.schedule || 'TBA'}</p>
                                        <p><strong>Room:</strong> ${enrollment.room || 'TBA'}</p>
                                    </div>
                                    <div class="enrollment-status">
                                        <span class="badge badge-${enrollment.status === 'enrolled' ? 'success' : 'secondary'}">
                                            ${enrollment.status.charAt(0).toUpperCase() + enrollment.status.slice(1)}
                                        </span>
                                        <small>Enrolled: ${new Date(enrollment.enrollment_date).toLocaleDateString()}</small>
                                    </div>
                                </div>
                            `;
                        });
                        enrollmentsHTML += `
                                </div>
                            </div>
                        `;
                    } else {
                        enrollmentsHTML = `
                            <div class="enrollments-section">
                                <h4>Current Enrollments</h4>
                                <p class="no-enrollments">No active enrollments found.</p>
                            </div>
                        `;
                    }
                    
                    const content = `
                        <div class="student-details">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Student ID:</label>
                                    <span>${student.student_id}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Full Name:</label>
                                    <span>${student.first_name} ${student.last_name}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Username:</label>
                                    <span>@${student.username}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Email:</label>
                                    <span>${student.email}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Status:</label>
                                    <span class="status-badge status-${student.status}">${student.status.charAt(0).toUpperCase() + student.status.slice(1)}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Total Enrollments:</label>
                                    <span>${student.total_enrollments}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Active Enrollments:</label>
                                    <span>${student.active_enrollments}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Joined Date:</label>
                                    <span>${new Date(student.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                            ${enrollmentsHTML}
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
                            <button type="button" class="btn btn-primary" onclick="editStudent(${student.id})">
                                <i class="fas fa-edit"></i> Edit Student
                            </button>
                            <button type="button" class="btn btn-success" onclick="manageEnrollment(${student.id})">
                                <i class="fas fa-clipboard-list"></i> Manage Enrollment
                            </button>
                        </div>
                    `;
                    
                    document.getElementById('viewStudentContent').innerHTML = content;
                })
                .catch(error => {
                    console.error('Error fetching enrollments:', error);
                    // Fallback to basic view without enrollments
                    const content = `
                        <div class="student-details">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Student ID:</label>
                                    <span>${student.student_id}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Full Name:</label>
                                    <span>${student.first_name} ${student.last_name}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Username:</label>
                                    <span>@${student.username}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Email:</label>
                                    <span>${student.email}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Status:</label>
                                    <span class="status-badge status-${student.status}">${student.status.charAt(0).toUpperCase() + student.status.slice(1)}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Total Enrollments:</label>
                                    <span>${student.total_enrollments}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Active Enrollments:</label>
                                    <span>${student.active_enrollments}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Joined Date:</label>
                                    <span>${new Date(student.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                            <div class="enrollments-section">
                                <h4>Current Enrollments</h4>
                                <p class="error-message">Unable to load enrollment data.</p>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
                            <button type="button" class="btn btn-primary" onclick="editStudent(${student.id})">
                                <i class="fas fa-edit"></i> Edit Student
                            </button>
                            <button type="button" class="btn btn-success" onclick="manageEnrollment(${student.id})">
                                <i class="fas fa-clipboard-list"></i> Manage Enrollment
                            </button>
                        </div>
                    `;
                    
                    document.getElementById('viewStudentContent').innerHTML = content;
                });
            
            document.getElementById('viewStudentModal').style.display = 'block';
        }
        
        function editStudent(studentId) {
            const student = allStudentData.find(s => s.id == studentId);
            if (!student) return;
            
            // Populate edit form
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_first_name').value = student.first_name;
            document.getElementById('edit_last_name').value = student.last_name;
            document.getElementById('edit_email').value = student.email;
            document.getElementById('edit_student_id_field').value = student.student_id;
            document.getElementById('edit_status').value = student.status;
            
            // Close view modal if open and show edit modal
            closeViewModal();
            document.getElementById('editStudentModal').style.display = 'block';
        }
        
        function manageEnrollment(studentId) {
            const student = allStudentData.find(s => s.id == studentId);
            if (!student) return;
            
            // Create enrollment management content
            let sectionsHTML = '';
            classSections.forEach(section => {
                const availableSpots = section.max_students - section.current_enrollments;
                const isAvailable = availableSpots > 0;
                
                sectionsHTML += `
                    <div class="enrollment-section ${!isAvailable ? 'full' : ''}">
                        <div class="section-info">
                            <h4>${section.course_code} - ${section.course_name}</h4>
                            <p><strong>Section:</strong> ${section.section_name}</p>
                            <p><strong>Faculty:</strong> ${section.faculty_name || 'TBA'}</p>
                            <p><strong>Schedule:</strong> ${section.schedule || 'TBA'}</p>
                            <p><strong>Room:</strong> ${section.room || 'TBA'}</p>
                            <p><strong>Department:</strong> ${section.department_name || 'N/A'}</p>
                            <div class="enrollment-status">
                                <span class="badge ${isAvailable ? 'badge-success' : 'badge-danger'}">
                                    ${section.current_enrollments}/${section.max_students} enrolled
                                </span>
                                ${!isAvailable ? '<span class="badge badge-warning">FULL</span>' : ''}
                            </div>
                        </div>
                        <div class="enrollment-actions">
                            ${isAvailable ? `
                                <button class="btn btn-sm btn-success" onclick="confirmEnrollment(${studentId}, ${section.id})">
                                    <i class="fas fa-plus"></i> Enroll
                                </button>
                            ` : `
                                <button class="btn btn-sm btn-secondary" disabled>
                                    <i class="fas fa-ban"></i> Full
                                </button>
                            `}
                            <button class="btn btn-sm btn-danger" onclick="removeEnrollment(${studentId}, ${section.id})">
                                <i class="fas fa-minus"></i> Remove
                            </button>
                        </div>
                    </div>
                `;
            });
            
            const content = `
                <div class="enrollment-management">
                    <div class="student-info-header">
                        <h3>${student.first_name} ${student.last_name} (${student.student_id})</h3>
                        <p>Current Enrollments: ${student.active_enrollments}</p>
                    </div>
                    <div class="available-sections">
                        <h4>Available Class Sections</h4>
                        ${sectionsHTML || '<p>No class sections available.</p>'}
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEnrollmentModal()">Close</button>
                </div>
            `;
            
            document.getElementById('enrollmentContent').innerHTML = content;
            document.getElementById('enrollmentModal').style.display = 'block';
        }
        
        function confirmEnrollment(studentId, classSectionId) {
            if (confirm('Confirm enrollment for this student in the selected class section?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                form.innerHTML = `
                    <input type="hidden" name="action" value="confirm_enrollment">
                    <input type="hidden" name="student_id_pk" value="${studentId}">
                    <input type="hidden" name="class_section_id" value="${classSectionId}">
                `;
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function removeEnrollment(studentId, classSectionId) {
            if (confirm('Remove this student from the selected class section?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                form.innerHTML = `
                    <input type="hidden" name="action" value="remove_enrollment">
                    <input type="hidden" name="student_id_pk" value="${studentId}">
                    <input type="hidden" name="class_section_id" value="${classSectionId}">
                `;
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteStudent(studentId, studentName) {
            if (confirm(`Are you sure you want to delete ${studentName}?\n\nThis action cannot be undone and will remove all associated enrollment records.`)) {
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
        }
        
        function resetPassword(studentId) {
            const sendEmail = confirm(
                'Reset this student\'s password?\n\n' +
                'Click OK to reset and send email notification\n' +
                'Click Cancel to abort'
            );
            
            if (sendEmail) {
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
        }
        
        // Test email function
        function testEmail() {
            if (confirm('This will send a test email to verify your email configuration. Continue?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                form.innerHTML = '<input type="hidden" name="action" value="test_email">';
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Search and filter functions
        function searchStudents() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const enrollmentFilter = document.getElementById('enrollmentFilter').value;
            
            filterTable(searchTerm, statusFilter, enrollmentFilter);
        }
        
        function filterStudents() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const enrollmentFilter = document.getElementById('enrollmentFilter').value;
            
            filterTable(searchTerm, statusFilter, enrollmentFilter);
        }
        
        function filterTable(searchTerm, statusFilter, enrollmentFilter) {
            const table = document.getElementById('studentsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            let visibleCount = 0;
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                if (row.cells.length < 8) continue; // Skip empty state row
                
                const studentId = row.cells[1].textContent.toLowerCase();
                const name = row.cells[2].textContent.toLowerCase();
                const email = row.cells[3].textContent.toLowerCase();
                const status = row.cells[4].textContent.toLowerCase();
                const enrollments = parseInt(row.cells[5].querySelector('.badge-primary')?.textContent || '0');
                
                let showRow = true;
                
                // Search filter
                if (searchTerm && !name.includes(searchTerm) && !email.includes(searchTerm) && !studentId.includes(searchTerm)) {
                    showRow = false;
                }
                
                // Status filter
                if (statusFilter && !status.includes(statusFilter)) {
                    showRow = false;
                }
                
                // Enrollment filter
                if (enrollmentFilter) {
                    if (enrollmentFilter === 'enrolled' && enrollments === 0) {
                        showRow = false;
                    }
                    if (enrollmentFilter === 'not-enrolled' && enrollments > 0) {
                        showRow = false;
                    }
                }
                
                row.style.display = showRow ? '' : 'none';
                if (showRow) visibleCount++;
            }
            
            updateFilterSummary(visibleCount, rows.length);
        }
        
        function clearAllFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('enrollmentFilter').value = '';
            filterTable('', '', '');
        }
        
        function updateFilterSummary(visible, total) {
            const summary = document.querySelector('.filter-summary');
            if (visible === total) {
                summary.textContent = '';
            } else {
                summary.textContent = `Showing ${visible} of ${total} students`;
            }
        }
        
        // Bulk actions
        function selectAllStudents() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.student-checkbox');
            
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                if (row.style.display !== 'none') {
                    checkbox.checked = selectAll.checked;
                }
            });
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            const bulkActions = document.querySelector('.bulk-actions');
            const bulkCount = document.querySelector('.bulk-count');
            
            bulkCount.textContent = checkedBoxes.length;
            bulkActions.style.display = checkedBoxes.length > 0 ? 'flex' : 'none';
        }
        
        function clearSelection() {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateBulkActions();
        }

        function loadSections() {
            const programId = document.getElementById('program_id').value;
            const yearLevel = document.getElementById('year_level').value;
            const sectionSelect = document.getElementById('section_id');
            
            // Clear existing options
            sectionSelect.innerHTML = '<option value="">Select Section</option>';
            
            if (programId && yearLevel) {
                // Make AJAX call to load sections
                fetch(`../api/get_sections.php?program_id=${programId}&year_level=${yearLevel}`)
                    .then(response => response.json())
                    .then(sections => {
                        if (sections.error) {
                            console.error('Error loading sections:', sections.error);
                            return;
                        }
                        
                        sections.forEach(section => {
                            const option = document.createElement('option');
                            option.value = section.id;
                            option.textContent = section.section_name;
                            sectionSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching sections:', error);
                    });
            }
        }
        
        // Export functions
        function exportStudents() {
            alert('Export functionality would be implemented here');
        }
        
        function exportSelected() {
            const selected = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
            alert(`Export selected students: ${selected.join(', ')}`);
        }
        
        function printStudentList() {
            window.print();
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['addStudentModal', 'editStudentModal', 'enrollmentModal', 'viewStudentModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize filter summary
            const table = document.getElementById('studentsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            updateFilterSummary(rows.length, rows.length);
        });
    </script>
    
    <style>
        .enrollment-section {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .enrollment-section.full {
            background-color: #fef2f2;
            border-color: #fecaca;
        }
        
        .section-info h4 {
            margin: 0 0 0.5rem 0;
            color: #1f2937;
        }
        
        .section-info p {
            margin: 0.25rem 0;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .enrollment-status {
            margin-top: 0.5rem;
        }
        
        .enrollment-actions {
            display: flex;
            gap: 0.5rem;
            flex-direction: column;
        }
        
        .student-info-header {
            background-color: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .student-info-header h3 {
            margin: 0 0 0.5rem 0;
            color: #1f2937;
        }
        
        .student-info-header p {
            margin: 0;
            color: #6b7280;
        }
        
        .available-sections h4 {
            margin-bottom: 1rem;
            color: #1f2937;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        /* Make view modal larger */
        #viewStudentModal .modal-content {
            max-width: 800px;
            width: 90%;
        }
        
        /* Make enrollment modal larger */
        #enrollmentModal .modal-content {
            max-width: 900px;
            width: 95%;
        }
        
        /* Increase modal content padding for better spacing */
        .modal-content {
            padding: 2rem;
        }
        
        .modal-header {
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .detail-item label {
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .detail-item span {
            color: #1f2937;
        }
        
        .enrollment-info {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-primary {
            background-color: #dbeafe;
            color: #1d4ed8;
        }
        
        .badge-secondary {
            background-color: #f3f4f6;
            color: #6b7280;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #059669;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        /* Student Enrollment Styles */
        .enrollments-section {
            margin-top: 2rem;
        }
        
        .enrollments-section h4 {
            margin-bottom: 1rem;
            color: #1f2937;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }
        
        .enrollments-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .enrollment-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            background-color: #f9fafb;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .course-info h5 {
            margin: 0 0 0.5rem 0;
            color: #1f2937;
            font-size: 1.1rem;
        }
        
        .course-info p {
            margin: 0.25rem 0;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .enrollment-status {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }
        
        .enrollment-status small {
            color: #6b7280;
            font-size: 0.75rem;
        }
        
        .no-enrollments {
            color: #6b7280;
            font-style: italic;
            text-align: center;
            padding: 2rem;
        }
        
        .error-message {
            color: #dc2626;
            font-style: italic;
            text-align: center;
            padding: 1rem;
            background-color: #fee2e2;
            border-radius: 4px;
        }
    </style>
</body>
</html>