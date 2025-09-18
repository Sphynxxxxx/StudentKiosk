<?php
session_start();

// Check if faculty is logged in
if (!isset($_SESSION['faculty_id'])) {
    header('Location: ../faculty.php');
    exit();
}

// Include database configuration
require_once '../config/database.php';

try {
    // Get faculty's assigned courses with class sections
    $stmt = $pdo->prepare("
        SELECT 
            c.id as course_id,
            c.course_code,
            c.course_name,
            cs.id as section_id,
            cs.section_name,
            cs.schedule,
            cs.room,
            cs.max_students,
            ay.year_start,
            ay.year_end,
            ay.semester,
            COUNT(e.student_id) as enrolled_count
        FROM courses c
        INNER JOIN class_sections cs ON c.id = cs.course_id
        INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
        LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
        WHERE cs.faculty_id = ? AND ay.is_active = 1 AND cs.status = 'active'
        GROUP BY c.id, cs.id
        ORDER BY c.course_code, cs.section_name
    ");
    $stmt->execute([$_SESSION['faculty_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Faculty dashboard error: " . $e->getMessage());
    $courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/faculty.css" rel="stylesheet">
    <style>
        
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
                    <div class="system-title">FACULTY DASHBOARD</div>
                </div>
            </div>
            <div class="user-section">
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-info" onclick="toggleDropdown()">
                        <i class="fas fa-user-tie"></i>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['faculty_name']); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="dropdown-user-name"><?php echo htmlspecialchars($_SESSION['faculty_name']); ?></div>
                            <div class="dropdown-user-role">Faculty Member</div>
                        </div>
                        <a href="faculty_profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../auth/faculty_logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav-bar">
        <div class="nav-content">
            <div class="nav-title">
                <i class="fas fa-chalkboard-teacher"></i>
                Course Management System
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Courses List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-book"></i>
                    ASSIGNED COURSES
                </h2>
                <div class="card-subtitle">Academic Year <?php echo date('Y'); ?>-<?php echo date('Y')+1; ?></div>
            </div>
            
            <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No Courses Assigned</h3>
                    <p>You currently have no courses assigned for this academic year.</p>
                </div>
            <?php else: ?>
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($courses); ?></div>
                        <div class="stat-label">Total Courses</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo array_sum(array_column($courses, 'enrolled_count')); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo array_sum(array_column($courses, 'max_students')); ?></div>
                        <div class="stat-label">Max Capacity</div>
                    </div>
                </div>

                <div class="subjects-list">
                    <?php foreach ($courses as $course): ?>
                        <div class="subject-item">
                            <div class="subject-header" onclick="toggleStudents(<?php echo $course['section_id']; ?>, '<?php echo htmlspecialchars($course['course_code']); ?>')">
                                <div class="subject-info">
                                    <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                    <div class="subject-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-layer-group"></i>
                                            <span>Section <?php echo htmlspecialchars($course['section_name']); ?></span>
                                        </div>
                                        <?php if ($course['room']): ?>
                                            <div class="meta-item">
                                                <i class="fas fa-door-open"></i>
                                                <span><?php echo htmlspecialchars($course['room']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="meta-item">
                                            <span class="enrollment-count">
                                                <?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="schedule-info">
                                    <button class="view-btn">
                                        <i class="fas fa-users"></i> View Students
                                    </button>
                                    <?php if ($course['schedule']): ?>
                                        <div class="schedule-text"><?php echo htmlspecialchars($course['schedule']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div id="students-<?php echo $course['section_id']; ?>" class="students-section">
                                <div class="students-header">
                                    <i class="fas fa-users"></i>
                                    ENROLLED STUDENTS - <?php echo htmlspecialchars($course['course_code']); ?>
                                </div>
                                <div class="students-list" id="students-list-<?php echo $course['section_id']; ?>">
                                    <div class="loading">
                                        <div class="spinner"></div>
                                        <span>Loading students...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Student Details Card -->
        <div class="card" id="student-list-card" style="display: none;">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-user-graduate"></i>
                    STUDENT DETAILS
                </h2>
                <div id="selected-subject" class="card-subtitle"></div>
            </div>
            <div id="student-details">
                <div class="empty-state">
                    <i class="fas fa-hand-pointer"></i>
                    <h3>Select a Course</h3>
                    <p>Click on any course above to view enrolled students and manage grades.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentlyOpenSection = null;

        function toggleStudents(sectionId, courseCode) {
            const studentsSection = document.getElementById(`students-${sectionId}`);
            const studentCard = document.getElementById('student-list-card');
            const selectedSubject = document.getElementById('selected-subject');
            const studentDetails = document.getElementById('student-details');
            
            // If clicking the same section, close it
            if (currentlyOpenSection === sectionId) {
                studentsSection.classList.remove('show');
                studentCard.style.display = 'none';
                currentlyOpenSection = null;
                return;
            }
            
            // Close currently open section
            if (currentlyOpenSection) {
                document.getElementById(`students-${currentlyOpenSection}`).classList.remove('show');
            }
            
            // Open new section
            studentsSection.classList.add('show');
            currentlyOpenSection = sectionId;
            
            // Show and update student card
            studentCard.style.display = 'block';
            selectedSubject.textContent = courseCode;
            
            // Load students via AJAX
            loadStudents(sectionId, courseCode);
        }

        function loadStudents(sectionId, courseCode) {
            const container = document.getElementById(`students-list-${sectionId}`);
            const studentDetails = document.getElementById('student-details');
            
            // Show loading state
            container.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <span>Loading students...</span>
                </div>
            `;
            studentDetails.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <span>Loading student details...</span>
                </div>
            `;
            
            // Make AJAX request
            fetch('../api/get_students.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `section_id=${sectionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayStudents(container, data.students, sectionId);
                    displayStudentsInCard(studentDetails, data.students, courseCode);
                } else {
                    container.innerHTML = '<div class="no-students">Failed to load students</div>';
                    studentDetails.innerHTML = '<div class="no-students">Failed to load students</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = '<div class="no-students">Error loading students</div>';
                studentDetails.innerHTML = '<div class="no-students">Error loading students</div>';
            });
        }

        function displayStudents(container, students, sectionId) {
            if (students.length === 0) {
                container.innerHTML = '<div class="no-students">No students enrolled in this course yet</div>';
                return;
            }
            
            let html = '';
            students.forEach((student, index) => {
                html += `
                    <div class="student-item">
                        <div>
                            <div class="student-name">${student.full_name}</div>
                            <div class="student-id">ID: ${student.student_id}</div>
                        </div>
                        <button class="input-grades-btn" onclick="inputGrades(${student.student_id}, ${sectionId}, '${student.full_name}')">
                            <i class="fas fa-edit"></i>
                            Input Grades
                        </button>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function displayStudentsInCard(container, students, courseCode) {
            if (students.length === 0) {
                container.innerHTML = '<div class="no-students">No students enrolled in this course yet</div>';
                return;
            }
            
            let html = '<div class="student-details">';
            students.forEach((student, index) => {
                html += `
                    <div class="student-item">
                        <div>
                            <div class="student-name">${student.full_name}</div>
                            <div class="student-id">Student ID: ${student.student_id}</div>
                        </div>
                        <button class="input-grades-btn" onclick="inputGrades(${student.student_id}, '${courseCode}', '${student.full_name}')">
                            <i class="fas fa-edit"></i>
                            Manage Grades
                        </button>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function inputGrades(studentId, sectionIdOrCourseCode, studentName) {
            // You can implement a modal or redirect to grades input page
            if (confirm(`Input grades for ${studentName}?`)) {
                // Redirect to grades input page or open modal
                window.location.href = `input_grades.php?student_id=${studentId}&section=${sectionIdOrCourseCode}`;
            }
        }

        // Auto-refresh data every 30 seconds
        setInterval(() => {
            if (currentlyOpenSection) {
                const courseCode = document.getElementById('selected-subject').textContent;
                loadStudents(currentlyOpenSection, courseCode);
            }
        }, 30000);

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

        // Close dropdown on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('userDropdown').classList.remove('active');
            }
        });
    </script>
</body>
</html>