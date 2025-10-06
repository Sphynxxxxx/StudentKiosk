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
    // Get student information
    $stmt = $pdo->prepare("
        SELECT u.*, sp.year_level, sp.program_id, p.program_name, p.program_code,
               s.section_name
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

    // Get student's enrollments and grades for selected academic year
    $grades = [];
    if ($selected_ay) {
        $stmt = $pdo->prepare("
            SELECT 
                s.course_code,
                s.subject_name,
                s.credits,
                g.midterm_grade,
                g.final_grade,
                g.overall_grade,
                g.letter_grade,
                g.remarks,
                ay.year_start,
                ay.year_end,
                ay.semester,
                cs.section_name
            FROM enrollments e
            INNER JOIN class_sections cs ON e.class_section_id = cs.id
            INNER JOIN subjects s ON cs.subject_id = s.id
            INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
            LEFT JOIN grades g ON e.id = g.enrollment_id
            WHERE e.student_id = ? AND ay.id = ? AND e.status = 'enrolled'
            ORDER BY s.course_code
        ");
        $stmt->execute([$_SESSION['student_id'], $selected_ay]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate GPA
    $total_grade_points = 0;
    $total_credits = 0;
    $gpa = 0;
    $completed_subjects = 0;
    $incomplete_subjects = 0;
    $dropped_subjects = 0;
    
    foreach ($grades as $grade) {
        // Check if grade is INC or DRP
        if (strtoupper($grade['letter_grade'] ?? '') === 'INC') {
            $incomplete_subjects++;
            continue; // Don't include INC in GPA calculation
        }
        
        if (strtoupper($grade['letter_grade'] ?? '') === 'DRP') {
            $dropped_subjects++;
            continue; // Don't include DRP in GPA calculation
        }
        
        // Only calculate GPA for completed subjects with numeric grades
        if ($grade['overall_grade'] && is_numeric($grade['overall_grade']) && $grade['credits']) {
            $total_grade_points += ($grade['overall_grade'] * $grade['credits']);
            $total_credits += $grade['credits'];
            $completed_subjects++;
        }
    }
    
    if ($total_credits > 0) {
        $gpa = $total_grade_points / $total_credits;
    }

} catch (PDOException $e) {
    error_log("Student dashboard error: " . $e->getMessage());
    // Redirect to login on database error
    header('Location: ../auth/student_logout.php');
    exit();
}

// Group grades by semester
$grades_by_semester = [];
foreach ($grades as $grade) {
    $semester_key = $grade['year_start'] . '-' . $grade['year_end'] . ', ' . ucfirst($grade['semester']) . ' Semester';
    if (!isset($grades_by_semester[$semester_key])) {
        $grades_by_semester[$semester_key] = [];
    }
    $grades_by_semester[$semester_key][] = $grade;
}

// Helper function to get grade color class
function getGradeClass($letter_grade, $overall_grade = null) {
    // Check for INC or DRP first
    if (strtoupper($letter_grade ?? '') === 'INC') return 'grade-incomplete';
    if (strtoupper($letter_grade ?? '') === 'DRP') return 'grade-dropped';
    
    // If no numeric grade, return default
    if (!$overall_grade || !is_numeric($overall_grade)) return 'grade-pending';
    
    // Philippine grading system (lower is better: 1.00 is highest, 5.00 is failed)
    if ($overall_grade >= 1.00 && $overall_grade <= 1.25) return 'grade-excellent';
    if ($overall_grade >= 1.26 && $overall_grade <= 1.75) return 'grade-very-good';
    if ($overall_grade >= 1.76 && $overall_grade <= 2.25) return 'grade-good';
    if ($overall_grade >= 2.26 && $overall_grade <= 2.75) return 'grade-satisfactory';
    if ($overall_grade >= 2.76 && $overall_grade <= 3.00) return 'grade-passing';
    return 'grade-failing';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/student_dashboard.css" rel="stylesheet">
    <style>
        /* Additional styles for INC/DRP support */
        .gpa-card.warning {
            border-left: 4px solid #ffc107;
        }

        .gpa-card.warning .gpa-value {
            color: #ffc107;
        }

        .gpa-card.danger {
            border-left: 4px solid #dc3545;
        }

        .gpa-card.danger .gpa-value {
            color: #dc3545;
        }

        .grade-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .grade-status-badge.incomplete {
            color: #856404;
        }

        .grade-status-badge.incomplete i {
            color: #ffc107;
        }

        .grade-status-badge.dropped {
            color: #721c24;
        }

        .grade-status-badge.dropped i {
            color: #dc3545;
        }

        .grade-status-message {
            font-size: 0.85em;
            color: #6c757d;
            font-style: italic;
            margin-top: 5px;
        }

        .grade-incomplete {
            background-color: #fff3cd;
            color: #856404;
            border: 2px solid #ffc107;
        }

        .grade-dropped {
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #dc3545;
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
                <h1>Grades</h1>
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
                    
                    <div class="filter-group">
                        <select name="term" class="filter-select">
                            <option value="">-Term-</option>
                            <option value="1st">1st Semester</option>
                            <option value="2nd">2nd Semester</option>
                            <option value="summer">Summer</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="apply-btn">Apply</button>
                </form>
            </div>
        </div>

        <div class="search-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Enter subject code.." id="courseSearch">
            </div>
        </div>

        <?php if (empty($grades_by_semester)): ?>
        <div class="empty-grades">
            <i class="fas fa-graduation-cap"></i>
            <h3>No Grades Available</h3>
            <p>No grades found for the selected academic year. Please check with your instructor or select a different academic year.</p>
        </div>
        <?php else: ?>
        <!-- GPA Summary -->
        <div class="gpa-summary">
            <div class="gpa-card">
                <div class="gpa-label">Current GPA</div>
                <div class="gpa-value"><?php echo number_format($gpa, 2); ?></div>
            </div>
            <div class="gpa-card">
                <div class="gpa-label">Total Credits</div>
                <div class="gpa-value"><?php echo $total_credits; ?></div>
            </div>
            <div class="gpa-card">
                <div class="gpa-label">Completed</div>
                <div class="gpa-value"><?php echo $completed_subjects; ?></div>
            </div>
            <?php if ($incomplete_subjects > 0): ?>
            <div class="gpa-card warning">
                <div class="gpa-label">Incomplete</div>
                <div class="gpa-value"><?php echo $incomplete_subjects; ?></div>
            </div>
            <?php endif; ?>
            <?php if ($dropped_subjects > 0): ?>
            <div class="gpa-card danger">
                <div class="gpa-label">Dropped</div>
                <div class="gpa-value"><?php echo $dropped_subjects; ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Grades Table -->
        <?php foreach ($grades_by_semester as $semester => $semester_grades): ?>
        <div class="semester-section">
            <div class="semester-header">
                Academic Year <?php echo htmlspecialchars($semester); ?>
            </div>
            
            <div class="grades-table">
                <div class="table-header">
                    <div class="header-course">Subject</div>
                    <div class="header-grade">Grade</div>
                    <div class="header-credit">Credit</div>
                </div>
                
                <?php foreach ($semester_grades as $grade): ?>
                <div class="table-row" data-course="<?php echo strtolower($grade['course_code'] ?? ''); ?>">
                    <div class="course-info">
                        <div class="course-code"><?php echo htmlspecialchars($grade['course_code'] ?? 'N/A'); ?></div>
                        <div class="course-name"><?php echo htmlspecialchars($grade['subject_name'] ?? 'Subject Name Not Available'); ?></div>
                        <?php if (!empty($grade['section_name'])): ?>
                        <div class="course-section">Section: <?php echo htmlspecialchars($grade['section_name']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="grade-info">
                        <?php 
                        // Check if grade is INC or DRP
                        $isIncomplete = (strtoupper($grade['letter_grade'] ?? '') === 'INC');
                        $isDropped = (strtoupper($grade['letter_grade'] ?? '') === 'DRP');
                        
                        if ($isIncomplete): ?>
                            <div class="grade-status-badge incomplete">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>INCOMPLETE</span>
                            </div>
                        <?php elseif ($isDropped): ?>
                            <div class="grade-status-badge dropped">
                                <i class="fas fa-times-circle"></i>
                                <span>DROPPED</span>
                            </div>

                        <?php elseif (!empty($grade['overall_grade']) && is_numeric($grade['overall_grade'])): ?>
                            <div class="grade-value <?php echo getGradeClass($grade['letter_grade'], $grade['overall_grade']); ?>">
                                <?php echo number_format($grade['overall_grade'], 2); ?>
                            </div>
                            <?php if (!empty($grade['letter_grade'])): ?>
                            <div class="letter-grade"><?php echo htmlspecialchars($grade['letter_grade']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($grade['remarks'])): ?>
                            <div class="grade-remarks"><?php echo htmlspecialchars($grade['remarks']); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="grade-pending">
                                <i class="fas fa-clock"></i>
                                Pending
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="credit-info">
                        <?php echo $grade['credits'] ?? '0'; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
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

        // Course search functionality
        document.getElementById('courseSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.table-row');
            
            rows.forEach(row => {
                const courseCode = row.getAttribute('data-course') || '';
                const courseName = row.querySelector('.course-name')?.textContent?.toLowerCase() || '';
                
                if (courseCode.includes(searchTerm) || courseName.includes(searchTerm)) {
                    row.style.display = 'grid';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Auto-refresh every 5 minutes
        setInterval(() => {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>