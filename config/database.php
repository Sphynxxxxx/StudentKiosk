<?php
/**
 * Database Configuration
 * ISATU Student Kiosk System
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'isatu_kiosk_system';
    private $username = 'root';
    private $password = '';
    private $pdo = null;
    
    public function __construct() {
        // You can override these values with environment variables or config file
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'isatu_kiosk_system';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
    }
    
    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ];
                
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
                
            } catch(PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                throw new Exception("Database connection failed. Please check your configuration.");
            }
        }
        
        return $this->pdo;
    }
    
    public function disconnect() {
        $this->pdo = null;
    }
    
    // Test database connection
    public function testConnection() {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->query('SELECT 1');
            return true;
        } catch(Exception $e) {
            return false;
        }
    }
    
    // Get database instance (singleton pattern)
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
}

// Global database connection function for backward compatibility
function getDB() {
    return Database::getInstance()->connect();
}

// Initialize global PDO variable
$database = Database::getInstance();
$pdo = $database->connect();

?>