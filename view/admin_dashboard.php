<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$pdo = $database->connect();

// Get dashboard statistics
try {
    // Count total students
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
    $stmt->execute();
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count total faculty
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'faculty'");
    $stmt->execute();
    $totalFaculty = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count total courses
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM courses");
    $stmt->execute();
    $totalCourses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count total departments
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM departments");
    $stmt->execute();
    $totalDepartments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count pending appeals
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM grade_appeals WHERE status = 'pending'");
    $stmt->execute();
    $pendingAppeals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count active enrollments
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM enrollments WHERE status = 'enrolled'");
    $stmt->execute();
    $activeEnrollments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

} catch(PDOException $e) {
    $totalStudents = $totalFaculty = $totalCourses = $totalDepartments = $pendingAppeals = $activeEnrollments = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Dashboard - ISATU Student Kiosk System</title>
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Administrator Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>!</p>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $totalStudents; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon faculty">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $totalFaculty; ?></h3>
                            <p>Total Faculty</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon courses">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $totalCourses; ?></h3>
                            <p>Total Courses</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon departments">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $totalDepartments; ?></h3>
                            <p>Departments</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon enrollments">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $activeEnrollments; ?></h3>
                            <p>Active Enrollments</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon appeals">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $pendingAppeals; ?></h3>
                            <p>Pending Appeals</p>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Sections -->
                <div class="dashboard-sections">
                    <!-- Large Section -->
                    <div class="large-section">
                        <div class="section-header">
                            <h2>System Overview</h2>
                        </div>
                        <div class="section-content">
                            <div class="overview-grid">
                                <div class="overview-item">
                                    <h4>Current Academic Year</h4>
                                    <p>2024-2025 (1st Semester)</p>
                                </div>
                                <div class="overview-item">
                                    <h4>System Status</h4>
                                    <span class="status-badge active">Active</span>
                                </div>
                                <div class="overview-item">
                                    <h4>Last Backup</h4>
                                    <p><?php echo date('M d, Y H:i'); ?></p>
                                </div>
                            </div>
                            
                            <div class="quick-actions">
                                <h4>Quick Actions</h4>
                                <div class="action-buttons">
                                    <a href="manage_users.php" class="btn btn-primary">
                                        <i class="fas fa-users"></i> Manage Users
                                    </a>
                                    <a href="system_settings.php" class="btn btn-secondary">
                                        <i class="fas fa-cog"></i> System Settings
                                    </a>
                                    <a href="backup.php" class="btn btn-success">
                                        <i class="fas fa-database"></i> Backup Data
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Two smaller sections -->
                    <div class="small-sections">
                        <div class="small-section">
                            <div class="section-header">
                                <h3>Recent Activities</h3>
                            </div>
                            <div class="section-content">
                                <div class="activity-list">
                                    <div class="activity-item">
                                        <i class="fas fa-user-plus"></i>
                                        <div class="activity-details">
                                            <p>New student registered</p>
                                            <small>2 hours ago</small>
                                        </div>
                                    </div>
                                    <div class="activity-item">
                                        <i class="fas fa-file-alt"></i>
                                        <div class="activity-details">
                                            <p>Grade appeal submitted</p>
                                            <small>4 hours ago</small>
                                        </div>
                                    </div>
                                    <div class="activity-item">
                                        <i class="fas fa-book"></i>
                                        <div class="activity-details">
                                            <p>New course added</p>
                                            <small>1 day ago</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="small-section">
                            <div class="section-header">
                                <h3>System Alerts</h3>
                            </div>
                            <div class="section-content">
                                <div class="alerts-list">
                                    <?php if($pendingAppeals > 0): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <p><?php echo $pendingAppeals; ?> pending grade appeals</p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <p>System running normally</p>
                                    </div>
                                    
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i>
                                        <p>Database backup completed</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin_dashboard.js"></script>
</body>
</html>