<?php
// StudentService.php
// includes/StudentService.php

require_once __DIR__ . '/EmailService.php';

class StudentService {
    private $pdo;
    private $emailService;
    
    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            // Include database configuration if not provided
            require_once __DIR__ . '/../config/database.php';
            global $pdo;
            $this->pdo = $pdo;
        }
        
        // Initialize email service
        $this->emailService = new EmailService();
    }
    
    /**
     * Send enrollment confirmation email to student
     */
    public function sendEnrollmentConfirmationEmail($studentId, $academicYear = null, $semester = null) {
        try {
            // Get student information with program details
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.first_name, u.last_name, u.email, u.student_id,
                    sp.year_level, p.program_code, p.program_name
                FROM users u
                LEFT JOIN student_profiles sp ON u.id = sp.user_id
                LEFT JOIN programs p ON sp.program_id = p.id
                WHERE u.id = ? AND u.role = 'student'
            ");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                return ['success' => false, 'message' => 'Student not found'];
            }
            
            // Get current academic year if not provided
            if (!$academicYear || !$semester) {
                $stmt = $this->pdo->prepare("
                    SELECT year_start, year_end, semester 
                    FROM academic_years 
                    WHERE is_active = 1 
                    LIMIT 1
                ");
                $stmt->execute();
                $currentAY = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($currentAY) {
                    $academicYear = $currentAY['year_start'] . '-' . $currentAY['year_end'];
                    $semester = strtoupper($currentAY['semester']) . ' SEMESTER';
                } else {
                    $academicYear = date('Y') . '-' . (date('Y') + 1);
                    $semester = 'FIRST SEMESTER';
                }
            }
            
            // Get enrolled subjects for the student
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.course_code, c.course_name, c.credits,
                    cs.section_name
                FROM enrollments e
                INNER JOIN class_sections cs ON e.class_section_id = cs.id
                INNER JOIN courses c ON cs.course_id = c.id
                INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
                WHERE e.student_id = ? 
                AND e.status = 'enrolled'
                AND ay.is_active = 1
                ORDER BY c.course_code
            ");
            $stmt->execute([$studentId]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($subjects)) {
                return ['success' => false, 'message' => 'No enrolled subjects found for this student'];
            }
            
            // Send the email using the existing EmailService
            $emailResult = $this->emailService->sendStudentEnrollmentConfirmationEmail(
                $student, 
                $subjects, 
                $academicYear, 
                $semester
            );
            
            return $emailResult;
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send welcome email for new student registration
     */
    public function sendStudentEnrollmentEmail($studentData, $password = null) {
        try {
            // Create a simplified welcome email for new students
            $emailResult = $this->emailService->sendStudentWelcomeEmail($studentData, $password);
            return $emailResult;
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error sending welcome email: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send password reset email to student
     */
    public function sendPasswordResetEmail($studentData, $newPassword) {
        try {
            // Use the existing password reset functionality
            $emailResult = $this->emailService->sendPasswordResetEmail($studentData, $newPassword);
            return $emailResult;
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error sending password reset email: ' . $e->getMessage()];
        }
    }
    
    /**
     * Log enrollment confirmation action
     */
    public function logEnrollmentConfirmation($studentId, $confirmedBy, $actionType = 'officially_enrolled') {
        try {
            // Check if enrollment_logs table exists, if not create it
            $this->createEnrollmentLogsTable();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO enrollment_logs (student_id, action_type, confirmed_by, action_date, notes)
                VALUES (?, ?, ?, NOW(), ?)
            ");
            
            $notes = "Student officially enrolled and confirmation email sent";
            $stmt->execute([$studentId, $actionType, $confirmedBy, $notes]);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to log enrollment confirmation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update student enrollment status
     */
    public function updateEnrollmentStatus($studentId, $status = 'officially_enrolled') {
        try {
            // First check if updated_at column exists in enrollments table
            $this->addUpdatedAtColumn();
            
            $stmt = $this->pdo->prepare("
                UPDATE enrollments 
                SET status = ?, updated_at = NOW() 
                WHERE student_id = ? AND status = 'enrolled'
            ");
            
            $stmt->execute([$status, $studentId]);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to update enrollment status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student enrollment statistics
     */
    public function getStudentEnrollmentStats($studentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_enrollments,
                    COUNT(CASE WHEN status = 'enrolled' THEN 1 END) as active_enrollments,
                    COUNT(CASE WHEN status = 'officially_enrolled' THEN 1 END) as official_enrollments,
                    SUM(c.credits) as total_credits
                FROM enrollments e
                INNER JOIN class_sections cs ON e.class_section_id = cs.id
                INNER JOIN courses c ON cs.course_id = c.id
                WHERE e.student_id = ?
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get enrollment stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get student's current enrolled subjects
     */
    public function getStudentEnrolledSubjects($studentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.course_code, c.course_name, c.credits,
                    cs.section_name, cs.schedule, cs.room,
                    CONCAT(uf.first_name, ' ', uf.last_name) as faculty_name,
                    e.status, e.enrollment_date
                FROM enrollments e
                INNER JOIN class_sections cs ON e.class_section_id = cs.id
                INNER JOIN courses c ON cs.course_id = c.id
                LEFT JOIN users uf ON cs.faculty_id = uf.id
                INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
                WHERE e.student_id = ? 
                AND e.status IN ('enrolled', 'officially_enrolled')
                AND ay.is_active = 1
                ORDER BY c.course_code
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get enrolled subjects: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if student is officially enrolled
     */
    public function isStudentOfficiallyEnrolled($studentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM enrollments 
                WHERE student_id = ? AND status = 'officially_enrolled'
            ");
            $stmt->execute([$studentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
            
        } catch (Exception $e) {
            error_log("Failed to check official enrollment status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create enrollment_logs table if it doesn't exist
     */
    private function createEnrollmentLogsTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS enrollment_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                confirmed_by INT NOT NULL,
                action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_student_id (student_id),
                INDEX idx_action_type (action_type),
                INDEX idx_confirmed_by (confirmed_by),
                FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE CASCADE
            )";
            
            $this->pdo->exec($sql);
            
        } catch (Exception $e) {
            error_log("Failed to create enrollment_logs table: " . $e->getMessage());
        }
    }
    
    /**
     * Add updated_at column to enrollments table if it doesn't exist
     */
    private function addUpdatedAtColumn() {
        try {
            // Check if column exists
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'enrollments' 
                AND COLUMN_NAME = 'updated_at'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                // Add the column
                $this->pdo->exec("
                    ALTER TABLE enrollments 
                    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ");
            }
            
        } catch (Exception $e) {
            error_log("Failed to add updated_at column: " . $e->getMessage());
        }
    }
    
    /**
     * Test email functionality
     */
    public function testEmailConfiguration() {
        try {
            return $this->emailService->testEmail();
        } catch (Exception $e) {
            error_log("Email test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Bulk send enrollment confirmations for multiple students
     */
    public function bulkSendEnrollmentConfirmations($studentIds, $confirmedBy) {
        $results = [];
        $successCount = 0;
        $failCount = 0;
        
        foreach ($studentIds as $studentId) {
            $result = $this->sendEnrollmentConfirmationEmail($studentId);
            
            if ($result['success']) {
                $this->logEnrollmentConfirmation($studentId, $confirmedBy, 'bulk_officially_enrolled');
                $this->updateEnrollmentStatus($studentId, 'officially_enrolled');
                $successCount++;
            } else {
                $failCount++;
            }
            
            $results[] = [
                'student_id' => $studentId,
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }
        
        return [
            'success' => $successCount > 0,
            'total_processed' => count($studentIds),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'details' => $results
        ];
    }
    
    /**
     * Get enrollment confirmation history for a student
     */
    public function getEnrollmentHistory($studentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    el.*,
                    CONCAT(u.first_name, ' ', u.last_name) as confirmed_by_name
                FROM enrollment_logs el
                LEFT JOIN users u ON el.confirmed_by = u.id
                WHERE el.student_id = ?
                ORDER BY el.action_date DESC
            ");
            $stmt->execute([$studentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get enrollment history: " . $e->getMessage());
            return [];
        }
    }
}
?>