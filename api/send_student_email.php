<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Include database configuration
require_once '../config/database.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Set JSON header
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get POST data
$recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';
$recipient_name = isset($_POST['recipient_name']) ? trim($_POST['recipient_name']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validate inputs
if (empty($recipient_email) || empty($subject) || empty($message)) {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required'
    ]);
    exit;
}

// Validate email format
if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email address'
    ]);
    exit;
}

try {
    // Get sender information from session (with fallback)
    $sender_name = 'System Administrator';
    $sender_email = 'noreply@isatu.edu.ph';
    $sender_id = null;
    
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("
            SELECT first_name, last_name, email 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $sender = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sender) {
            $sender_name = $sender['first_name'] . ' ' . $sender['last_name'];
            $sender_email = $sender['email'];
            $sender_id = $_SESSION['user_id'];
        }
    }
    
    // Prepare email content
    $email_subject = "[ISATU Kiosk System] " . $subject;
    
    $email_body = "
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
            }
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 8px;
            }
            .header {
                background: linear-gradient(135deg, #007bff, #0056b3);
                color: white;
                padding: 20px;
                border-radius: 8px 8px 0 0;
                text-align: center;
            }
            .content {
                padding: 20px;
                background: #f9f9f9;
            }
            .message-box {
                background: white;
                padding: 15px;
                border-left: 4px solid #007bff;
                margin: 15px 0;
                white-space: pre-wrap;
            }
            .footer {
                padding: 15px;
                text-align: center;
                font-size: 0.9em;
                color: #6c757d;
                border-top: 1px solid #ddd;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h2>ISATU Kiosk System</h2>
                <p>Academic Communication</p>
            </div>
            <div class='content'>
                <p><strong>Dear " . htmlspecialchars($recipient_name) . ",</strong></p>
                
                <div class='message-box'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
                
                <p><strong>From:</strong><br>
                " . htmlspecialchars($sender_name) . "<br>
                " . htmlspecialchars($sender_email) . "</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from ISATU Kiosk System.<br>
                Please do not reply directly to this email.</p>
                <p>&copy; " . date('Y') . " ISATU Kiosk System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        $pdo->query("SELECT 1 FROM email_logs LIMIT 1");
    } catch (PDOException $e) {
        // Table doesn't exist, create it
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NULL,
                recipient_email VARCHAR(255) NOT NULL,
                recipient_name VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                message TEXT NOT NULL,
                sent_at DATETIME NOT NULL,
                status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sender (sender_id),
                INDEX idx_recipient (recipient_email),
                INDEX idx_status (status),
                INDEX idx_sent_at (sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    $mail_sent = false;
    $error_message = '';
    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';  
        $mail->SMTPAuth = true;
        $mail->Username = 'larrydenverbiaco@gmail.com';  
        $mail->Password = 'sjqx kaqk ctsd yeyn';      
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Email settings
        $mail->setFrom('noreply@isatu.edu.ph', 'ISATU Kiosk System');
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->addReplyTo($sender_email, $sender_name);
        
        $mail->isHTML(true);
        $mail->Subject = $email_subject;
        $mail->Body = $email_body;
        
        // Send email
        $mail->send();
        $mail_sent = true;
        
    } catch (Exception $e) {
        $mail_sent = false;
        $error_message = $mail->ErrorInfo;
        error_log("PHPMailer Error: " . $error_message);
    }
    
    // Log the attempt in database
    $stmt = $pdo->prepare("
        INSERT INTO email_logs (sender_id, recipient_email, recipient_name, subject, message, sent_at, status)
        VALUES (?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $log_status = $mail_sent ? 'sent' : 'failed';
    $stmt->execute([
        $sender_id,
        $recipient_email,
        $recipient_name,
        $subject,
        $message,
        $log_status
    ]);
    
    if ($mail_sent) {
        echo json_encode([
            'success' => true,
            'message' => 'Email sent successfully to ' . $recipient_email
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email: ' . $error_message
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Email sending error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Email sending error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}