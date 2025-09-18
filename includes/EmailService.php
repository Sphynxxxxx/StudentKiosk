<?php
// Email Service Class
// includes/EmailService.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $config;
    private $mail;
    
    public function __construct() {
        // Load email configuration
        $this->config = include '../config/email_config.php';
        
        // Create PHPMailer instance
        $this->mail = new PHPMailer(true);
        $this->setupSMTP();
    }
    
    private function setupSMTP() {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = $this->config['smtp_host'];
            $this->mail->SMTPAuth = $this->config['smtp_auth'];
            $this->mail->Username = $this->config['smtp_username'];
            $this->mail->Password = $this->config['smtp_password'];
            $this->mail->SMTPSecure = $this->config['smtp_secure'];
            $this->mail->Port = $this->config['smtp_port'];
            
            // Content settings
            $this->mail->isHTML($this->config['is_html']);
            $this->mail->CharSet = $this->config['charset'];
            
            // Default sender
            $this->mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mail->addReplyTo($this->config['reply_to'], $this->config['from_name']);
            
        } catch (Exception $e) {
            throw new Exception("SMTP Setup failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send Faculty Enrollment Confirmation Email
     */
    public function sendFacultyEnrollmentEmail($facultyData, $password = null) {
        try {
            // Clear any previous recipients
            $this->mail->clearAddresses();
            
            // Recipient
            $this->mail->addAddress($facultyData['email'], $facultyData['first_name'] . ' ' . $facultyData['last_name']);
            
            // Subject
            $this->mail->Subject = 'Welcome to ISATU Faculty Kiosk System - Account Created';
            
            // Email content
            $this->mail->Body = $this->getFacultyWelcomeTemplate($facultyData, $password);
            $this->mail->AltBody = $this->getFacultyWelcomeTextTemplate($facultyData, $password);
            
            // Send email
            $result = $this->mail->send();
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Enrollment confirmation email sent successfully to ' . $facultyData['email']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Email sending failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send Password Reset Email
     */
    public function sendPasswordResetEmail($facultyData, $newPassword) {
        try {
            // Clear any previous recipients
            $this->mail->clearAddresses();
            
            // Recipient
            $this->mail->addAddress($facultyData['email'], $facultyData['first_name'] . ' ' . $facultyData['last_name']);
            
            // Subject
            $this->mail->Subject = 'ISATU Kiosk System - Password Reset';
            
            // Email content
            $this->mail->Body = $this->getPasswordResetTemplate($facultyData, $newPassword);
            $this->mail->AltBody = $this->getPasswordResetTextTemplate($facultyData, $newPassword);
            
            // Send email
            $result = $this->mail->send();
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Password reset email sent successfully to ' . $facultyData['email']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Email sending failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * HTML Template for Faculty Welcome Email
     */
    private function getFacultyWelcomeTemplate($facultyData, $password = null) {
        $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/StudentKiosk/login.php';
        $supportEmail = $this->config['reply_to'];
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Welcome to ISATU Kiosk System</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .credentials-box { background: white; border: 2px solid #2563eb; border-radius: 8px; padding: 20px; margin: 20px 0; }
                .button { display: inline-block; background: #2563eb; color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 20px 0; }
                h1, h2 { margin: 0 0 15px 0; }
                .info-row { margin: 10px 0; }
                .label { font-weight: bold; color: #2563eb; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Welcome to ISATU Kiosk System</h1>
                    <p>Your faculty account has been successfully created!</p>
                </div>
                
                <div class='content'>
                    <h2>Dear " . htmlspecialchars($facultyData['first_name'] . ' ' . $facultyData['last_name']) . ",</h2>
                    
                    <p>Congratulations! You have been successfully enrolled as a faculty member in the ISATU Kiosk System. Your account is now active and ready to use.</p>
                    
                    <div class='credentials-box'>
                        <h3>📋 Your Account Details</h3>
                        <div class='info-row'><span class='label'>Full Name:</span> " . htmlspecialchars($facultyData['first_name'] . ' ' . $facultyData['last_name']) . "</div>
                        <div class='info-row'><span class='label'>Email:</span> " . htmlspecialchars($facultyData['email']) . "</div>
                        <div class='info-row'><span class='label'>Username:</span> " . htmlspecialchars($facultyData['username']) . "</div>
                        <div class='info-row'><span class='label'>Employee ID:</span> " . htmlspecialchars($facultyData['employee_id'] ?? 'Not Set') . "</div>
                        <div class='info-row'><span class='label'>Position:</span> " . htmlspecialchars($facultyData['position'] ?? 'Faculty Member') . "</div>
                        " . ($password ? "<div class='info-row'><span class='label'>Temporary Password:</span> <strong>" . htmlspecialchars($password) . "</strong></div>" : "") . "
                    </div>
                    
                    " . ($password ? "
                    <div class='warning'>
                        <strong>⚠️ Important Security Notice:</strong><br>
                        This is a temporary password. For your security, please log in and change your password immediately after your first login.
                    </div>
                    " : "") . "
                    
                    <h3>🚀 Getting Started</h3>
                    <ol>
                        <li>Click the login button below to access your account</li>
                        <li>Use your username and " . ($password ? "temporary password" : "assigned password") . " to log in</li>
                        " . ($password ? "<li>Change your password immediately after logging in</li>" : "") . "
                        <li>Complete your faculty profile information</li>
                        <li>Start using the system features</li>
                    </ol>
                    
                    <div style='text-align: center;'>
                        <a href='" . $loginUrl . "' class='button'>🔐 Login to Your Account</a>
                    </div>
                    
                    <h3>📞 Need Help?</h3>
                    <p>If you have any questions or need assistance, please contact our support team:</p>
                    <ul>
                        <li>Email: <a href='mailto:" . $supportEmail . "'>" . $supportEmail . "</a></li>
                        <li>System: ISATU Kiosk System</li>
                    </ul>
                </div>
                
                <div class='footer'>
                    <p>This email was sent automatically by the ISATU Kiosk System.<br>
                    Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " Iloilo Science and Technology University</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Plain Text Template for Faculty Welcome Email
     */
    private function getFacultyWelcomeTextTemplate($facultyData, $password = null) {
        $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/StudentKiosk/login.php';
        $supportEmail = $this->config['reply_to'];
        
        return "
WELCOME TO ISATU KIOSK SYSTEM
=============================

Dear " . $facultyData['first_name'] . ' ' . $facultyData['last_name'] . ",

Congratulations! You have been successfully enrolled as a faculty member in the Iloilo Science and Technology University. Your account is now active and ready to use.

YOUR ACCOUNT DETAILS:
- Full Name: " . $facultyData['first_name'] . ' ' . $facultyData['last_name'] . "
- Email: " . $facultyData['email'] . "
- Username: " . $facultyData['username'] . "
- Employee ID: " . ($facultyData['employee_id'] ?? 'Not Set') . "
- Position: " . ($facultyData['position'] ?? 'Faculty Member') . "
" . ($password ? "- Temporary Password: " . $password : "") . "

" . ($password ? "
IMPORTANT SECURITY NOTICE:
This is a temporary password. For your security, please log in and change your password immediately after your first login.
" : "") . "

GETTING STARTED:
1. Visit the login page: " . $loginUrl . "
2. Use your username and " . ($password ? "temporary password" : "assigned password") . " to log in
" . ($password ? "3. Change your password immediately after logging in" : "") . "
4. Complete your faculty profile information
5. Start using the system features

NEED HELP?
If you have any questions or need assistance, please contact our support team:
- Email: " . $supportEmail . "
- System: ISATU Kiosk System

Login URL: " . $loginUrl . "

This email was sent automatically by the ISATU Kiosk System.
Please do not reply to this email.

© " . date('Y') . " Iloilo Science and Technology University";
    }
    
    /**
     * HTML Template for Password Reset Email
     */
    private function getPasswordResetTemplate($facultyData, $newPassword) {
        $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/StudentKiosk/login.php';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset - ISATU Kiosk System</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc2626; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .password-box { background: white; border: 2px solid #dc2626; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center; }
                .button { display: inline-block; background: #dc2626; color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; margin: 20px 0; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Reset</h1>
                    <p>Your password has been reset</p>
                </div>
                
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($facultyData['first_name']) . ",</h2>
                    
                    <p>Your password for the ISATU Kiosk System has been reset by an administrator.</p>
                    
                    <div class='password-box'>
                        <h3>Your New Temporary Password</h3>
                        <h2 style='color: #dc2626; font-family: monospace; letter-spacing: 2px;'>" . htmlspecialchars($newPassword) . "</h2>
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Important Security Notice:</strong><br>
                        This is a temporary password. You must change it immediately after logging in for security reasons.
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='" . $loginUrl . "' class='button'>🔐 Login and Change Password</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>If you did not request this password reset, please contact the administrator immediately.</p>
                    <p>&copy; " . date('Y') . " Iloilo Science and Technology University</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Plain Text Template for Password Reset Email
     */
    private function getPasswordResetTextTemplate($facultyData, $newPassword) {
        $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/StudentKiosk/login.php';
        
        return "
PASSWORD RESET - ISATU KIOSK SYSTEM
===================================

Hello " . $facultyData['first_name'] . ",

Your password for the ISATU Kiosk System has been reset by an administrator.

YOUR NEW TEMPORARY PASSWORD: " . $newPassword . "

IMPORTANT SECURITY NOTICE:
This is a temporary password. You must change it immediately after logging in for security reasons.

To change your password:
1. Visit: " . $loginUrl . "
2. Log in with your username and this temporary password
3. Change your password immediately

If you did not request this password reset, please contact the administrator immediately.

© " . date('Y') . " Iloilo Science and Technology University";
    }
    
    /**
     * Test email configuration
     */
    public function testEmail() {
        try {
            // Send a test email to the configured sender
            $this->mail->addAddress($this->config['from_email']);
            $this->mail->Subject = 'ISATU Kiosk System - Email Test';
            $this->mail->Body = '<h1>Email Test Successful!</h1><p>Your email configuration is working correctly.</p>';
            $this->mail->AltBody = 'Email Test Successful! Your email configuration is working correctly.';
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            throw new Exception("Email test failed: " . $e->getMessage());
        }
    }
}