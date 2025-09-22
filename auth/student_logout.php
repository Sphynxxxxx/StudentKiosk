<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: ../index.php');
    exit();
}

// Include database configuration
require_once '../config/database.php';

// Get student information for display
$student = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.*, sp.year_level, sp.program_id, p.program_name, p.program_code
        FROM users u 
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN programs p ON sp.program_id = p.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$_SESSION['student_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Logout page error: " . $e->getMessage());
}

// Handle logout action
if (isset($_POST['confirm_logout'])) {
    // Log the logout action
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['student_id'],
            'logout',
            'Student logged out',
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }

    // Destroy session and redirect
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    header('Location: ../index.php?logged_out=1');
    exit();
}

// Handle cancel action
if (isset($_POST['cancel_logout'])) {
    header('Location: student_dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - ISATU Student Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header Styles */
        .header {
            background: #1e3a8a;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .header-text {
            display: flex;
            flex-direction: column;
        }

        .university-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            letter-spacing: 0.5px;
        }

        .system-title {
            font-size: 0.9rem;
            font-weight: 500;
            color: #3b82f6;
            letter-spacing: 1px;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            font-weight: 500;
        }

        .user-info i {
            font-size: 1.2rem;
            color: #667eea;
        }

        /* Main Content */
        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .logout-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 500px;
            width: 100%;
            text-align: center;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-icon {
            font-size: 4rem;
            color: #1e3a8a;
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .logout-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .logout-message {
            font-size: 1.1rem;
            color: #7f8c8d;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .student-info {
            background: rgba(103, 126, 234, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin: 1.5rem 0;
            border-left: 4px solid #667eea;
        }

        .student-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .student-details {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .logout-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-logout {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }

        .btn-cancel:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
        }

        .session-info {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            font-size: 0.85rem;
            color: #95a5a6;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .university-name {
                font-size: 1rem;
            }

            .system-title {
                font-size: 0.8rem;
            }

            .logout-card {
                margin: 1rem;
                padding: 2rem;
            }

            .logout-title {
                font-size: 1.5rem;
            }

            .logout-actions {
                flex-direction: column;
            }

            .btn {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 1rem;
            }

            .logout-card {
                padding: 1.5rem;
            }

            .logout-icon {
                font-size: 3rem;
            }

            .logout-title {
                font-size: 1.3rem;
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
            <?php if ($student): ?>
            <div class="user-section">
                <div class="user-info">
                    <i class="fas fa-user-graduate"></i>
                    <span class="user-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-container">
        <div class="logout-card">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <h1 class="logout-title">Confirm Logout</h1>
            <p class="logout-message">Are you sure you want to sign out of your student portal account?</p>
            
            <?php if ($student): ?>
            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                <div class="student-details">
                    <?php if ($student['student_id']): ?>
                    Student ID: <?php echo htmlspecialchars($student['student_id']); ?>
                    <?php endif; ?>
                    <?php if ($student['program_name']): ?>
                    <br><?php echo htmlspecialchars($student['program_name']); ?>
                    <?php if ($student['year_level']): ?>
                    - <?php echo htmlspecialchars($student['year_level']); ?> Year
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="logout-actions">
                <button type="submit" name="confirm_logout" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Yes, Logout
                </button>
                <button type="submit" name="cancel_logout" class="btn btn-cancel">
                    <i class="fas fa-arrow-left"></i>
                    Cancel
                </button>
            </form>

            <div class="session-info">
                <i class="fas fa-clock"></i>
                Session started: <?php echo date('M d, Y g:i A', $_SESSION['login_time'] ?? time()); ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus on logout button for keyboard accessibility
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.querySelector('.btn-logout');
            if (logoutBtn) {
                logoutBtn.focus();
            }
        });

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelector('button[name="cancel_logout"]').click();
            }
            if (e.key === 'Enter' && e.ctrlKey) {
                document.querySelector('button[name="confirm_logout"]').click();
            }
        });

        // Add confirmation dialog for extra security
        document.querySelector('.btn-logout').addEventListener('click', function(e) {
            const confirmed = confirm('This will end your current session. Are you sure you want to logout?');
            if (!confirmed) {
                e.preventDefault();
            }
        });

        // Prevent back button after logout confirmation
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</body>
</html>