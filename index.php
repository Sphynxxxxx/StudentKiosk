<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['student_id'])) {
    header('Location: view/student_dashboard.php');
    exit();
}

// Include database configuration
require_once 'config/database.php';

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number = trim($_POST['student_number']);
    $password = $_POST['password'];
    
    if (empty($student_number) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        try {
            // Check if user exists with student_id
            $stmt = $pdo->prepare("
                SELECT u.*, sp.year_level, sp.program_id 
                FROM users u 
                LEFT JOIN student_profiles sp ON u.id = sp.user_id
                WHERE u.student_id = ? AND u.role = 'student' AND u.status = 'active'
            ");
            $stmt->execute([$student_number]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['student_id'] = $user['id'];
                $_SESSION['student_number'] = $user['student_id'];
                $_SESSION['student_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['student_email'] = $user['email'];
                $_SESSION['user_role'] = 'student';
                
                // Log the login activity
                $stmt = $pdo->prepare("
                    INSERT INTO user_logs (user_id, activity_type, description, ip_address, user_agent) 
                    VALUES (?, 'login', 'Student logged in', ?, ?)
                ");
                $stmt->execute([
                    $user['id'], 
                    $_SERVER['REMOTE_ADDR'], 
                    $_SERVER['HTTP_USER_AGENT']
                ]);
                
                // Redirect to dashboard
                header('Location: view/student_dashboard.php');
                exit();
            } else {
                $error_message = 'Invalid student number or password.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = 'Login system temporarily unavailable. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - ISATU</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .logo-section {
            margin-bottom: 30px;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .logo img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .portal-title {
            font-size: 1.5rem;
            color: #4285f4;
            font-weight: 600;
            margin: 0;
        }

        .login-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e8eaed;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fff;
            color: #3c4043;
        }

        .form-input:focus {
            outline: none;
            border-color: #4285f4;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }

        .form-input::placeholder {
            color: #5f6368;
            font-size: 16px;
        }

        .password-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 14px;
            color: #5f6368;
        }

        .password-toggle input[type="checkbox"] {
            margin: 0;
            cursor: pointer;
        }

        .password-toggle label {
            cursor: pointer;
            user-select: none;
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: #4285f4;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .login-btn:hover {
            background: #3367d6;
            box-shadow: 0 2px 8px rgba(66, 133, 244, 0.3);
        }

        .login-btn:active {
            transform: translateY(1px);
        }

        .login-btn:disabled {
            background: #dadce0;
            cursor: not-allowed;
            transform: none;
        }

        .support-info {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e8eaed;
            color: #5f6368;
            font-size: 14px;
            line-height: 1.5;
        }

        .support-email {
            color: #4285f4;
            text-decoration: none;
        }

        .support-email:hover {
            text-decoration: underline;
        }

        .footer {
            margin-top: 30px;
            color: #9aa0a6;
            font-size: 12px;
        }

        .error-message {
            background: #fce8e6;
            color: #d93025;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #fad2cf;
        }

        .success-message {
            background: #e8f5e8;
            color: #137333;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #ceead6;
        }

        /* Loading state */
        .loading {
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .portal-title {
                font-size: 1.3rem;
            }

            .form-input {
                font-size: 16px; /* Prevent zoom on iOS */
            }
        }

        /* Accessibility */
        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Focus indicators */
        .login-btn:focus,
        .support-email:focus {
            outline: 2px solid #4285f4;
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo">
                <img src="assets/images/ISATU Logo.png" alt="ISATU Logo">
                <h1 class="portal-title">Student Portal</h1>
            </div>
        </div>

        <?php if ($error_message): ?>
        <div class="error-message" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
        <div class="success-message" role="alert">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="" autocomplete="on">
            <div class="form-group">
                <label for="student_number" class="visually-hidden">Student Number</label>
                <input 
                    type="text" 
                    id="student_number" 
                    name="student_number" 
                    class="form-input" 
                    placeholder="Student Number"
                    required
                    autocomplete="username"
                    value="<?php echo isset($_POST['student_number']) ? htmlspecialchars($_POST['student_number']) : ''; ?>"
                >
            </div>

            <div class="form-group">
                <label for="password" class="visually-hidden">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input" 
                    placeholder="Password"
                    required
                    autocomplete="current-password"
                >
                <div class="password-toggle">
                    <input type="checkbox" id="show-password" onchange="togglePassword()">
                    <label for="show-password">Show Password</label>
                </div>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
                Login
            </button>
        </form>

        <div class="support-info">
            For technical concerns, please email 
            <a href="mailto:support.mis@isatu.edu.ph" class="support-email">support.mis@isatu.edu.ph</a>.<br>
            Kindly use your university email to ensure prompt assistance and avoid delays.
        </div>

        <div class="footer">
            © <?php echo date('Y'); ?>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const checkbox = document.getElementById('show-password');
            
            if (checkbox.checked) {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }

        // Form submission with loading state
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('loginBtn');
            const studentNumber = document.getElementById('student_number').value.trim();
            const password = document.getElementById('password').value;
            
            if (!studentNumber || !password) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.textContent = '';
            
            // Re-enable if form validation fails
            setTimeout(() => {
                if (submitBtn.disabled) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    submitBtn.textContent = 'Login';
                }
            }, 5000);
        });

        // Auto-focus on first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const studentNumberInput = document.getElementById('student_number');
            const passwordInput = document.getElementById('password');
            
            if (!studentNumberInput.value) {
                studentNumberInput.focus();
            } else if (!passwordInput.value) {
                passwordInput.focus();
            }
        });

        // Remove error message after user starts typing
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                const errorMsg = document.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.style.display = 'none';
                }
            });
        });

        // Enter key handling
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = document.querySelector('.login-form');
                const inputs = form.querySelectorAll('input[required]');
                const currentIndex = Array.from(inputs).indexOf(document.activeElement);
                
                if (currentIndex >= 0 && currentIndex < inputs.length - 1) {
                    e.preventDefault();
                    inputs[currentIndex + 1].focus();
                }
            }
        });
    </script>
</body>
</html>