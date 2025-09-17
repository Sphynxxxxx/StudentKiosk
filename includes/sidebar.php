<?php
// Get current page name for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>Administrator</h2>
        <p>ISATU Kiosk System</p>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li>
            <a href="manage_faculty.php" class="<?php echo ($current_page == 'manage_faculty.php') ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Manage Faculty</span>
            </a>
        </li>
        
        <li>
            <a href="manage_student.php" class="<?php echo ($current_page == 'manage_student.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Manage Student</span>
            </a>
        </li>
        
        <li>
            <a href="manage_grades.php" class="<?php echo ($current_page == 'manage_grades.php') ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Manage Grades</span>
            </a>
        </li>
        
        <li>
            <a href="generate_report.php" class="<?php echo ($current_page == 'generate_report.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Generate Report</span>
            </a>
        </li>
        
        <li>
            <a href="appeal_reports.php" class="<?php echo ($current_page == 'appeal_reports.php') ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Appeal Reports</span>
            </a>
        </li>
        
        <li>
            <a href="rankings.php" class="<?php echo ($current_page == 'rankings.php') ? 'active' : ''; ?>">
                <i class="fas fa-trophy"></i>
                <span>Rankings</span>
            </a>
        </li>
        
        <li class="menu-divider"></li>
        
        <li>
            <a href="manage_courses.php" class="<?php echo ($current_page == 'manage_courses.php') ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>Manage Courses</span>
            </a>
        </li>
        
        <li>
            <a href="manage_departments.php" class="<?php echo ($current_page == 'manage_departments.php') ? 'active' : ''; ?>">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
        </li>
        
        <li>
            <a href="academic_years.php" class="<?php echo ($current_page == 'academic_years.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Academic Years</span>
            </a>
        </li>
        
        <li class="menu-divider"></li>
        
        <li>
            <a href="system_settings.php" class="<?php echo ($current_page == 'system_settings.php') ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>System Settings</span>
            </a>
        </li>
        
        <li>
            <a href="user_logs.php" class="<?php echo ($current_page == 'user_logs.php') ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>User Logs</span>
            </a>
        </li>
        
        <li class="menu-divider"></li>
        
        <li>
            <a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </li>
        
        <li>
            <a href="logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<style>
.menu-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.2);
    margin: 1rem 1.5rem;
    list-style: none;
}

.logout-link {
    color: #fbbf24 !important;
    margin-top: 1rem;
}

.logout-link:hover {
    background: rgba(251, 191, 36, 0.2) !important;
    color: #fbbf24 !important;
}

.sidebar-menu {
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

.sidebar-menu::-webkit-scrollbar {
    width: 4px;
}

.sidebar-menu::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
}

.sidebar-menu::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* Mobile toggle button */
@media (max-width: 768px) {
    .sidebar-toggle {
        display: block;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        background: var(--primary-blue);
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 5px;
        cursor: pointer;
        font-size: 1.2rem;
    }
    
    .sidebar-toggle:hover {
        background: var(--secondary-blue);
    }
}
</style>