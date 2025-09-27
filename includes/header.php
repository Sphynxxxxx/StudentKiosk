<?php
// Get current page name for dynamic header content
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Define page titles and descriptions
$page_info = [
    'admin_dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'description' => 'Welcome to the ISATU Kiosk System Administration Panel'
    ],
    'manage_faculty' => [
        'title' => 'Manage Faculty',
        'icon' => 'fas fa-chalkboard-teacher',
        'description' => 'Add, edit, and manage faculty members'
    ],
    'manage_student' => [
        'title' => 'Manage Students',
        'icon' => 'fas fa-user-graduate',
        'description' => 'Add, edit, and manage student records'
    ],
    'manage_pre_enrollment' => [
        'title' => 'Manage Pre-Enrollment',
        'icon' => 'fas fa-sync-alt',
        'description' => 'Accept or reject student pre-enrollment requests'
    ],
    'manage_grades' => [
        'title' => 'Manage Grades',
        'icon' => 'fas fa-clipboard-list',
        'description' => 'View and manage student grades'
    ],
    'generate_report' => [
        'title' => 'Generate Reports',
        'icon' => 'fas fa-chart-bar',
        'description' => 'Create and download system reports'
    ],
    'appeal_reports' => [
        'title' => 'Appeal Reports',
        'icon' => 'fas fa-exclamation-triangle',
        'description' => 'Review and manage grade appeals'
    ],
    'rankings' => [
        'title' => 'Rankings',
        'icon' => 'fas fa-trophy',
        'description' => 'View student rankings and academic performance'
    ],
    'manage_subjects' => [
        'title' => 'Manage Subjects',
        'icon' => 'fas fa-book',
        'description' => 'Add, edit, and manage subjects offerings'
    ],
    'manage_departments' => [
        'title' => 'Colleges & Courses',
        'icon' => 'fas fa-building',
        'description' => 'Manage academic departments'
    ],
    'academic_years' => [
        'title' => 'Academic Years',
        'icon' => 'fas fa-calendar-alt',
        'description' => 'Manage academic year settings'
    ],
    'system_settings' => [
        'title' => 'System Settings',
        'icon' => 'fas fa-cog',
        'description' => 'Configure system preferences and settings'
    ],
    'user_logs' => [
        'title' => 'User Logs',
        'icon' => 'fas fa-history',
        'description' => 'View system activity and user logs'
    ],
    'profile' => [
        'title' => 'Profile',
        'icon' => 'fas fa-user',
        'description' => 'Manage your account settings'
    ]
];

// Get current page info or default
$current_info = $page_info[$current_page] ?? [
    'title' => 'Administration',
    'icon' => 'fas fa-cog',
    'description' => 'ISATU Kiosk System Administration'
];
?>

<div class="dashboard-header">
    <!-- Mobile menu toggle button -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="header-content">
        <h1>
            <i class="<?php echo $current_info['icon']; ?>"></i> 
            <?php echo htmlspecialchars($current_info['title']); ?>
        </h1>
        <p><?php echo htmlspecialchars($current_info['description']); ?></p>
    </div>
    
    <div class="header-actions">
        <!-- Notifications -->
        <div class="notification-dropdown">
            <button class="notification-btn" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">3</span>
            </button>
            <div class="notification-dropdown-content" id="notificationDropdown">
                <div class="notification-header">
                    <h4>Notifications</h4>
                </div>
                <div class="notification-item">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    <div>
                        <strong>New Grade Appeal</strong>
                        <small>2 minutes ago</small>
                    </div>
                </div>
                <div class="notification-item">
                    <i class="fas fa-user-plus text-success"></i>
                    <div>
                        <strong>New Faculty Registration</strong>
                        <small>15 minutes ago</small>
                    </div>
                </div>
                <div class="notification-item">
                    <i class="fas fa-file-alt text-info"></i>
                    <div>
                        <strong>Report Generated</strong>
                        <small>1 hour ago</small>
                    </div>
                </div>
                <div class="notification-footer">
                    <a href="#" class="view-all">View All Notifications</a>
                </div>
            </div>
        </div>
        
        <!-- User dropdown -->
        <div class="user-dropdown">
            <button class="user-btn" onclick="toggleUserMenu()">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-info">
                    <span class="user-name">Administrator</span>
                    <small class="user-role">System Admin</small>
                </div>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="user-dropdown-content" id="userDropdown">
                <a href="profile.php">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="system_settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 100;
}

.sidebar-toggle {
    display: none;
    background: var(--primary-blue);
    color: white;
    border: none;
    padding: 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1.2rem;
    transition: all 0.2s ease;
}

