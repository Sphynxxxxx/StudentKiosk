

// config/config.php
<?php
define('BASE_URL', 'http://localhost/student_kiosk_system/');
define('APP_NAME', 'Student Kiosk System');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoload classes
spl_autoload_register(function ($class_name) {
    $paths = [
        'models/' . $class_name . '.php',
        'controllers/' . $class_name . '.php',
        'config/' . $class_name . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
});
?>