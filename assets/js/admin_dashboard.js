// ISATU Student Kiosk System - Admin Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    setupEventListeners();
    updateDashboardData();
    startAutoRefresh();
});

// Initialize Dashboard
function initializeDashboard() {
    console.log('ISATU Admin Dashboard initialized');
    
    // Add loading animation to stat cards
    animateStatCards();
    
    // Set active menu item
    setActiveMenuItem();
    
    // Initialize tooltips
    initializeTooltips();
}

// Setup Event Listeners
function setupEventListeners() {
    // Sidebar toggle for mobile
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !event.target.matches('.sidebar-toggle')) {
                sidebar.classList.remove('open');
            }
        }
    });
    
    // Stat card hover effects
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Activity refresh button
    const refreshBtn = document.querySelector('.refresh-activities');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshActivities);
    }
    
    // Alert close buttons
    const alertCloseButtons = document.querySelectorAll('.alert-close');
    alertCloseButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.alert').style.display = 'none';
        });
    });
}

// Animate stat cards on load
function animateStatCards() {
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
            
            // Animate numbers
            const numberElement = card.querySelector('h3');
            if (numberElement) {
                animateNumber(numberElement);
            }
        }, index * 100);
    });
}

// Animate numbers counting up
function animateNumber(element) {
    const finalNumber = parseInt(element.textContent);
    const duration = 2000;
    const increment = finalNumber / (duration / 50);
    let currentNumber = 0;
    
    const timer = setInterval(() => {
        currentNumber += increment;
        if (currentNumber >= finalNumber) {
            element.textContent = finalNumber;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(currentNumber);
        }
    }, 50);
}

// Set active menu item
function setActiveMenuItem() {
    const currentPage = window.location.pathname.split('/').pop();
    const menuItems = document.querySelectorAll('.sidebar-menu a');
    
    menuItems.forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('href') === currentPage || 
            (currentPage === 'admin_dashboard.php' && item.textContent.trim() === 'Dashboard')) {
            item.classList.add('active');
        }
    });
}

// Initialize tooltips
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

// Show tooltip
function showTooltip(event) {
    const tooltipText = event.target.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    document.body.appendChild(tooltip);
    
    const rect = event.target.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
    tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
}

// Hide tooltip
function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Update Dashboard Data
function updateDashboardData() {
    // This would typically fetch real-time data from the server
    console.log('Updating dashboard data...');
    
    // Example of updating activity feed
    updateActivityFeed();
    
    // Update system alerts
    updateSystemAlerts();
    
    // Update last updated time
    updateLastUpdateTime();
}

// Update activity feed
function updateActivityFeed() {
    const activityList = document.querySelector('.activity-list');
    if (!activityList) return;
    
    // This would typically fetch from an API endpoint
    // For now, we'll just add a visual update indicator
    const activities = activityList.querySelectorAll('.activity-item');
    activities.forEach(activity => {
        activity.style.transition = 'all 0.3s ease';
    });
}

// Update system alerts
function updateSystemAlerts() {
    const alertsList = document.querySelector('.alerts-list');
    if (!alertsList) return;
    
    // Check for new alerts and update the display
    fetch('api/get_alerts.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAlerts(data.alerts);
            }
        })
        .catch(error => {
            console.log('Error fetching alerts:', error);
        });
}

// Display alerts
function displayAlerts(alerts) {
    const alertsList = document.querySelector('.alerts-list');
    if (!alertsList || !alerts) return;
    
    // Clear existing alerts
    alertsList.innerHTML = '';
    
    alerts.forEach(alert => {
        const alertElement = createAlertElement(alert);
        alertsList.appendChild(alertElement);
    });
}

