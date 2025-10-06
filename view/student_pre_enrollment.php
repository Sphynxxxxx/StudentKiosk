<?php
// Helper function to format student status
function formatStudentStatus($status) {
    $status_map = [
        'regular' => 'REGULAR',
        'irregular' => 'IRREGULAR',
        'probation' => 'PROBATION',
        'suspended' => 'SUSPENDED',
        'graduated' => 'GRADUATED',
        'dropped' => 'DROPPED',
        'transferred' => 'TRANSFERRED',
        'leave_of_absence' => 'LEAVE OF ABSENCE',
        'returning' => 'RETURNING'
    ];
    
    return $status_map[strtolower($status)] ?? strtoupper($status);
}

// Helper function to get status color class
function getStatusColorClass($status) {
    switch (strtolower($status)) {
        case 'regular':
            return 'status-regular';
        case 'irregular':
            return 'status-irregular';
        case 'probation':
            return 'status-probation';
        case 'suspended':
            return 'status-suspended';
        case 'graduated':
            return 'status-graduated';
        case 'dropped':
        case 'transferred':
            return 'status-inactive';
        case 'leave_of_absence':
            return 'status-leave';
        case 'returning':
            return 'status-returning';
        default:
            return 'status-default';
    }
}
?>
<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: ../index.php');
    exit();
}

// Include database configuration
require_once '../config/database.php';

