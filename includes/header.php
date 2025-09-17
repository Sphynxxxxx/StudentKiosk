<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user information
$user_name = isset($_SESSION['first_name']) && isset($_SESSION['last_name']) 
    ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] 
    : 'Administrator';
$user_role = isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'Administrator';
$user_initials = isset($_SESSION['first_name']) && isset($_SESSION['last_name']) 
    ? strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1))
    : 'AD';

// Get current page title
$page_titles = [
    'admin_dashboard.php' => 'Administrator Dashboard',
    'manage_faculty.php' => 'Manage Faculty',
    'manage_student.php' => 'Manage Students',
    'manage_grades.php' => 'Manage Grades',
    'generate_report.php' => 'Generate Reports',
    'appeal_reports.php' => 'Grade Appeal Reports',
    'rankings.php' => 'Student Rankings',
    'manage_courses.php' => 'Manage Courses',
    'manage_departments.php' => 'Manage Departments',
    'academic_years.php' => 'Academic Years',
    'system_settings.php' => 'System Settings',
    'user_logs.php' => 'User Activity Logs',
    'profile.php' => 'User Profile'
];

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'ISATU Kiosk System';
?>

<div class="header">
    <!-- Mobile menu toggle -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="header-left">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
        <div class="breadcrumb">
            <span class="current-time" id="currentTime"></span>
        </div>
    </div>
    
    <div class="header-right">
        <!-- Notifications -->
        <div class="notifications-container">
            <button class="notification-btn" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationCount">3</span>
            </button>
            
            <div class="notifications-dropdown" id="notificationsDropdown">
                <div class="notifications-header">
                    <h4>Notifications</h4>
                    <button class="mark-all-read" onclick="markAllAsRead()">Mark all as read</button>
                </div>
                
                <div class="notifications-footer">
                    <a href="all_notifications.php">View all notifications</a>
                </div>
            </div>
        </div>
        
        <!-- User Profile -->
        <div class="user-profile" onclick="toggleUserMenu()">
            <div class="user-avatar">
                <?php echo htmlspecialchars($user_initials); ?>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($user_name); ?></h4>
                <p><?php echo htmlspecialchars($user_role); ?></p>
            </div>
            <i class="fas fa-chevron-down user-dropdown-arrow"></i>
            
            <!-- User Dropdown Menu -->
            <div class="user-dropdown-menu" id="userDropdownMenu">
                <div class="user-dropdown-header">
                    <div class="user-avatar-large">
                        <?php echo htmlspecialchars($user_initials); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($user_name); ?></h4>
                        <p><?php echo htmlspecialchars($user_role); ?></p>
                        <small><?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?></small>
                    </div>
                </div>
                
                <div class="user-dropdown-divider"></div>
                
                <ul class="user-dropdown-list">
                    <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="account_settings.php"><i class="fas fa-cog"></i> Account Settings</a></li>
                    <li><a href="activity_log.php"><i class="fas fa-history"></i> Activity Log</a></li>
                    <li><a href="help.php"><i class="fas fa-question-circle"></i> Help & Support</a></li>
                </ul>
                
                <div class="user-dropdown-divider"></div>
                
                <div class="user-dropdown-footer">
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update current time
function updateCurrentTime() {
    const now = new Date();
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    };
    document.getElementById('currentTime').textContent = now.toLocaleDateString('en-US', options);
}

// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('open');
}

// Toggle notifications dropdown
function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    dropdown.classList.toggle('show');
    
    // Close user menu if open
    const userMenu = document.getElementById('userDropdownMenu');
    userMenu.classList.remove('show');
}

// Toggle user dropdown menu
function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdownMenu');
    dropdown.classList.toggle('show');
    
    // Close notifications if open
    const notifications = document.getElementById('notificationsDropdown');
    notifications.classList.remove('show');
}

// Mark all notifications as read
function markAllAsRead() {
    const notifications = document.querySelectorAll('.notification-item.unread');
    notifications.forEach(notification => {
        notification.classList.remove('unread');
    });
    
    // Update notification count
    document.getElementById('notificationCount').textContent = '0';
    document.getElementById('notificationCount').style.display = 'none';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const notificationsBtn = document.querySelector('.notification-btn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const userProfile = document.querySelector('.user-profile');
    const userDropdown = document.getElementById('userDropdownMenu');
    
    // Close notifications dropdown
    if (!notificationsBtn.contains(event.target) && !notificationsDropdown.contains(event.target)) {
        notificationsDropdown.classList.remove('show');
    }
    
    // Close user dropdown
    if (!userProfile.contains(event.target)) {
        userDropdown.classList.remove('show');
    }
});