// Create alert element
function createAlertElement(alert) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${alert.type}`;
    
    alertDiv.innerHTML = `
        <i class="fas fa-${getAlertIcon(alert.type)}"></i>
        <p>${alert.message}</p>
        <button class="alert-close" onclick="dismissAlert(${alert.id})">&times;</button>
    `;
    
    return alertDiv;
}

// Get alert icon based on type
function getAlertIcon(type) {
    const icons = {
        'warning': 'exclamation-triangle',
        'info': 'info-circle',
        'success': 'check-circle',
        'danger': 'times-circle'
    };
    return icons[type] || 'info-circle';
}

// Dismiss alert
function dismissAlert(alertId) {
    fetch('api/dismiss_alert.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ alert_id: alertId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove alert from DOM
            const alertElement = document.querySelector(`[data-alert-id="${alertId}"]`);
            if (alertElement) {
                alertElement.style.transition = 'all 0.3s ease';
                alertElement.style.opacity = '0';
                alertElement.style.transform = 'translateX(100%)';
                setTimeout(() => alertElement.remove(), 300);
            }
        }
    });
}

// Update last update time
function updateLastUpdateTime() {
    const timeElement = document.querySelector('.last-update-time');
    if (timeElement) {
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        timeElement.textContent = `Last updated: ${timeString}`;
    }
}

// Start auto refresh
function startAutoRefresh() {
    // Refresh dashboard data every 30 seconds
    setInterval(() => {
        updateDashboardData();
    }, 30000);
    
    // Update time every second
    setInterval(() => {
        updateClock();
    }, 1000);
}

// Update clock
function updateClock() {
    const clockElement = document.querySelector('.current-time');
    if (clockElement) {
        const now = new Date();
        const timeString = now.toLocaleString();
        clockElement.textContent = timeString;
    }
}

// Refresh activities manually
function refreshActivities() {
    const refreshBtn = document.querySelector('.refresh-activities');
    if (refreshBtn) {
        refreshBtn.style.animation = 'spin 1s linear';
        setTimeout(() => {
            refreshBtn.style.animation = '';
        }, 1000);
    }
    
    // Fetch latest activities
    fetch('api/get_activities.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateActivityDisplay(data.activities);
            }
        })
        .catch(error => {
            console.log('Error fetching activities:', error);
        });
}

// Update activity display
function updateActivityDisplay(activities) {
    const activityList = document.querySelector('.activity-list');
    if (!activityList || !activities) return;
    
    // Clear existing activities
    activityList.innerHTML = '';
    
    activities.forEach(activity => {
        const activityElement = createActivityElement(activity);
        activityList.appendChild(activityElement);
    });
}

// Create activity element
function createActivityElement(activity) {
    const activityDiv = document.createElement('div');
    activityDiv.className = 'activity-item';
    
    activityDiv.innerHTML = `
        <i class="fas fa-${getActivityIcon(activity.type)}"></i>
        <div class="activity-details">
            <p>${activity.description}</p>
            <small>${formatTimeAgo(activity.created_at)}</small>
        </div>
    `;
    
    return activityDiv;
}

// Get activity icon
function getActivityIcon(type) {
    const icons = {
        'user_registration': 'user-plus',
        'grade_appeal': 'file-alt',
        'course_added': 'book',
        'enrollment': 'user-graduate',
        'system': 'cog'
    };
    return icons[type] || 'info-circle';
}

// Format time ago
function formatTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diffInSeconds = Math.floor((now - time) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
    return `${Math.floor(diffInSeconds / 86400)} days ago`;
}

// Handle window resize
window.addEventListener('resize', function() {
    handleResponsiveLayout();
});

// Handle responsive layout
function handleResponsiveLayout() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (window.innerWidth <= 768) {
        sidebar.classList.remove('open');
        mainContent.style.marginLeft = '0';
    } else {
        sidebar.classList.remove('open');
        mainContent.style.marginLeft = '280px';
    }
}

// Export functions for global use
window.dismissAlert = dismissAlert;
window.refreshActivities = refreshActivities;

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .tooltip {
        position: absolute;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 1000;
        pointer-events: none;
    }
    
    .tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: rgba(0, 0, 0, 0.8) transparent transparent transparent;
    }
    
    .alert-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        padding: 0;
        margin-left: auto;
        opacity: 0.7;
        transition: opacity 0.3s ease;
    }
    
    .alert-close:hover {
        opacity: 1;
    }
    
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .pulse {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .loading {
        position: relative;
        overflow: hidden;
    }
    
    .loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: loading 1.5s infinite;
    }
    
    @keyframes loading {
        0% { left: -100%; }
        100% { left: 100%; }
    }
`;

document.head.appendChild(style);

// Initialize dashboard when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDashboard);
} else {
    initializeDashboard();
}