try {
    // Get student information with status
    $stmt = $pdo->prepare("
        SELECT u.*, sp.year_level, sp.program_id, sp.student_status, sp.section_id, sp.academic_year_id,
               p.program_name, p.program_code, s.section_name
        FROM users u 
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN programs p ON sp.program_id = p.id
        LEFT JOIN sections s ON sp.section_id = s.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$_SESSION['student_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if student data was found
    if (!$student) {
        // Redirect to login if student not found
        header('Location: ../auth/student_logout.php');
        exit();
    }

    // Get current student status from history if status history table exists
    $current_status = 'regular'; // default
    try {
        $stmt = $pdo->prepare("
            SELECT status 
            FROM student_status_history 
            WHERE student_id = ? 
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['student_id']]);
        $status_result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($status_result) {
            $current_status = $status_result['status'];
        } elseif ($student && isset($student['student_status']) && $student['student_status']) {
            $current_status = $student['student_status'];
        }
    } catch (PDOException $e) {
        // If status history table doesn't exist, use profile status or default
        if ($student && isset($student['student_status']) && $student['student_status']) {
            $current_status = $student['student_status'];
        }
    }

    // Get all academic years for dropdown
    $stmt = $pdo->prepare("SELECT * FROM academic_years ORDER BY year_start DESC, semester");
    $stmt->execute();
    $academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get active academic year if none selected - prioritize student's academic year
    $selected_ay = isset($_GET['academic_year_id']) ? $_GET['academic_year_id'] : null;
    if (!$selected_ay) {
        // First try to use student's academic year from their profile
        if ($student['academic_year_id']) {
            $selected_ay = $student['academic_year_id'];
        } else {
            // Fallback to active academic year
            $stmt = $pdo->prepare("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
            $stmt->execute();
            $active_ay = $stmt->fetch(PDO::FETCH_ASSOC);
            $selected_ay = $active_ay ? $active_ay['id'] : null;
        }
    }

    // Get available class sections for enrollment - FILTERED by student's year, section, and program
    $available_sections = [];
    if ($selected_ay && isset($student['program_id']) && $student['program_id'] && 
        isset($student['year_level']) && $student['year_level']) {
        
        // Updated query to filter by student's specific year, section, and program
        $stmt = $pdo->prepare("
            SELECT 
                cs.id as class_section_id,
                cs.section_name,
                cs.schedule,
                cs.room,
                cs.max_students,
                s.course_code,
                s.subject_name,
                s.credits,
                s.description,
                ay.year_start,
                ay.year_end,
                ay.semester,
                u.first_name as faculty_first_name,
                u.last_name as faculty_last_name,
                COUNT(e.id) as enrolled_count,
                CASE WHEN e_student.id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled,
                CASE WHEN e_pending.id IS NOT NULL THEN 1 ELSE 0 END as is_pending,
                sec.year_level as section_year_level,
                sec.program_id as section_program_id
            FROM class_sections cs
            INNER JOIN subjects s ON cs.subject_id = s.id
            INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
            INNER JOIN users u ON cs.faculty_id = u.id
            LEFT JOIN sections sec ON cs.section_id = sec.id
            LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
            LEFT JOIN enrollments e_student ON cs.id = e_student.class_section_id 
                AND e_student.student_id = ? AND e_student.status = 'enrolled'
            LEFT JOIN enrollments e_pending ON cs.id = e_pending.class_section_id 
                AND e_pending.student_id = ? AND e_pending.status = 'pending'
            WHERE ay.id = ? 
                AND cs.status = 'active'
                AND s.status = 'active'
                AND (
                    -- Match student's specific section if they have one
                    (? IS NOT NULL AND cs.section_id = ?) OR
                    -- If no specific section, match by year level and program
                    (? IS NULL AND sec.year_level = ? AND sec.program_id = ?) OR
                    -- Also include sections that don't have a specific section assigned but match criteria
                    (cs.section_id IS NULL AND EXISTS (
                        SELECT 1 FROM sections temp_sec 
                        WHERE temp_sec.year_level = ? 
                        AND temp_sec.program_id = ?
                        AND temp_sec.academic_year_id = ?
                    ))
                )
            GROUP BY cs.id, s.id, ay.id, u.id, sec.id
            ORDER BY s.course_code, cs.section_name
        ");
        
        $stmt->execute([
            $_SESSION['student_id'], // param 1 - for enrolled check
            $_SESSION['student_id'], // param 2 - for pending check
            $selected_ay,            // param 3
            $student['section_id'],  // param 4
            $student['section_id'],  // param 5  
            $student['section_id'],  // param 6
            $student['year_level'],  // param 7
            $student['program_id'],  // param 8
            $student['year_level'],  // param 9
            $student['program_id'],  // param 10
            $selected_ay             // param 11
        ]);
        $available_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Handle enrollment actions - UPDATED to use 'pending' status
    if ($_POST && isset($_POST['enroll_subjects']) && is_array($_POST['enroll_subjects'])) {
        $enrolled_count = 0;
        $error_count = 0;
        $error_messages = [];
        
        // Start transaction for data consistency
        $pdo->beginTransaction();
        
        try {
            foreach ($_POST['enroll_subjects'] as $class_section_id) {
                $class_section_id = (int)$class_section_id;
                
                // Check if student is already enrolled or has pending enrollment
                $stmt = $pdo->prepare("
                    SELECT id, status FROM enrollments 
                    WHERE student_id = ? AND class_section_id = ? 
                    AND status IN ('enrolled', 'pending')
                ");
                $stmt->execute([$_SESSION['student_id'], $class_section_id]);
                $existing_enrollment = $stmt->fetch();
                
                if ($existing_enrollment) {
                    if ($existing_enrollment['status'] === 'enrolled') {
                        $error_messages[] = "Already enrolled in one of the selected subjects.";
                    } else if ($existing_enrollment['status'] === 'pending') {
                        $error_messages[] = "Already have a pending enrollment for one of the selected subjects.";
                    }
                    $error_count++;
                    continue;
                }
                
                // Get class information for validation
                $stmt = $pdo->prepare("
                    SELECT cs.max_students, s.course_code, s.subject_name,
                           COUNT(e.id) as enrolled_count
                    FROM class_sections cs
                    INNER JOIN subjects s ON cs.subject_id = s.id
                    LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
                    WHERE cs.id = ?
                    GROUP BY cs.id, cs.max_students, s.course_code, s.subject_name
                ");
                $stmt->execute([$class_section_id]);
                $class_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$class_info) {
                    $error_messages[] = "Class section not found.";
                    $error_count++;
                    continue;
                }
                
                // Check if class has available slots (considering only enrolled students, not pending)
                if ($class_info['enrolled_count'] >= $class_info['max_students']) {
                    $error_messages[] = "Class for {$class_info['course_code']} is already full.";
                    $error_count++;
                    continue;
                }
                
                // Submit enrollment request with 'pending' status
                $stmt = $pdo->prepare("
                    INSERT INTO enrollments (student_id, class_section_id, status, enrollment_date)
                    VALUES (?, ?, 'pending', NOW())
                ");
                $stmt->execute([$_SESSION['student_id'], $class_section_id]);
                $enrolled_count++;
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Set success/error messages
            if ($enrolled_count > 0) {
                $success_message = "Successfully submitted $enrolled_count enrollment request(s)! Your enrollments are now pending approval from the registrar.";
            }
            if ($error_count > 0) {
                $error_message = implode(" ", array_unique($error_messages));
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error_message = "An error occurred while processing your enrollment request. Please try again.";
            error_log("Pre-enrollment error: " . $e->getMessage());
        }
        
        // Refresh data after enrollment
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

} catch (PDOException $e) {
    error_log("Student pre-enrollment error: " . $e->getMessage());
    // Redirect to login on database error
    header('Location: ../auth/student_logout.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-enrollment - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/student_dashboard.css" rel="stylesheet">

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
            <div class="user-section">
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-info" onclick="toggleDropdown()">
                        <i class="fas fa-user-graduate"></i>
                        <span class="user-name"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="dropdown-user-name"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></div>
                            <div class="dropdown-user-role">Student</div>
                            <?php if (!empty($student['student_id'])): ?>
                            <div class="dropdown-user-id">ID: <?php echo htmlspecialchars($student['student_id']); ?></div>
                            <?php endif; ?>
                        </div>
                        <a href="student_dashboard.php" class="dropdown-item">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="student_profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <a href="student_pre_enrollment.php" class="dropdown-item">
                            <i class="fas fa-file-alt"></i>
                            <span>Pre-enrollment</span>
                        </a>
                        <a href="appeal_grade.php" class="dropdown-item">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Grade Appeal</span>
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../auth/student_logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-container">
        <div class="grades-header">
            <div class="grades-title">
                <h1>Online Pre-Enrolment</h1>
                <?php if ($student && !empty($student['program_name'])): ?>
                <div class="student-info">
                    <span class="program"><?php echo htmlspecialchars($student['program_name']); ?></span>
                    <?php if (!empty($student['year_level']) || !empty($student['section_name'])): ?>
                    <span class="year-level">
                        <?php 
                        if (!empty($student['year_level'])) {
                            echo htmlspecialchars($student['year_level']) . ' Year';
                        }
                        if (!empty($student['section_name'])) {
                            echo !empty($student['year_level']) ? ' - ' : '';
                            echo 'Section ' . htmlspecialchars($student['section_name']);
                        }
                        ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="filters-section">
                <form method="GET" action="" class="filters-form">
                    <div class="filter-group">
                        <select name="academic_year_id" class="filter-select" onchange="this.form.submit()">
                            <option value="">-Academic Year-</option>
                            <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>" <?php echo ($selected_ay == $ay['id']) ? 'selected' : ''; ?>>
                                <?php echo $ay['year_start'] . '-' . $ay['year_end'] . ' (' . ucfirst($ay['semester']) . ')'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="apply-btn">Apply</button>
                </form>
            </div>
        </div>

        <!-- Student Information Header -->
        <?php 
        // Get selected academic year info
        $selected_ay_info = null;
        if ($selected_ay) {
            foreach ($academic_years as $ay) {
                if ($ay['id'] == $selected_ay) {
                    $selected_ay_info = $ay;
                    break;
                }
            }
        }
        ?>
        
        <div class="student-info-card">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">ID:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['student_id'] ?? '2025-1863-A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars(strtoupper(($student['last_name'] ?? 'LASTNAME') . ', ' . ($student['first_name'] ?? 'FIRSTNAME'))); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">A/Year:</span>
                    <span class="info-value">
                        <?php 
                        if ($selected_ay_info) {
                            echo $selected_ay_info['year_start'] . '-' . $selected_ay_info['year_end'];
                        } else {
                            echo '2025-2026';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Sem:</span>
                    <span class="info-value">
                        <?php 
                        if ($selected_ay_info) {
                            echo strtoupper($selected_ay_info['semester']);
                        } else {
                            echo '1ST';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Student Status:</span>
                    <span class="info-value <?php echo getStatusColorClass($current_status); ?>">
                        <?php echo formatStudentStatus($current_status); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Show helpful message if student profile is incomplete -->
        <?php if (empty($student['program_id']) || empty($student['year_level'])): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Incomplete Profile:</strong> Your student profile is missing required information (program or year level). 
            Please contact the registrar to complete your profile before enrolling in subjects.
        </div>
        <?php endif; ?>

        <?php if (empty($available_sections)): ?>
        <div class="empty-grades">
            <i class="fas fa-calendar-plus"></i>
            <h3>No Subjects Available</h3>
            <p>
                <?php if (empty($student['program_id']) || empty($student['year_level'])): ?>
                    Please ensure your profile is complete (program and year level) before viewing available subjects.
                <?php else: ?>
                    No subjects found for your program (<?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?>), 
                    year level (<?php echo htmlspecialchars($student['year_level'] ?? 'N/A'); ?>), 
                    and the selected academic year. Please contact the registrar if you believe this is an error.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>

        <!-- Enrollment Form -->
        <div class="enrollment-form">
            <!-- Student Details -->
            <div class="student-details">
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Course:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(($student['program_name'] ?? 'Not Set')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Year & Section:</span>
                        <span class="detail-value">
                            <?php 
                            if (!empty($student['year_level']) || !empty($student['section_name'])) {
                                if (!empty($student['year_level'])) {
                                    echo htmlspecialchars($student['year_level']) . ' Year';
                                }
                                if (!empty($student['section_name'])) {
                                    echo !empty($student['year_level']) ? ' - ' : '';
                                    echo 'Section ' . htmlspecialchars($student['section_name']);
                                }
                            } else {
                                echo 'Not Set';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Block Status:</span>
                        <span class="detail-value <?php echo getStatusColorClass($current_status); ?>">
                            <?php echo formatStudentStatus($current_status); ?>
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST">
                <div class="subjects-section">
                    <div class="legend">
                        <strong>Instructions:</strong><br>
                        * Click checkbox to select subject for pre-enrollment request<br>
                        * All enrollment requests require <span class="red-text">REGISTRAR APPROVAL</span><br>
                        * You will be notified via email once your enrollment is approved or rejected<br>
                        * Only subjects matching your program, year level, and section are shown.
                    </div>

                    <div class="section-header">
                        <i class="fas fa-list"></i>
                        <h3>Available Subjects for Pre-Enrollment</h3>
                    </div>

                    <table class="subjects-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">Select</th>
                                <th>Subjects</th>
                                <th class="section-cell">Section</th>
                                <th>Description</th>
                                <th class="credits-cell">No. of Units</th>
                                <th class="schedule-cell">Schedule</th>
                                <th>Instructor</th>
                                <th class="slots-cell">Slots</th>
                                <th class="status-cell">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_sections as $section): ?>
                            <tr>
                                <td class="checkbox-cell">
                                    <?php if (!$section['is_enrolled'] && !$section['is_pending'] && $section['enrolled_count'] < $section['max_students']): ?>
                                    <input type="checkbox" name="enroll_subjects[]" value="<?php echo $section['class_section_id']; ?>">
                                    <?php endif; ?>
                                </td>
                                <td class="course-code"><?php echo htmlspecialchars($section['course_code'] ?? 'N/A'); ?></td>
                                <td class="section-cell"><?php echo htmlspecialchars($section['section_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($section['subject_name'] ?? 'Subject Name Not Available'); ?></td>
                                <td class="credits-cell"><?php echo $section['credits'] ?? '0'; ?></td>
                                <td class="schedule-cell"><?php echo htmlspecialchars($section['schedule'] ?? 'TBA'); ?></td>
                                <td><?php echo htmlspecialchars(($section['faculty_first_name'] ?? 'Unknown') . ' ' . ($section['faculty_last_name'] ?? 'Instructor')); ?></td>
                                <td class="slots-cell"><?php echo ($section['enrolled_count'] ?? '0') . '/' . ($section['max_students'] ?? '0'); ?></td>
                                <td class="status-cell">
                                    <?php if ($section['is_enrolled']): ?>
                                    <span class="status-badge status-enrolled">Enrolled</span>
                                    <?php elseif ($section['is_pending']): ?>
                                    <span class="status-badge status-pending">Pending Approval</span>
                                    <?php elseif ($section['enrolled_count'] >= $section['max_students']): ?>
                                    <span class="status-badge status-full">Full</span>
                                    <?php else: ?>
                                    <span class="status-badge status-available">Available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="enroll-button-container">
                    <button type="submit" class="enroll-button" onclick="return validateAndConfirm()">
                        <i class="fas fa-paper-plane"></i>
                        Submit Pre-Enrollment Request
                    </button>
                </div>
            </form>
        </div>

        <?php endif; ?>
    </div>

    <script>
        // User dropdown functionality
        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Validation and confirmation - UPDATED messaging
        function validateAndConfirm() {
            const checkedBoxes = document.querySelectorAll('input[name="enroll_subjects[]"]:checked');
            
            if (checkedBoxes.length === 0) {
                alert('Please select at least one subject for pre-enrollment.');
                return false;
            }
            
            const subjectCount = checkedBoxes.length;
            const message = `Are you sure you want to submit a pre-enrollment request for ${subjectCount} subject${subjectCount > 1 ? 's' : ''}?\n\nYour request will be sent to the registrar for approval. You will be notified via email once your enrollment is processed.`;
            
            return confirm(message);
        }

        // Auto-refresh every 10 minutes to update enrollment counts
        setInterval(() => {
            window.location.reload();
        }, 600000);
    </script>

    <style>
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .filter-info {
            background-color: #e7f3ff;
            border: 1px solid #b6d7ff;
            color: #0056b3;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .red-text {
            color: #dc3545;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .status-enrolled {
            background-color: #d4edda;
            color: #155724;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .status-available {
            background-color: #cce7ff;
            color: #0056b3;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .status-full {
            background-color: #f8d7da;
            color: #721c24;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        /* Enhanced button styling for pre-enrollment */
        .enroll-button {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3);
        }
        
        .enroll-button:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.4);
        }
        
        .enroll-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Improved legend styling */
        .legend {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #007bff;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        /* Status color classes for student status */
        .status-regular {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-irregular {
            color: #ffc107;
            font-weight: 600;
        }
        
        .status-probation {
            color: #fd7e14;
            font-weight: 600;
        }
        
        .status-suspended {
            color: #dc3545;
            font-weight: 600;
        }
        
        .status-graduated {
            color: #6f42c1;
            font-weight: 600;
        }
        
        .status-inactive {
            color: #6c757d;
            font-weight: 600;
        }
        
        .status-leave {
            color: #17a2b8;
            font-weight: 600;
        }
        
        .status-returning {
            color: #20c997;
            font-weight: 600;
        }
        
        .status-default {
            color: #343a40;
            font-weight: 600;
        }
        
        /* Responsive improvements for mobile */
        @media (max-width: 768px) {
            .student-info-card {
                padding: 15px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .subjects-table {
                font-size: 0.8rem;
            }
            
            .subjects-table th,
            .subjects-table td {
                padding: 8px 4px;
            }
            
            .enroll-button {
                width: 100%;
                padding: 15px;
                font-size: 1.1rem;
            }
            
            .legend {
                padding: 12px 15px;
                font-size: 0.85rem;
            }
        }
        
        /* Print styles */
        @media print {
            .header,
            .filters-section,
            .enroll-button-container,
            .alert {
                display: none !important;
            }
            
            .main-container {
                margin: 0;
                padding: 20px;
            }
            
            .subjects-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .subjects-table th,
            .subjects-table td {
                border: 1px solid #000;
                padding: 8px;
            }
        }
    </style>
</body>
</html>