.sidebar-toggle:hover {
    background: var(--secondary-blue);
    transform: scale(1.05);
}

.header-content h1 {
    margin: 0;
    color: var(--primary-blue);
    font-size: 1.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.header-content p {
    margin: 0.5rem 0 0 0;
    color: #6b7280;
    font-size: 0.95rem;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

/* Notifications */
.notification-dropdown {
    position: relative;
}

.notification-btn {
    position: relative;
    background: none;
    border: none;
    color: #6b7280;
    font-size: 1.3rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.notification-btn:hover {
    color: var(--primary-blue);
    background: #f3f4f6;
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    font-weight: bold;
    padding: 0.2rem 0.4rem;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
}

.notification-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: white;
    min-width: 320px;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    border: 1px solid #e5e7eb;
    z-index: 1000;
    margin-top: 0.5rem;
}

.notification-dropdown-content.show {
    display: block;
}

.notification-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.notification-header h4 {
    margin: 0;
    color: var(--primary-blue);
    font-size: 1.1rem;
}

.notification-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f3f4f6;
    transition: background 0.2s ease;
}

.notification-item:hover {
    background: #f9fafb;
}

.notification-item:last-of-type {
    border-bottom: none;
}

.notification-item i {
    font-size: 1.2rem;
    width: 20px;
    text-align: center;
}

.notification-item div {
    flex: 1;
}

.notification-item strong {
    display: block;
    color: #374151;
    font-size: 0.9rem;
}

.notification-item small {
    color: #6b7280;
    font-size: 0.8rem;
}

.notification-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
    text-align: center;
}

.view-all {
    color: var(--primary-blue);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

.view-all:hover {
    text-decoration: underline;
}

/* User dropdown */
.user-dropdown {
    position: relative;
}

.user-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: none;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.user-btn:hover {
    background: #f3f4f6;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: var(--primary-blue);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
}

.user-name {
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}

.user-role {
    color: #6b7280;
    font-size: 0.8rem;
}

.user-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: white;
    min-width: 200px;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    border: 1px solid #e5e7eb;
    z-index: 1000;
    margin-top: 0.5rem;
}

.user-dropdown-content.show {
    display: block;
}

.user-dropdown-content a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    color: #374151;
    text-decoration: none;
    transition: background 0.2s ease;
    font-size: 0.9rem;
}

.user-dropdown-content a:hover {
    background: #f9fafb;
}

.user-dropdown-content a.logout-link {
    color: #dc2626;
}

.user-dropdown-content a.logout-link:hover {
    background: #fef2f2;
}

.dropdown-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 0.5rem 0;
}

.text-warning { color: #f59e0b !important; }
.text-success { color: #10b981 !important; }
.text-info { color: #3b82f6 !important; }

/* Mobile responsive */
@media (max-width: 768px) {
    .sidebar-toggle {
        display: block;
    }
    
    .dashboard-header {
        padding: 1rem;
    }
    
    .header-content h1 {
        font-size: 1.5rem;
    }
    
    .header-content p {
        font-size: 0.85rem;
    }
    
    .user-info {
        display: none;
    }
    
    .notification-dropdown-content,
    .user-dropdown-content {
        min-width: 280px;
    }
}

@media (max-width: 480px) {
    .header-actions {
        gap: 0.5rem;
    }
    
    .user-btn {
        padding: 0.5rem;
    }
    
    .notification-dropdown-content,
    .user-dropdown-content {
        min-width: 250px;
        right: -50px;
    }
}
</style>

<script>
// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay') || createOverlay();
    
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
}

// Create overlay for mobile sidebar
function createOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.onclick = toggleSidebar;
    document.body.appendChild(overlay);
    return overlay;
}

// Toggle notifications dropdown
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    const userDropdown = document.getElementById('userDropdown');
    
    // Close user dropdown if open
    userDropdown.classList.remove('show');
    
    dropdown.classList.toggle('show');
}

// Toggle user menu dropdown
function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    // Close notification dropdown if open
    notificationDropdown.classList.remove('show');
    
    dropdown.classList.toggle('show');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.notification-dropdown')) {
        document.getElementById('notificationDropdown').classList.remove('show');
    }
    
    if (!event.target.closest('.user-dropdown')) {
        document.getElementById('userDropdown').classList.remove('show');
    }
});

// Add overlay styles for mobile sidebar
const overlayStyles = `
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}

.sidebar-overlay.show {
    display: block;
}

@media (max-width: 768px) {
    .sidebar.show {
        transform: translateX(0);
    }
}
`;

// Inject overlay styles
const styleSheet = document.createElement('style');
styleSheet.textContent = overlayStyles;
document.head.appendChild(styleSheet);
</script>