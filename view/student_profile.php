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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $user_id = $_SESSION['student_id'];
        
        // Get form data
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
        $address = trim($_POST['address']);
        $contact_number = trim($_POST['contact_number']);
        $emergency_contact = trim($_POST['emergency_contact']);
        $emergency_phone = trim($_POST['emergency_phone']);
        
        // Update users table
        $stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, 
                last_name = ?, 
                email = ?, 
                birth_date = ?, 
                gender = ?, 
                address = ?, 
                contact_number = ?,
                emergency_contact = ?,
                emergency_phone = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $first_name, 
            $last_name, 
            $email, 
            $birth_date, 
            $gender, 
            $address, 
            $contact_number,
            $emergency_contact,
            $emergency_phone,
            $user_id
        ]);
        
        // Update student_profiles table
        $guardian_name = trim($_POST['guardian_name']);
        $guardian_contact = trim($_POST['guardian_contact']);
        
        $stmt = $pdo->prepare("
            UPDATE student_profiles 
            SET guardian_name = ?, 
                guardian_contact = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$guardian_name, $guardian_contact, $user_id]);
        
        // Log the activity
        $log_stmt = $pdo->prepare("
            INSERT INTO user_logs (user_id, activity_type, description, ip_address, created_at)
            VALUES (?, 'profile_update', 'Updated profile information', ?, NOW())
        ");
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log_stmt->execute([$user_id, $ip_address]);
        
        $success_message = 'Profile updated successfully!';
        
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error_message = 'Error updating profile: ' . $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $user_id = $_SESSION['student_id'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $user['password'])) {
            throw new Exception('Current password is incorrect.');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('New passwords do not match.');
        }
        
        if (strlen($new_password) < 8) {
            throw new Exception('New password must be at least 8 characters long.');
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        // Log the activity
        $log_stmt = $pdo->prepare("
            INSERT INTO user_logs (user_id, activity_type, description, ip_address, created_at)
            VALUES (?, 'password_change', 'Changed account password', ?, NOW())
        ");
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log_stmt->execute([$user_id, $ip_address]);
        
        $success_message = 'Password changed successfully!';
        
    } catch (Exception $e) {
        error_log("Password change error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}

// Get student information
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               sp.year_level, 
               sp.program_id, 
               sp.section_id,
               sp.academic_year_id,
               sp.admission_date,
               sp.student_type,
               sp.student_status,
               sp.guardian_name,
               sp.guardian_contact,
               p.program_name, 
               p.program_code,
               p.degree_type,
               d.name as department_name,
               s.section_name,
               ay.year_start,
               ay.year_end,
               ay.semester
        FROM users u 
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN programs p ON sp.program_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN sections s ON sp.section_id = s.id
        LEFT JOIN academic_years ay ON sp.academic_year_id = ay.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$_SESSION['student_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header('Location: ../auth/student_logout.php');
        exit();
    }
    
    // Get enrollment statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_enrollments,
            SUM(CASE WHEN e.status = 'enrolled' THEN 1 ELSE 0 END) as active_enrollments,
            SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments
        FROM enrollments e
        WHERE e.student_id = ?
    ");
    $stats_stmt->execute([$_SESSION['student_id']]);
    $enrollment_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get GPA if available
    $gpa_stmt = $pdo->prepare("
        SELECT AVG(g.overall_grade) as gpa,
               COUNT(*) as graded_subjects
        FROM grades g
        INNER JOIN enrollments e ON g.enrollment_id = e.id
        WHERE e.student_id = ? 
          AND g.overall_grade IS NOT NULL
          AND g.letter_grade NOT IN ('INC', 'DRP')
    ");
    $gpa_stmt->execute([$_SESSION['student_id']]);
    $gpa_data = $gpa_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $error_message = 'Error loading profile data.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/student_dashboard.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .profile-header {
            background: #1e3a8a;
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            color: #1e3a8a;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }

        .profile-info h1 {
            margin: 0 0 10px 0;
            font-size: 2em;
        }

        .profile-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #1e3a8a;
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.95em;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.95em;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #1e3a8a;
        }

        .info-label {
            font-size: 0.85em;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .info-value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }

        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #1e3a8a;
            color: white;
        }

        .btn-primary:hover {
            background: #3d62c5ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
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

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-regular {
            background: #d1ecf1;
            color: #0c5460;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e9ecef;
        }

        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s;
        }

        .tab.active {
            color: #1e3a8a;
            border-bottom-color: #1e3a8a;
        }

        .tab:hover {
            color: #3d62c5ff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .profile-header-content {
                flex-direction: column;
                text-align: center;
            }

            .profile-meta {
                justify-content: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
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
                    <div class="system-title">STUDENT PORTAL</div>
                </div>
            </div>
            <div class="user-section">
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-info" onclick="toggleDropdown()">
                        <i class="fas fa-user-graduate"></i>
                        <span class="user-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="dropdown-user-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                            <div class="dropdown-user-role">Student</div>
                            <div class="dropdown-user-id">ID: <?php echo htmlspecialchars($student['student_id']); ?></div>
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

    <div class="profile-container">
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

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-header-content">
                <div class="profile-avatar">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h1>
                    <div class="profile-meta">
                        <div class="meta-item">
                            <i class="fas fa-id-card"></i>
                            <span><?php echo htmlspecialchars($student['student_id']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-layer-group"></i>
                            <span><?php echo htmlspecialchars($student['year_level'] ?? 'N/A'); ?> Year</span>
                        </div>
                        <?php if ($student['section_name']): ?>
                        <div class="meta-item">
                            <i class="fas fa-users"></i>
                            <span>Section <?php echo htmlspecialchars($student['section_name']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $enrollment_stats['total_enrollments']; ?></div>
                <div class="stat-label">Total Enrollments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $enrollment_stats['active_enrollments']; ?></div>
                <div class="stat-label">Active Subjects</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $enrollment_stats['completed_enrollments']; ?></div>
                <div class="stat-label">Completed Subjects</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    if ($gpa_data['gpa']) {
                        echo number_format($gpa_data['gpa'], 2);
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
                <div class="stat-label">Current GPA</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('profile')">
                <i class="fas fa-user"></i> Profile Information
            </button>
            <button class="tab" onclick="switchTab('academic')">
                <i class="fas fa-graduation-cap"></i> Academic Information
            </button>
            <button class="tab" onclick="switchTab('security')">
                <i class="fas fa-lock"></i> Security
            </button>
        </div>

        <!-- Profile Information Tab -->
        <div id="profile-tab" class="tab-content active">
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-user-edit"></i>
                    Personal Information
                </h2>

                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Student ID</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['student_id']); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label>Birth Date</label>
                            <input type="date" name="birth_date" class="form-control" 
                                   value="<?php echo $student['birth_date']; ?>">
                        </div>

                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender" class="form-control">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($student['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($student['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($student['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['contact_number'] ?? ''); ?>" 
                                   placeholder="+63 XXX XXX XXXX">
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['address'] ?? ''); ?>" 
                                   placeholder="Complete Address">
                        </div>
                    </div>

                    <h3 style="margin: 30px 0 20px 0; color: #2c3e50;">
                        <i class="fas fa-user-friends"></i> Guardian Information
                    </h3>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Guardian Name</label>
                            <input type="text" name="guardian_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['guardian_name'] ?? ''); ?>" 
                                   placeholder="Full Name">
                        </div>

                        <div class="form-group">
                            <label>Guardian Contact Number</label>
                            <input type="text" name="guardian_contact" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['guardian_contact'] ?? ''); ?>" 
                                   placeholder="+63 XXX XXX XXXX">
                        </div>
                    </div>

                    <h3 style="margin: 30px 0 20px 0; color: #2c3e50;">
                        <i class="fas fa-phone-alt"></i> Emergency Contact
                    </h3>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Emergency Contact Name</label>
                            <input type="text" name="emergency_contact" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['emergency_contact'] ?? ''); ?>" 
                                   placeholder="Full Name">
                        </div>

                        <div class="form-group">
                            <label>Emergency Contact Number</label>
                            <input type="text" name="emergency_phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['emergency_phone'] ?? ''); ?>" 
                                   placeholder="+63 XXX XXX XXXX">
                        </div>
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Academic Information Tab -->
        <div id="academic-tab" class="tab-content">
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-graduation-cap"></i>
                    Academic Information
                </h2>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Program</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></div>
                    </div>

                    <!--<div class="info-item">
                        <div class="info-label">Program Code</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['program_code'] ?? 'N/A'); ?></div>
                    </div>-->

                    <div class="info-item">
                        <div class="info-label">Department</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Year Level</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['year_level'] ?? 'N/A'); ?> Year</div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Section</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['section_name'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Academic Year</div>
                        <div class="info-value">
                            <?php 
                            if ($student['year_start']) {
                                echo $student['year_start'] . '-' . $student['year_end'] . ' (' . ucfirst($student['semester']) . ')';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Student Type</div>
                        <div class="info-value"><?php echo htmlspecialchars(ucfirst($student['student_type'] ?? 'Regular')); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Student Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo strtolower($student['student_status'] ?? 'regular'); ?>">
                                <?php echo htmlspecialchars(ucfirst($student['student_status'] ?? 'Regular')); ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Admission Date</div>
                        <div class="info-value">
                            <?php echo $student['admission_date'] ? date('M d, Y', strtotime($student['admission_date'])) : 'N/A'; ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Degree Type</div>
                        <div class="info-value"><?php echo htmlspecialchars(ucfirst($student['degree_type'] ?? 'N/A')); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Account Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo strtolower($student['status']); ?>">
                                <?php echo htmlspecialchars(ucfirst($student['status'])); ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Current GPA</div>
                        <div class="info-value">
                            <?php 
                            if ($gpa_data['gpa']) {
                                echo number_format($gpa_data['gpa'], 2);
                            } else {
                                echo 'Not yet available';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <?php if ($gpa_data['graded_subjects'] > 0): ?>
                <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #495057;">
                        <i class="fas fa-chart-line"></i> Academic Performance
                    </h4>
                    <p style="margin: 0; color: #6b7280;">
                        Based on <strong><?php echo $gpa_data['graded_subjects']; ?></strong> graded subject(s), 
                        your current GPA is <strong><?php echo number_format($gpa_data['gpa'], 2); ?></strong>.
                        <?php
                        $gpa = $gpa_data['gpa'];
                        if ($gpa >= 1.00 && $gpa <= 1.25) {
                            echo ' You are performing excellently!';
                        } elseif ($gpa >= 1.26 && $gpa <= 1.75) {
                            echo ' You are doing very well!';
                        } elseif ($gpa >= 1.76 && $gpa <= 2.25) {
                            echo ' You are maintaining good performance!';
                        } elseif ($gpa >= 2.26 && $gpa <= 2.75) {
                            echo ' Your performance is satisfactory.';
                        } elseif ($gpa >= 2.76 && $gpa <= 3.00) {
                            echo ' You are passing your subjects.';
                        } else {
                            echo ' Please work on improving your grades.';
                        }
                        ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security-tab" class="tab-content">
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-lock"></i>
                    Change Password
                </h2>

                <form method="POST" action="" onsubmit="return validatePasswordForm()">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div style="max-width: 500px;">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" id="current_password" 
                                   class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" id="new_password" 
                                   class="form-control" required minlength="8">
                            <small style="color: #6b7280; font-size: 0.85em;">
                                Password must be at least 8 characters long
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   class="form-control" required>
                        </div>

                        <div style="margin-top: 25px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    Account Security
                </h2>

                <div style="padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
                    <h4 style="margin: 0 0 15px 0; color: #2c3e50;">
                        <i class="fas fa-info-circle"></i> Security Tips
                    </h4>
                    <ul style="margin: 0; padding-left: 20px; color: #495057; line-height: 1.8;">
                        <li>Use a strong password with a mix of letters, numbers, and symbols</li>
                        <li>Never share your password with anyone</li>
                        <li>Change your password regularly</li>
                        <li>Log out from shared or public computers</li>
                        <li>Be cautious of phishing emails and suspicious links</li>
                    </ul>
                </div>

                <div style="margin-top: 20px; padding: 20px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
                    <h4 style="margin: 0 0 10px 0; color: #856404;">
                        <i class="fas fa-exclamation-triangle"></i> Account Information
                    </h4>
                    <p style="margin: 0; color: #856404;">
                        <strong>Username:</strong> <?php echo htmlspecialchars($student['username']); ?><br>
                        <strong>Account Created:</strong> <?php echo date('M d, Y', strtotime($student['created_at'])); ?><br>
                        <strong>Last Updated:</strong> <?php echo date('M d, Y g:i A', strtotime($student['updated_at'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-bolt"></i>
                Quick Actions
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="student_dashboard.php" class="btn btn-primary" style="text-decoration: none; text-align: center;">
                    <i class="fas fa-tachometer-alt"></i> View Dashboard
                </a>
                <a href="student_pre_enrollment.php" class="btn btn-primary" style="text-decoration: none; text-align: center;">
                    <i class="fas fa-file-alt"></i> Pre-Enrollment
                </a>
                <a href="appeal_grade.php" class="btn btn-primary" style="text-decoration: none; text-align: center;">
                    <i class="fas fa-exclamation-triangle"></i> Grade Appeal
                </a>
                <a href="../auth/student_logout.php" class="btn btn-secondary" style="text-decoration: none; text-align: center;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <script>
        // User dropdown functionality
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

        // Tab switching
        function switchTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');

            // Add active class to selected tab
            event.target.classList.add('active');
        }

        // Password validation
        function validatePasswordForm() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword.length < 8) {
                alert('New password must be at least 8 characters long.');
                return false;
            }

            if (newPassword !== confirmPassword) {
                alert('New passwords do not match.');
                return false;
            }

            return confirm('Are you sure you want to change your password?');
        }

        // Form change detection
        let formChanged = false;
        const formInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="date"], select, textarea');
        
        formInputs.forEach(input => {
            input.addEventListener('change', function() {
                formChanged = true;
            });
        });

        // Warn before leaving if form changed
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Reset form changed flag on submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                formChanged = false;
            });
        });

        // Auto-save draft functionality (optional)
        let autoSaveTimer;
        
        function autoSaveDraft() {
            const formData = new FormData(document.querySelector('form'));
            const draftData = {};
            
            for (let [key, value] of formData.entries()) {
                draftData[key] = value;
            }
            
            localStorage.setItem('profileDraft', JSON.stringify(draftData));
            console.log('Draft saved');
        }

        function loadDraft() {
            const draft = localStorage.getItem('profileDraft');
            if (draft) {
                const draftData = JSON.parse(draft);
                for (let key in draftData) {
                    const input = document.querySelector(`[name="${key}"]`);
                    if (input && input.value === '') {
                        input.value = draftData[key];
                    }
                }
            }
        }

        // Load draft on page load
        window.addEventListener('load', function() {
            loadDraft();
        });

        // Schedule auto-save
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(autoSaveDraft, 2000);
            });
        });

        // Clear draft on successful submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                localStorage.removeItem('profileDraft');
            });
        });

        // Phone number formatting
        const phoneInputs = document.querySelectorAll('input[name="contact_number"], input[name="guardian_contact"], input[name="emergency_phone"]');
        
        phoneInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 0 && !value.startsWith('63')) {
                    if (value.startsWith('0')) {
                        value = '63' + value.substring(1);
                    }
                }
                
                // Format: +63 XXX XXX XXXX
                if (value.length > 2) {
                    value = '+' + value.substring(0, 2) + ' ' + 
                            value.substring(2, 5) + ' ' + 
                            value.substring(5, 8) + ' ' + 
                            value.substring(8, 12);
                }
                
                e.target.value = value.trim();
            });
        });

        // Email validation
        const emailInput = document.querySelector('input[name="email"]');
        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                const email = this.value;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailRegex.test(email)) {
                    this.style.borderColor = '#dc3545';
                    alert('Please enter a valid email address.');
                } else {
                    this.style.borderColor = '#e9ecef';
                }
            });
        }

        // Character counter for text inputs (optional)
        function addCharacterCounter(input, maxLength) {
            const counter = document.createElement('small');
            counter.style.cssText = 'display: block; text-align: right; color: #6b7280; margin-top: 5px;';
            input.parentNode.appendChild(counter);
            
            function updateCounter() {
                const remaining = maxLength - input.value.length;
                counter.textContent = `${input.value.length}/${maxLength} characters`;
                if (remaining < 20) {
                    counter.style.color = '#dc3545';
                } else {
                    counter.style.color = '#6b7280';
                }
            }
            
            input.addEventListener('input', updateCounter);
            updateCounter();
        }

        // Smooth scroll to top when switching tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        // Print profile functionality
        function printProfile() {
            window.print();
        }

        // Export profile data
        function exportProfile() {
            const profileData = {
                personalInfo: {
                    firstName: "<?php echo addslashes($student['first_name']); ?>",
                    lastName: "<?php echo addslashes($student['last_name']); ?>",
                    studentId: "<?php echo addslashes($student['student_id']); ?>",
                    email: "<?php echo addslashes($student['email']); ?>",
                    contactNumber: "<?php echo addslashes($student['contact_number'] ?? ''); ?>"
                },
                academicInfo: {
                    program: "<?php echo addslashes($student['program_name'] ?? ''); ?>",
                    yearLevel: "<?php echo addslashes($student['year_level'] ?? ''); ?>",
                    section: "<?php echo addslashes($student['section_name'] ?? ''); ?>",
                    gpa: "<?php echo $gpa_data['gpa'] ? number_format($gpa_data['gpa'], 2) : 'N/A'; ?>"
                }
            };
            
            const dataStr = JSON.stringify(profileData, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
            
            const exportFileDefaultName = 'profile_<?php echo $student['student_id']; ?>.json';
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const activeTab = document.querySelector('.tab-content.active form');
                if (activeTab) {
                    activeTab.submit();
                }
            }
        });

        // Check for unsaved changes
        window.onbeforeunload = function() {
            if (formChanged) {
                return "You have unsaved changes. Are you sure you want to leave?";
            }
        };
    </script>
</body>
</html>