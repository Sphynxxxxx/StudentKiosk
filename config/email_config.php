<?php
// Email Configuration
// config/email_config.php

return [
    // SMTP Configuration
    'smtp_host' => 'smtp.gmail.com', // Gmail SMTP server
    'smtp_port' => 587, // TLS port
    'smtp_secure' => 'tls', // 'tls' or 'ssl'
    'smtp_auth' => true,
    
    // Email credentials
    'smtp_username' => 'larrydenverbiaco@gmail.com', // Your Gmail address
    'smtp_password' => 'sjqx kaqk ctsd yeyn', // Your Gmail App Password (not regular password)
    
    // Sender information
    'from_email' => 'enrollment@isatu.edu.ph',
    'from_name' => 'ISATU Kiosk System',
    'reply_to' => 'noreply@isatu.edu.ph',
    
    // Email settings
    'charset' => 'UTF-8',
    'is_html' => true,
    
    // Other popular SMTP providers:
    
    // Outlook/Hotmail
    /*
    'smtp_host' => 'smtp-mail.outlook.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    */
    
    // Yahoo
    /*
    'smtp_host' => 'smtp.mail.yahoo.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    */
    
    // Custom SMTP (for your own domain)
    /*
    'smtp_host' => 'mail.yourdomain.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    */
];

/*
IMPORTANT: Gmail App Password Setup
1. Go to your Google Account settings
2. Enable 2-Factor Authentication
3. Go to Security > App passwords
4. Generate an app-specific password
5. Use this password (not your regular Gmail password) in the config above

For other email providers:
- Outlook: Use your regular email and password
- Yahoo: May require app password
- Custom SMTP: Use your hosting provider's SMTP settings
*/