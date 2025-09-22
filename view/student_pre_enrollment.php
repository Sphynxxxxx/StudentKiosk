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
        SELECT u.*, sp.year_level, sp.program_id, sp.student_status, sp.section_id,
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

    // Get active academic year if none selected
    $selected_ay = isset($_GET['academic_year_id']) ? $_GET['academic_year_id'] : null;
    if (!$selected_ay) {
        $stmt = $pdo->prepare("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $active_ay = $stmt->fetch(PDO::FETCH_ASSOC);
        $selected_ay = $active_ay ? $active_ay['id'] : null;
    }

    // Get available class sections for enrollment
    $available_sections = [];
    if ($selected_ay && isset($student['program_id']) && $student['program_id']) {
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
                CASE WHEN e_student.id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled
            FROM class_sections cs
            INNER JOIN subjects s ON cs.subject_id = s.id
            INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
            INNER JOIN users u ON cs.faculty_id = u.id
            LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
            LEFT JOIN enrollments e_student ON cs.id = e_student.class_section_id 
                AND e_student.student_id = ? AND e_student.status = 'enrolled'
            WHERE ay.id = ? 
                AND cs.status = 'active'
                AND s.status = 'active'
            GROUP BY cs.id, s.id, ay.id, u.id
            ORDER BY s.course_code, cs.section_name
        ");
        $stmt->execute([$_SESSION['student_id'], $selected_ay]);
        $available_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Handle enrollment actions
    if ($_POST && isset($_POST['enroll_subjects']) && is_array($_POST['enroll_subjects'])) {
        $enrolled_count = 0;
        $error_count = 0;
        
        foreach ($_POST['enroll_subjects'] as $class_section_id) {
            $class_section_id = (int)$class_section_id;
            
            // Check if student is already enrolled
            $stmt = $pdo->prepare("
                SELECT id FROM enrollments 
                WHERE student_id = ? AND class_section_id = ? AND status = 'enrolled'
            ");
            $stmt->execute([$_SESSION['student_id'], $class_section_id]);
            
            if (!$stmt->fetch()) {
                // Check if class is not full
                $stmt = $pdo->prepare("
                    SELECT cs.max_students, COUNT(e.id) as enrolled_count
                    FROM class_sections cs
                    LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
                    WHERE cs.id = ?
                    GROUP BY cs.id, cs.max_students
                ");
                $stmt->execute([$class_section_id]);
                $class_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($class_info && $class_info['enrolled_count'] < $class_info['max_students']) {
                    // Enroll student
                    $stmt = $pdo->prepare("
                        INSERT INTO enrollments (student_id, class_section_id, status)
                        VALUES (?, ?, 'enrolled')
                    ");
                    $stmt->execute([$_SESSION['student_id'], $class_section_id]);
                    $enrolled_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($enrolled_count > 0) {
            $success_message = "Successfully enrolled in $enrolled_count subject(s)!";
        }
        if ($error_count > 0) {
            $error_message = "$error_count subject(s) could not be enrolled (already enrolled or class full).";
        }
        
        // Refresh data
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
    <style>
        .enrollment-form {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .form-header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .form-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .student-details {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            gap: 0.5rem;
        }

        .detail-label {
            font-weight: 600;
            color: #374151;
            min-width: 80px;
        }

        .detail-value {
            color: #6b7280;
        }

        .subjects-section {
            padding: 1.5rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-header h3 {
            margin: 0;
            color: #1f2937;
            font-size: 1.1rem;
        }

        .subjects-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .subjects-table th {
            background: #f1f5f9;
            color: #374151;
            font-weight: 600;
            padding: 0.875rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.875rem;
        }

        .subjects-table td {
            padding: 0.875rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 0.875rem;
        }

        .subjects-table tr:hover {
            background: #f8fafc;
        }

        .subjects-table tr:last-child td {
            border-bottom: none;
        }

        .checkbox-cell {
            width: 40px;
            text-align: center;
        }

        .checkbox-cell input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #3b82f6;
        }

        .course-code {
            font-weight: 600;
            color: #1f2937;
            width: 80px;
        }

        .section-cell {
            text-align: center;
            width: 80px;
        }

        .credits-cell {
            text-align: center;
            width: 60px;
            font-weight: 500;
        }

        .schedule-cell {
            width: 120px;
            color: #6b7280;
        }

        .slots-cell {
            text-align: center;
            width: 80px;
            font-size: 0.8rem;
        }

        .status-cell {
            text-align: center;
            width: 80px;
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-available {
            background: #dcfce7;
            color: #166534;
        }

        .status-full {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-enrolled {
            background: #dbeafe;
            color: #1e40af;
        }

        .enroll-button-container {
            text-align: center;
            padding: 2rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .enroll-button {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .enroll-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .enroll-button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .legend {
            background: #fffbeb;
            border: 1px solid #fed7aa;
            color: #92400e;
            padding: 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .legend strong {
            color: #7c2d12;
        }

        .red-text {
            color: #dc2626;
            font-weight: 600;
        }

        .student-info-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-item {
            display: flex;
            padding: 0.75rem 1rem;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-item:last-child {
            border-right: none;
        }

        .info-item:nth-child(n+4) {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #374151;
            min-width: 100px;
            font-size: 0.875rem;
        }

        .info-value {
            color: #1f2937;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-regular { color: #059669; font-weight: 600; }
        .status-irregular { color: #d97706; font-weight: 600; }
        .status-probation { color: #dc2626; font-weight: 600; }
        .status-suspended { color: #991b1b; font-weight: 600; }
        .status-graduated { color: #7c3aed; font-weight: 600; }
        .status-inactive { color: #6b7280; font-weight: 600; }
        .status-leave { color: #0891b2; font-weight: 600; }
        .status-returning { color: #059669; font-weight: 600; }
        .status-default { color: #374151; font-weight: 600; }
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

        <?php if (empty($available_sections)): ?>
        <div class="empty-grades">
            <i class="fas fa-calendar-plus"></i>
            <h3>No Subjects Available</h3>
            <p>No subjects found for the selected academic year. Please select an academic year or contact the registrar.</p>
        </div>
        <?php else: ?>

        <!-- Enrollment Form -->
        <div class="enrollment-form">
            <!-- Student Details -->
            <div class="student-details">
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Course:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(($student['program_code'] ?? '') . ' - ' . ($student['program_name'] ?? 'Not Set')); ?></span>
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
                        * Click checkbox to select subject - <span class="red-text">STRICTLY NO CHANGES</span><br>
                        * Please check that you read the special instruction<br>
                        * Indicates your payment status in this semester data.
                    </div>

                    <div class="section-header">
                        <i class="fas fa-list"></i>
                        <h3>Available Subjects for Enrollment</h3>
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
                                    <?php if (!$section['is_enrolled'] && $section['enrolled_count'] < $section['max_students']): ?>
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
                        <i class="fas fa-check-circle"></i>
                        Enroll Selected Subjects
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

        // Validation and confirmation
        function validateAndConfirm() {
            const checkedBoxes = document.querySelectorAll('input[name="enroll_subjects[]"]:checked');
            
            if (checkedBoxes.length === 0) {
                alert('Please select at least one subject to enroll.');
                return false;
            }
            
            const subjectCount = checkedBoxes.length;
            const message = `Are you sure you want to enroll in ${subjectCount} subject${subjectCount > 1 ? 's' : ''}?`;
            
            return confirm(message);
        }

        // Auto-refresh every 10 minutes to update enrollment counts
        setInterval(() => {
            window.location.reload();
        }, 600000);
    </script>
</body>
</html>