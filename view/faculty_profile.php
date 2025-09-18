<?php
session_start();

// Check if faculty is logged in
if (!isset($_SESSION['faculty_id'])) {
    header('Location: ../faculty.php');
    exit();
}

// Include database configuration
require_once '../config/database.php';

$success_message = '';
$error_message = '';

try {
    // Get faculty information from users and faculty_profiles tables
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            fp.department_id,
            fp.position,
            fp.employment_type,
            fp.specialization,
            fp.education,
            fp.phone,
            fp.office_location,
            fp.consultation_hours,
            fp.hire_date,
            fp.biography,
            d.name as department_name,
            COUNT(DISTINCT cs.id) as total_courses,
            COUNT(DISTINCT e.student_id) as total_students
        FROM users u
        LEFT JOIN faculty_profiles fp ON u.id = fp.user_id
        LEFT JOIN departments d ON fp.department_id = d.id
        LEFT JOIN class_sections cs ON u.id = cs.faculty_id AND cs.status = 'active'
        LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
        WHERE u.id = ? AND u.role = 'faculty'
        GROUP BY u.id
    ");
    $stmt->execute([$_SESSION['faculty_id']]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$faculty) {
        throw new Exception("Faculty not found");
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['update_profile'])) {
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Update users table
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, email = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_SESSION['faculty_id']
                ]);
                
                // Check if faculty profile exists
                $stmt = $pdo->prepare("SELECT id FROM faculty_profiles WHERE user_id = ?");
                $stmt->execute([$_SESSION['faculty_id']]);
                $profile_exists = $stmt->fetch();
                
                if ($profile_exists) {
                    // Update existing faculty profile
                    $stmt = $pdo->prepare("
                        UPDATE faculty_profiles 
                        SET phone = ?, office_location = ?, consultation_hours = ?, 
                            specialization = ?, biography = ?, department_id = ?
                        WHERE user_id = ?
                    ");
                    
                    $stmt->execute([
                        $_POST['phone'],
                        $_POST['office_location'],
                        $_POST['consultation_hours'],
                        $_POST['specialization'],
                        $_POST['biography'],
                        !empty($_POST['department_id']) ? $_POST['department_id'] : null,
                        $_SESSION['faculty_id']
                    ]);
                } else {
                    // Insert new faculty profile
                    $stmt = $pdo->prepare("
                        INSERT INTO faculty_profiles 
                        (user_id, phone, office_location, consultation_hours, specialization, biography, department_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $_SESSION['faculty_id'],
                        $_POST['phone'],
                        $_POST['office_location'],
                        $_POST['consultation_hours'],
                        $_POST['specialization'],
                        $_POST['biography'],
                        !empty($_POST['department_id']) ? $_POST['department_id'] : null
                    ]);
                }
                
                $pdo->commit();
                $success_message = "Profile updated successfully!";
                
                // Refresh faculty data
                $stmt = $pdo->prepare("
                    SELECT 
                        u.*,
                        fp.department_id,
                        fp.position,
                        fp.employment_type,
                        fp.specialization,
                        fp.education,
                        fp.phone,
                        fp.office_location,
                        fp.consultation_hours,
                        fp.hire_date,
                        fp.biography,
                        d.name as department_name,
                        COUNT(DISTINCT cs.id) as total_courses,
                        COUNT(DISTINCT e.student_id) as total_students
                    FROM users u
                    LEFT JOIN faculty_profiles fp ON u.id = fp.user_id
                    LEFT JOIN departments d ON fp.department_id = d.id
                    LEFT JOIN class_sections cs ON u.id = cs.faculty_id AND cs.status = 'active'
                    LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
                    WHERE u.id = ? AND u.role = 'faculty'
                    GROUP BY u.id
                ");
                $stmt->execute([$_SESSION['faculty_id']]);
                $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $pdo->rollback();
                $error_message = "Error updating profile: " . $e->getMessage();
            }
            
        } elseif (isset($_POST['change_password'])) {
            // Handle password change
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Verify current password
            if (password_verify($current_password, $faculty['password'])) {
                if ($new_password === $confirm_password) {
                    if (strlen($new_password) >= 8) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $_SESSION['faculty_id']]);
                        $success_message = "Password changed successfully!";
                    } else {
                        $error_message = "Password must be at least 8 characters long.";
                    }
                } else {
                    $error_message = "New passwords do not match.";
                }
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    }

    // Get departments for dropdown
    $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Faculty profile error: " . $e->getMessage());
    $error_message = "An error occurred while loading profile information.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Profile - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            color: #2c3e50;
        }

        /* Header Styles */
        .header {
            background: #1e3a8a;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .header-text {
            display: flex;
            flex-direction: column;
        }

        .university-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.2rem;
        }

        .system-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fbbf24;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        /* Navigation */
        .nav-bar {
            background: #fbbf24;
            padding: 1rem 0;
        }

        .nav-content {
            padding: 0 2rem;
        }

        .nav-title {
            color: #1e3a8a;
            font-size: 1.3rem;
            font-weight: bold;
        }

        /* Main Container */
        .main-container {
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            align-items: start;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .card-header {
            background: #1e3a8a;
            color: white;
            padding: 1.5rem 2rem;
            border-bottom: 3px solid #fbbf24;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Profile Card */
        .profile-card {
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #1e3a8a;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1.5rem;
            border: 4px solid #fbbf24;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }

        .profile-role {
            color: #6b7280;
            margin-bottom: 1rem;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 6px;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e3a8a;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #1e3a8a;
        }

        .form-textarea {
            height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #1e3a8a;
            color: white;
        }

        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .tab.active {
            color: #1e3a8a;
            border-bottom-color: #1e3a8a;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Info Section */
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #374151;
        }

        .info-value {
            color: #6b7280;
            text-align: right;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-container {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
            
            .header-content {
                padding: 0 1rem;
            }
            
            .nav-content {
                padding: 0 1rem;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
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
                    <div class="system-title">FACULTY PROFILE</div>
                </div>
            </div>
            <a href="faculty_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav-bar">
        <div class="nav-content">
            <div class="nav-title">
                <i class="fas fa-user-cog"></i>
                Profile Management
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Profile Overview Card -->
        <div class="card profile-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-id-card"></i>
                    Profile Overview
                </h2>
            </div>
            <div class="card-body">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?></div>
                <div class="profile-role">Faculty Member</div>
                
                <div class="info-item">
                    <span class="info-label">Employee ID:</span>
                    <span class="info-value"><?php echo htmlspecialchars($faculty['employee_id']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Department:</span>
                    <span class="info-value"><?php echo htmlspecialchars($faculty['department_name'] ?? 'Not Assigned'); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Join Date:</span>
                    <span class="info-value"><?php echo date('M d, Y', strtotime($faculty['created_at'])); ?></span>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $faculty['total_courses']; ?></div>
                        <div class="stat-label">Active Courses</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $faculty['total_students']; ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Details Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-edit"></i>
                    Profile Settings
                </h2>
            </div>
            <div class="card-body">
                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="showTab('profile')">
                        <i class="fas fa-user"></i> Personal Information
                    </button>
                    <button class="tab" onclick="showTab('security')">
                        <i class="fas fa-lock"></i> Security
                    </button>
                </div>

                <!-- Profile Tab -->
                <div id="profile" class="tab-content active">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($faculty['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($faculty['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($faculty['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($faculty['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-input">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" 
                                                <?php echo ($faculty['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Office Location</label>
                                <input type="text" name="office_location" class="form-input" 
                                       value="<?php echo htmlspecialchars($faculty['office_location'] ?? ''); ?>"
                                       placeholder="e.g., Room 101, Building A">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Specialization</label>
                            <input type="text" name="specialization" class="form-input" 
                                   value="<?php echo htmlspecialchars($faculty['specialization'] ?? ''); ?>"
                                   placeholder="e.g., Computer Science, Mathematics, Engineering">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Consultation Hours</label>
                            <input type="text" name="consultation_hours" class="form-input" 
                                   value="<?php echo htmlspecialchars($faculty['consultation_hours'] ?? ''); ?>"
                                   placeholder="e.g., Monday-Friday 9:00 AM - 5:00 PM">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Biography</label>
                            <textarea name="biography" class="form-input form-textarea" 
                                      placeholder="Tell us about yourself, your experience, and interests..."><?php echo htmlspecialchars($faculty['biography'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Profile
                        </button>
                    </form>
                </div>

                <!-- Security Tab -->
                <div id="security" class="tab-content">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-input" 
                                   minlength="8" required>
                            <small style="color: #6b7280;">Password must be at least 8 characters long.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" 
                                   minlength="8" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-danger">
                            <i class="fas fa-key"></i>
                            Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
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
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Form validation for password change
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            if (newPassword && confirmPassword) {
                if (newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    return false;
                }
            }
        });

        // Auto-hide success messages after 5 seconds
        setTimeout(function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>