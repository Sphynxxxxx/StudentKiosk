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
require_once '../includes/EmailService.php';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_faculty':
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
                    
                    // Add new faculty
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, first_name, last_name, role, employee_id, status) 
                        VALUES (?, ?, ?, ?, ?, 'faculty', ?, 'active')
                    ");
                    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
                    $stmt->execute([
                        $username,
                        $_POST['email'],
                        $hashed_password,
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['employee_id']
                    ]);
                    
                    $user_id = $pdo->lastInsertId();
                    
                    // Add faculty profile
                    $stmt = $pdo->prepare("
                        INSERT INTO faculty_profiles 
                        (user_id, department_id, position, employment_type, specialization, phone, hire_date, consultation_hours) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user_id,
                        !empty($_POST['department_id']) ? $_POST['department_id'] : null,
                        $_POST['position'],
                        $_POST['employment_type'],
                        $_POST['specialization'],
                        $_POST['phone'],
                        !empty($_POST['hire_date']) ? $_POST['hire_date'] : null,
                        $_POST['consultation_hours']
                    ]);
                    
                    // Prepare faculty data for email
                    $facultyData = [
                        'id' => $user_id,
                        'username' => $username,
                        'email' => $_POST['email'],
                        'first_name' => $_POST['first_name'],
                        'last_name' => $_POST['last_name'],
                        'employee_id' => $_POST['employee_id'],
                        'position' => $_POST['position']
                    ];
                    
                    // Send welcome email
                    if (isset($_POST['send_email']) && $_POST['send_email'] == '1') {
                        try {
                            $emailService = new EmailService();
                            $emailResult = $emailService->sendFacultyEnrollmentEmail($facultyData, $plain_password);
                            
                            if ($emailResult['success']) {
                                $message = 'Faculty member added successfully! Username: ' . $username . '. ' . $emailResult['message'];
                            } else {
                                $message = 'Faculty member added successfully! Username: ' . $username . '. However, email sending failed: ' . $emailResult['message'];
                            }
                        } catch (Exception $e) {
                            $message = 'Faculty member added successfully! Username: ' . $username . '. However, email sending failed: ' . $e->getMessage();
                        }
                    } else {
                        $message = 'Faculty member added successfully! Username: ' . $username;
                    }
                    
                    $message_type = 'success';
                    break;
                    
                case 'update_faculty':
                    // Update faculty information
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, email = ?, employee_id = ?, status = ?
                        WHERE id = ? AND role = 'faculty'
                    ");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['employee_id'],
                        $_POST['status'],
                        $_POST['faculty_id']
                    ]);
                    
                    // Update or insert faculty profile
                    $stmt = $pdo->prepare("
                        INSERT INTO faculty_profiles 
                        (user_id, department_id, position, employment_type, specialization, phone, hire_date, consultation_hours)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        department_id = VALUES(department_id),
                        position = VALUES(position),
                        employment_type = VALUES(employment_type),
                        specialization = VALUES(specialization),
                        phone = VALUES(phone),
                        hire_date = VALUES(hire_date),
                        consultation_hours = VALUES(consultation_hours)
                    ");
                    $stmt->execute([
                        $_POST['faculty_id'],
                        !empty($_POST['department_id']) ? $_POST['department_id'] : null,
                        $_POST['position'],
                        $_POST['employment_type'],
                        $_POST['specialization'],
                        $_POST['phone'],
                        !empty($_POST['hire_date']) ? $_POST['hire_date'] : null,
                        $_POST['consultation_hours']
                    ]);
                    
                    $message = 'Faculty information updated successfully!';
                    $message_type = 'success';
                    break;
                    
                case 'delete_faculty':
                    // Delete faculty (this will cascade delete the profile)
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'faculty'");
                    $stmt->execute([$_POST['faculty_id']]);
                    
                    $message = 'Faculty member deleted successfully!';
                    $message_type = 'success';
                    break;
                    
                case 'reset_password':
                    // Generate a new password
                    $new_password = 'ISATU' . date('Y') . rand(100, 999); // More secure password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Get faculty data for email
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'faculty'");
                    $stmt->execute([$_POST['faculty_id']]);
                    $facultyData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($facultyData) {
                        // Update password
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'faculty'");
                        $stmt->execute([$hashed_password, $_POST['faculty_id']]);
                        
                        // Send password reset email
                        if (isset($_POST['send_reset_email']) && $_POST['send_reset_email'] == '1') {
                            try {
                                $emailService = new EmailService();
                                $emailResult = $emailService->sendPasswordResetEmail($facultyData, $new_password);
                                
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
                        $message = 'Faculty member not found!';
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
    } catch (Exception $e) {
        $message = 'System Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all faculty members with their profiles (with error handling)
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.username, u.email, u.first_name, u.last_name, u.employee_id, u.status, u.created_at,
            COALESCE(fp.department_id, NULL) as department_id, 
            COALESCE(fp.position, 'Instructor') as position, 
            COALESCE(fp.employment_type, 'Full-time') as employment_type, 
            COALESCE(fp.specialization, '') as specialization, 
            COALESCE(fp.phone, '') as phone, 
            fp.hire_date, 
            COALESCE(fp.consultation_hours, '') as consultation_hours,
            COALESCE(d.name, '') as department_name, 
            COALESCE(d.code, '') as department_code
        FROM users u
        LEFT JOIN faculty_profiles fp ON u.id = fp.user_id
        LEFT JOIN departments d ON fp.department_id = d.id
        WHERE u.role = 'faculty'
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute();
    $faculty_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $faculty_members = [];
    if (empty($message)) {
        $message = 'Error loading faculty data: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get departments for dropdowns (with error handling)
try {
    $stmt = $pdo->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->query("SELECT id, name, code FROM departments ORDER BY name");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $departments = [];
        if (empty($message)) {
            $message = 'Departments table not found. Please run the database update script.';
            $message_type = 'error';
        }
    }
}

// Get faculty statistics (with error handling)
$stats = ['total_faculty' => 0, 'active_faculty' => 0, 'fulltime_faculty' => 0, 'departments_with_faculty' => 0];

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'faculty'");
    $stats['total_faculty'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'faculty' AND status = 'active'");
    $stats['active_faculty'] = $stmt->fetchColumn();

    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM users u 
            LEFT JOIN faculty_profiles fp ON u.id = fp.user_id 
            WHERE u.role = 'faculty' AND COALESCE(fp.employment_type, 'Full-time') = 'Full-time'
        ");
        $stats['fulltime_faculty'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['fulltime_faculty'] = 0;
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(DISTINCT fp.department_id) FROM faculty_profiles fp WHERE fp.department_id IS NOT NULL");
        $stats['departments_with_faculty'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['departments_with_faculty'] = 0;
    }
} catch (PDOException $e) {
    // Keep default stats if queries fail
    error_log("Faculty stats query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Faculty - ISATU Kiosk System</title>
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
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_faculty']; ?></div>
                        <div class="stat-label">Total Faculty</div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-card-header">
                            <div class="stat-icon success">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['active_faculty']; ?></div>
                        <div class="stat-label">Active Faculty</div>
                    </div>

                    <div class="stat-card warning">
                        <div class="stat-card-header">
                            <div class="stat-icon warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['fulltime_faculty']; ?></div>
                        <div class="stat-label">Full-time Faculty</div>
                    </div>

                    <div class="stat-card info">
                        <div class="stat-card-header">
                            <div class="stat-icon info">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['departments_with_faculty']; ?></div>
                        <div class="stat-label">Active Departments</div>
                    </div>
                </div>

                <!-- Page Actions -->
                <div class="page-actions">
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add New Faculty
                    </button>
                    <button class="btn btn-secondary" onclick="exportFaculty()">
                        <i class="fas fa-download"></i> Export Faculty List
                    </button>
                    <button class="btn btn-outline" onclick="printFacultyList()">
                        <i class="fas fa-print"></i> Print List
                    </button>
                    <button class="btn btn-info" onclick="testEmail()">
                        <i class="fas fa-envelope-circle-check"></i> Test Email
                    </button>
                </div>

                <!-- Bulk Actions (hidden by default) -->
                <div class="bulk-actions">
                    <span><i class="fas fa-check-square"></i> <span class="bulk-count">0</span> faculty members selected</span>
                    <div class="bulk-action-buttons">
                        <button class="btn btn-sm btn-secondary" onclick="exportSelected()">
                            <i class="fas fa-download"></i> Export Selected
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="clearSelection()">
                            <i class="fas fa-times"></i> Clear Selection
                        </button>
                    </div>
                </div>

                <!-- Faculty Table -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i> Faculty Members
                        </h3>
                    </div>

                    <!-- Search and Filter Section -->
                    <div class="search-container">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Search faculty by name, email, department..." onkeyup="searchFaculty()">
                            <i class="fas fa-search"></i>
                        </div>
                        
                        <div class="search-filters">
                            <select class="filter-select" id="departmentFilter" onchange="filterFaculty()">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['name']); ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select class="filter-select" id="positionFilter" onchange="filterFaculty()">
                                <option value="">All Positions</option>
                                <option value="Professor">Professor</option>
                                <option value="Associate Professor">Associate Professor</option>
                                <option value="Assistant Professor">Assistant Professor</option>
                                <option value="Lecturer">Lecturer</option>
                                <option value="Instructor">Instructor</option>
                            </select>
                            
                            <select class="filter-select" id="employmentFilter" onchange="filterFaculty()">
                                <option value="">All Employment Types</option>
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                            </select>
                            
                            <select class="filter-select" id="statusFilter" onchange="filterFaculty()">
                                <option value="">All Status</option>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                            
                            <button class="btn btn-outline" onclick="clearAllFilters()">
                                <i class="fas fa-times"></i> Clear Filters
                            </button>
                        </div>

                        <div class="filter-summary"></div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table" id="facultyTable">
                            <thead>
                                <tr>
                                    <th class="select-all-header">
                                        <input type="checkbox" id="selectAll" onchange="selectAllFaculty()">
                                    </th>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Employment Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($faculty_members)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 2rem; color: #6b7280;">
                                            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                            <p>No faculty members found. Click "Add New Faculty" to get started.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($faculty_members as $faculty): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="faculty-checkbox" value="<?php echo $faculty['id']; ?>" onchange="updateBulkActions()">
                                            </td>
                                            <td><?php echo htmlspecialchars($faculty['employee_id'] ?? 'N/A'); ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <strong><?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?></strong>
                                                    <small>@<?php echo htmlspecialchars($faculty['username']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($faculty['email']); ?></td>
                                            <td>
                                                <?php if (!empty($faculty['department_name'])): ?>
                                                    <span class="department-badge">
                                                        <?php echo htmlspecialchars($faculty['department_code']); ?>
                                                    </span>
                                                    <small><?php echo htmlspecialchars($faculty['department_name']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($faculty['position']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower(str_replace('-', '', $faculty['employment_type'])); ?>">
                                                    <?php echo htmlspecialchars($faculty['employment_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $faculty['status']; ?>">
                                                    <?php echo ucfirst($faculty['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-icon btn-primary" onclick="viewFaculty(<?php echo $faculty['id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn-icon btn-warning" onclick="editFaculty(<?php echo $faculty['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn-icon btn-info" onclick="resetPassword(<?php echo $faculty['id']; ?>)" title="Reset Password">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button class="btn-icon btn-danger" onclick="deleteFaculty(<?php echo $faculty['id']; ?>, '<?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>')" title="Delete">
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

    <!-- Add Faculty Modal -->
    <div id="addFacultyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Faculty Member</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" class="faculty-form">
                <input type="hidden" name="action" value="add_faculty">
                
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
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="employee_id">Employee ID *</label>
                        <input type="text" id="employee_id" name="employee_id" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="position">Position</label>
                        <select id="position" name="position">
                            <option value="Instructor">Instructor</option>
                            <option value="Lecturer">Lecturer</option>
                            <option value="Assistant Professor">Assistant Professor</option>
                            <option value="Associate Professor">Associate Professor</option>
                            <option value="Professor">Professor</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="employment_type">Employment Type</label>
                        <select id="employment_type" name="employment_type">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contractual">Contractual</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="hire_date">Hire Date</label>
                        <input type="date" id="hire_date" name="hire_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="specialization">Specialization</label>
                    <input type="text" id="specialization" name="specialization" placeholder="e.g., Computer Programming, Mathematics">
                </div>
                
                <div class="form-group">
                    <label for="consultation_hours">Consultation Hours</label>
                    <textarea id="consultation_hours" name="consultation_hours" rows="2" placeholder="e.g., Mon-Fri 10:00 AM - 12:00 PM"></textarea>
                </div>
                
                <!-- Email Option -->
                <div class="form-group email-option">
                    <label class="checkbox-label">
                        <input type="checkbox" id="send_email" name="send_email" value="1" checked>
                        <span class="checkmark"></span>
                        Send welcome email with login credentials
                    </label>
                    <small class="form-text">The faculty member will receive an email with their username and password.</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add Faculty
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Faculty Modal -->
    <div id="editFacultyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Faculty Member</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" class="faculty-form" id="editFacultyForm">
                <input type="hidden" name="action" value="update_faculty">
                <input type="hidden" name="faculty_id" id="edit_faculty_id">
                
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
                        <label for="edit_employee_id">Employee ID *</label>
                        <input type="text" id="edit_employee_id" name="employee_id" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Status *</label>
                        <select id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_department_id">Department</label>
                        <select id="edit_department_id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_position">Position</label>
                        <select id="edit_position" name="position">
                            <option value="Instructor">Instructor</option>
                            <option value="Lecturer">Lecturer</option>
                            <option value="Assistant Professor">Assistant Professor</option>
                            <option value="Associate Professor">Associate Professor</option>
                            <option value="Professor">Professor</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_employment_type">Employment Type</label>
                        <select id="edit_employment_type" name="employment_type">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contractual">Contractual</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="tel" id="edit_phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_hire_date">Hire Date</label>
                        <input type="date" id="edit_hire_date" name="hire_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_specialization">Specialization</label>
                    <input type="text" id="edit_specialization" name="specialization" placeholder="e.g., Computer Programming, Mathematics">
                </div>
                
                <div class="form-group">
                    <label for="edit_consultation_hours">Consultation Hours</label>
                    <textarea id="edit_consultation_hours" name="consultation_hours" rows="2" placeholder="e.g., Mon-Fri 10:00 AM - 12:00 PM"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Faculty
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Include Scripts -->
    <script>
        // Pass PHP data to JavaScript
        const allFacultyData = <?php echo json_encode($faculty_members); ?>;
        
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
        
        // Override reset password function to include email option
        function resetPassword(facultyId) {
            const sendEmail = confirm(
                'Reset this faculty member\'s password?\n\n' +
                'Click OK to reset and send email notification\n' +
                'Click Cancel to abort'
            );
            
            if (sendEmail) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                form.innerHTML = `
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="faculty_id" value="${facultyId}">
                    <input type="hidden" name="send_reset_email" value="1">
                `;
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    <script src="../assets/js/manage_faculty.js"></script>
</body>
</html>
                