<?php
// Include database configuration
require_once '../config/database.php';

// Get statistics
$stats = [];

// Total users by role
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$user_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stats['total_students'] = $user_stats['student'] ?? 0;
$stats['total_faculty'] = $user_stats['faculty'] ?? 0;
$stats['total_admins'] = $user_stats['administrator'] ?? 0;

// Total subjects (instead of courses)
$stmt = $pdo->query("SELECT COUNT(*) FROM subjects");
$stats['total_subjects'] = $stmt->fetchColumn();

// Total departments
$stmt = $pdo->query("SELECT COUNT(*) FROM departments");
$stats['total_departments'] = $stmt->fetchColumn();

// Active academic year
$stmt = $pdo->query("SELECT CONCAT(year_start, '-', year_end, ' (', semester, ' Semester)') FROM academic_years WHERE is_active = 1 LIMIT 1");
$stats['active_year'] = $stmt->fetchColumn() ?: 'No active year';

// Recent enrollments (last 7 days)
$stmt = $pdo->query("SELECT COUNT(*) FROM enrollments WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['recent_enrollments'] = $stmt->fetchColumn();

// Pending grade appeals
$stmt = $pdo->query("SELECT COUNT(*) FROM grade_appeals WHERE status = 'pending'");
$stats['pending_appeals'] = $stmt->fetchColumn();

// Get recent activities
$recent_activities = [];

// Recent user registrations
$stmt = $pdo->prepare("
    SELECT 
        CONCAT(first_name, ' ', last_name) as name, 
        role, 
        created_at,
        'user_registered' as activity_type
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent grade appeals
$stmt = $pdo->prepare("
    SELECT 
        CONCAT(u.first_name, ' ', u.last_name) as name,
        ga.status,
        ga.submitted_at,
        'grade_appeal' as activity_type
    FROM grade_appeals ga
    JOIN users u ON ga.student_id = u.id
    ORDER BY ga.submitted_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_appeals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine activities
$recent_activities = array_merge($recent_users, $recent_appeals);
usort($recent_activities, function($a, $b) {
    $timeA = $a['created_at'] ?? $a['submitted_at'];
    $timeB = $b['created_at'] ?? $b['submitted_at'];
    return strtotime($timeB) - strtotime($timeA);
});
$recent_activities = array_slice($recent_activities, 0, 10);

// Get department statistics - Fixed query
$stmt = $pdo->prepare("
    SELECT 
        d.name,
        d.code,
        COUNT(DISTINCT s.id) as subject_count,
        COUNT(DISTINCT fp.user_id) as faculty_count
    FROM departments d
    LEFT JOIN subjects s ON d.id = s.department_id
    LEFT JOIN faculty_profiles fp ON d.id = fp.department_id
    GROUP BY d.id, d.name, d.code
    ORDER BY d.name
");
$stmt->execute();
$department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get program statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT p.id) as total_programs,
        COUNT(DISTINCT sec.id) as total_sections,
        COUNT(DISTINCT sp.id) as enrolled_students
    FROM programs p
    LEFT JOIN sections sec ON p.id = sec.program_id
    LEFT JOIN student_profiles sp ON p.id = sp.program_id
    WHERE p.status = 'active'
");
$stmt->execute();
$program_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin_dashboard.css" rel="stylesheet">
        
</head>
<body>
    <div class="admin-layout">
        
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="dashboard-content">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Active Academic Year:</strong> <?php echo htmlspecialchars($stats['active_year']); ?>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon primary">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total_students']); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-card-header">
                            <div class="stat-icon success">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total_faculty']); ?></div>
                        <div class="stat-label">Faculty Members</div>
                    </div>

                    <div class="stat-card warning">
                        <div class="stat-card-header">
                            <div class="stat-icon warning">
                                <i class="fas fa-book"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total_subjects']); ?></div>
                        <div class="stat-label">Available Subjects</div>
                    </div>

                    <div class="stat-card danger">
                        <div class="stat-card-header">
                            <div class="stat-icon danger">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['pending_appeals']); ?></div>
                        <div class="stat-label">Pending Appeals</div>
                    </div>
                </div>

                <div class="content-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-clock"></i> Recent Activities
                            </h3>
                        </div>
                        
                        <?php if (empty($recent_activities)): ?>
                            <div style="text-align: center; padding: 2rem; color: var(--gray-600);">
                                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No recent activities found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $activity['activity_type'] == 'user_registered' ? 'primary' : 'warning'; ?>">
                                        <i class="fas fa-<?php echo $activity['activity_type'] == 'user_registered' ? 'user-plus' : 'exclamation-triangle'; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?php if ($activity['activity_type'] == 'user_registered'): ?>
                                                New <?php echo ucfirst($activity['role']); ?>: <?php echo htmlspecialchars($activity['name']); ?>
                                            <?php else: ?>
                                                Grade Appeal <?php echo ucfirst($activity['status']); ?>: <?php echo htmlspecialchars($activity['name']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-time">
                                            <?php 
                                            $time = $activity['created_at'] ?? $activity['submitted_at'];
                                            echo date('M j, Y g:i A', strtotime($time)); 
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-building"></i> Departments
                            </h3>
                        </div>
                        
                        <?php if (empty($department_stats)): ?>
                            <div style="text-align: center; padding: 2rem; color: var(--gray-600);">
                                <i class="fas fa-building" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No departments found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($department_stats as $dept): ?>
                                <div class="department-item">
                                    <div class="department-info">
                                        <h4><?php echo htmlspecialchars($dept['name']); ?></h4>
                                        <small style="color: var(--gray-600);">
                                        </small>
                                    </div>
                                    <div class="department-stats">
                                        <div class="department-stat">
                                            <strong><?php echo $dept['subject_count']; ?></strong>
                                            <small>Subjects</small>
                                        </div>
                                        <div class="department-stat">
                                            <strong><?php echo $dept['faculty_count']; ?></strong>
                                            <small>Faculty</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i> System Overview
                        </h3>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-card primary">
                            <div class="stat-card-header">
                                <div class="stat-icon primary">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo number_format($program_stats['total_programs'] ?? 0); ?></div>
                            <div class="stat-label">Programs</div>
                        </div>

                        <div class="stat-card success">
                            <div class="stat-card-header">
                                <div class="stat-icon success">
                                    <i class="fas fa-building"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo number_format($stats['total_departments']); ?></div>
                            <div class="stat-label">Departments</div>
                        </div>

                        <div class="stat-card info">
                            <div class="stat-card-header">
                                <div class="stat-icon info">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo number_format($program_stats['total_sections'] ?? 0); ?></div>
                            <div class="stat-label">Sections</div>
                        </div>

                        <div class="stat-card warning">
                            <div class="stat-card-header">
                                <div class="stat-icon warning">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo number_format($stats['recent_enrollments']); ?></div>
                            <div class="stat-label">New Enrollments (7 days)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin_dashboard.js"></script>
</body>
</html>