// Initialize
updateCurrentTime();
setInterval(updateCurrentTime, 1000);
</script>

<style>
.header {
    position: relative;
}

.sidebar-toggle {
    display: none;
    background: var(--primary-blue);
    color: white;
    border: none;
    padding: 0.5rem;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
}

.breadcrumb {
    margin-top: 0.25rem;
}

.current-time {
    color: var(--gray);
    font-size: 0.9rem;
    font-weight: 500;
}

.header-right {
    position: relative;
}

/* Notifications */
.notifications-container {
    position: relative;
}

.notification-btn {
    background: none;
    border: none;
    color: var(--gray);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    position: relative;
    transition: all 0.3s ease;
}

.notification-btn:hover {
    background: var(--light-gray);
    color: var(--primary-blue);
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--danger);
    color: white;
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
    border-radius: 50%;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notifications-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    width: 350px;
    max-height: 400px;
    overflow: hidden;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.notifications-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--primary-blue);
    color: white;
}

.notifications-header h4 {
    margin: 0;
    font-size: 1.1rem;
}

.mark-all-read {
    background: none;
    border: none;
    color: var(--golden-yellow);
    font-size: 0.8rem;
    cursor: pointer;
    text-decoration: underline;
}

.notifications-list {
    max-height: 250px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.2s ease;
}

.notification-item:hover {
    background: var(--light-gray);
}

.notification-item.unread {
    background: var(--light-yellow);
    border-left: 3px solid var(--golden-yellow);
}

.notification-item i {
    font-size: 1.1rem;
    margin-top: 0.2rem;
}

.notification-content p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--dark-gray);
}

.notification-content small {
    color: var(--gray);
    font-size: 0.8rem;
}

.notifications-footer {
    padding: 0.75rem 1rem;
    text-align: center;
    border-top: 1px solid var(--border-color);
    background: var(--light-gray);
}

.notifications-footer a {
    color: var(--primary-blue);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

/* User Profile Dropdown */
.user-profile {
    position: relative;
    cursor: pointer;
}

.user-dropdown-arrow {
    font-size: 0.8rem;
    margin-left: 0.5rem;
    transition: transform 0.3s ease;
}

.user-profile:hover .user-dropdown-arrow {
    transform: rotate(180deg);
}

.user-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    width: 280px;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.user-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.user-dropdown-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--primary-blue);
    color: white;
    border-radius: 10px 10px 0 0;
}

.user-avatar-large {
    width: 50px;
    height: 50px;
    background: var(--golden-yellow);
    color: var(--primary-blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.user-details h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
}

.user-details p {
    margin: 0 0 0.25rem 0;
    font-size: 0.9rem;
    opacity: 0.9;
}

.user-details small {
    font-size: 0.8rem;
    opacity: 0.8;
}

.user-dropdown-divider {
    height: 1px;
    background: var(--border-color);
    margin: 0.5rem 0;
}

.user-dropdown-list {
    list-style: none;
    padding: 0.5rem 0;
    margin: 0;
}

.user-dropdown-list li {
    margin: 0;
}

.user-dropdown-list a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    color: var(--dark-gray);
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.user-dropdown-list a:hover {
    background: var(--light-gray);
    color: var(--primary-blue);
}

.user-dropdown-list i {
    width: 16px;
    text-align: center;
}

.user-dropdown-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background: var(--light-gray);
    border-radius: 0 0 10px 10px;
}

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.75rem;
    background: var(--danger);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.logout-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* Utility text colors */
.text-warning { color: var(--warning) !important; }
.text-success { color: var(--success) !important; }
.text-info { color: var(--secondary-blue) !important; }
.text-danger { color: var(--danger) !important; }

/* Mobile responsiveness */
@media (max-width: 768px) {
    .sidebar-toggle {
        display: block;
    }
    
    .header {
        padding: 1rem;
    }
    
    .header-left h1 {
        font-size: 1.5rem;
    }
    
    .user-info {
        display: none;
    }
    
    .notifications-dropdown,
    .user-dropdown-menu {
        width: 300px;
        right: -50px;
    }
    
    .current-time {
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .header-right {
        flex-direction: column;
        align-items: flex-end;
        gap: 0.5rem;
    }
    
    .notifications-dropdown,
    .user-dropdown-menu {
        width: 280px;
        right: -100px;
    }
    
    .header-left h1 {
        font-size: 1.3rem;
    }
    
    .user-profile {
        scale: 0.9;
    }
}