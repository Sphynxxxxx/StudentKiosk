<?php
session_start();

// Check if faculty is logged in
if (!isset($_SESSION['faculty_id'])) {
    header('Location: ../faculty.php');
    exit();
}

// Include database configuration
require_once '../config/database.php';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'enroll_student':
                try {
                    // Check if student ID already exists in users table
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE student_id = ?");
                    $stmt->execute([$_POST['student_id']]);
                    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_user) {
                        // Check if student is already enrolled in this section
                        $stmt = $pdo->prepare("
                            SELECT id FROM enrollments 
                            WHERE student_id = ? AND class_section_id = ?
                        ");
                        $stmt->execute([$existing_user['id'], $_POST['class_section_id']]);
                        
                        if ($stmt->rowCount() > 0) {
                            $message = 'Student is already enrolled in this course section!';
                            $message_type = 'error';
                        } else {
                            // Check if section is full
                            $stmt = $pdo->prepare("
                                SELECT cs.max_students, COUNT(e.id) as current_count
                                FROM class_sections cs
                                LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
                                WHERE cs.id = ?
                                GROUP BY cs.id
                            ");
                            $stmt->execute([$_POST['class_section_id']]);
                            $section_info = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($section_info && $section_info['current_count'] >= $section_info['max_students']) {
                                $message = 'Cannot enroll: Section is full!';
                                $message_type = 'error';
                            } else {
                                // Enroll the existing student
                                $stmt = $pdo->prepare("
                                    INSERT INTO enrollments (student_id, class_section_id, enrollment_date, status) 
                                    VALUES (?, ?, NOW(), 'enrolled')
                                ");
                                $stmt->execute([$existing_user['id'], $_POST['class_section_id']]);
                                $message = 'Student enrolled successfully!';
                                $message_type = 'success';
                            }
                        }
                    } else {
                        // Create new student user first
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, email, password, first_name, last_name, role, student_id, status) 
                            VALUES (?, ?, ?, ?, ?, 'student', ?, 'active')
                        ");
                        
                        // Generate username from first name and last name
                        $username = strtolower(substr($_POST['first_name'], 0, 1) . $_POST['last_name']);
                        $username = preg_replace('/[^a-zA-Z0-9]/', '', $username); // Remove special characters
                        
                        // Check if username exists and add number if needed
                        $original_username = $username;
                        $counter = 1;
                        while (true) {
                            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                            $check_stmt->execute([$username]);
                            if ($check_stmt->rowCount() == 0) break;
                            $username = $original_username . $counter;
                            $counter++;
                        }
                        
                        // Default password (should be changed by student on first login)
                        $default_password = password_hash('student123', PASSWORD_DEFAULT);
                        
                        // Build full name with middle initial
                        $first_name = $_POST['first_name'];
                        if (!empty($_POST['middle_initial'])) {
                            $first_name .= ' ' . $_POST['middle_initial'] . '.';
                        }
                        
                        $stmt->execute([
                            $username,
                            $_POST['email'],
                            $default_password,
                            $first_name,
                            $_POST['last_name'],
                            $_POST['student_id']
                        ]);
                        
                        $new_user_id = $pdo->lastInsertId();
                        
                        // Now enroll the new student
                        $stmt = $pdo->prepare("
                            INSERT INTO enrollments (student_id, class_section_id, enrollment_date, status) 
                            VALUES (?, ?, NOW(), 'enrolled')
                        ");
                        $stmt->execute([$new_user_id, $_POST['class_section_id']]);
                        
                        $message = 'New student created and enrolled successfully! Default password: student123';
                        $message_type = 'success';
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        // Check which constraint failed
                        if (strpos($e->getMessage(), 'student_id') !== false) {
                            $message = 'Student ID already exists!';
                        } elseif (strpos($e->getMessage(), 'email') !== false) {
                            $message = 'Email address already exists!';
                        } elseif (strpos($e->getMessage(), 'username') !== false) {
                            $message = 'Username already exists!';
                        } else {
                            $message = 'Student already exists or is already enrolled!';
                        }
                        $message_type = 'error';
                    } else {
                        $message = 'Error enrolling student: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
                break;
                
            case 'delete_student':
                try {
                    // First, delete the enrollment record
                    $stmt = $pdo->prepare("
                        DELETE FROM enrollments 
                        WHERE student_id = ? AND class_section_id = ?
                    ");
                    $stmt->execute([$_POST['student_id'], $_POST['class_section_id']]);
                    
                    // Check if student has any other enrollments
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as enrollment_count 
                        FROM enrollments 
                        WHERE student_id = ?
                    ");
                    $stmt->execute([$_POST['student_id']]);
                    $enrollment_check = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // If no other enrollments exist, delete the student user account
                    if ($enrollment_check['enrollment_count'] == 0) {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$_POST['student_id']]);
                        $message = 'Student and user account deleted successfully!';
                    } else {
                        $message = 'Student removed from course successfully!';
                    }
                    
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting student: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
        }
    }
}

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
        <!-- Left Panel - Courses List -->
        <div class="card courses-panel">
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
                        <div class="subject-item" data-section-id="<?php echo $course['section_id']; ?>">
                            <div class="subject-header" onclick="selectCourse(<?php echo $course['section_id']; ?>, '<?php echo htmlspecialchars($course['course_code']); ?>', '<?php echo htmlspecialchars($course['course_name']); ?>')">
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
                                        <i class="fas fa-users"></i> Manage Students
                                    </button>
                                    <?php if ($course['schedule']): ?>
                                        <div class="schedule-text"><?php echo htmlspecialchars($course['schedule']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Enrollment Section - Hidden by default -->
                            <div class="enrollment-section" id="enrollment-<?php echo $course['section_id']; ?>" style="display: none;">
                                <div class="enrollment-header">
                                    <div class="enrollment-title">
                                        <i class="fas fa-user-plus"></i>
                                        Enroll New Student
                                    </div>
                                    <div class="capacity-indicator">
                                        <?php 
                                        $percentage = ($course['enrolled_count'] / $course['max_students']) * 100;
                                        $capacity_class = 'capacity-normal';
                                        if ($percentage >= 90) $capacity_class = 'capacity-full';
                                        elseif ($percentage >= 75) $capacity_class = 'capacity-warning';
                                        ?>
                                        <span><?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?></span>
                                        <div class="capacity-bar">
                                            <div class="capacity-fill <?php echo $capacity_class; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="enroll-student-form">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="enroll_student">
                                        <input type="hidden" name="class_section_id" value="<?php echo $course['section_id']; ?>">
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="student_id_<?php echo $course['section_id']; ?>">Student ID *</label>
                                                <input type="text" 
                                                       id="student_id_<?php echo $course['section_id']; ?>" 
                                                       name="student_id" 
                                                       placeholder="Enter student ID"
                                                       required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="first_name_<?php echo $course['section_id']; ?>">First Name *</label>
                                                <input type="text" 
                                                       id="first_name_<?php echo $course['section_id']; ?>" 
                                                       name="first_name" 
                                                       placeholder="Enter first name"
                                                       required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="last_name_<?php echo $course['section_id']; ?>">Last Name *</label>
                                                <input type="text" 
                                                       id="last_name_<?php echo $course['section_id']; ?>" 
                                                       name="last_name" 
                                                       placeholder="Enter last name"
                                                       required>
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="middle_initial_<?php echo $course['section_id']; ?>">Middle Initial</label>
                                                <input type="text" 
                                                       id="middle_initial_<?php echo $course['section_id']; ?>" 
                                                       name="middle_initial" 
                                                       placeholder="M.I."
                                                       maxlength="2">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="email_<?php echo $course['section_id']; ?>">Email *</label>
                                                <input type="email" 
                                                       id="email_<?php echo $course['section_id']; ?>" 
                                                       name="email" 
                                                       placeholder="student@example.com"
                                                       required>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus"></i>
                                                Enroll Student
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Panel - Student Details -->
        <div class="card students-panel" id="studentsPanel">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-users"></i>
                    <span id="selectedCourseTitle">ENROLLED STUDENTS</span>
                </h2>
                <div class="card-subtitle" id="selectedCourseSubtitle">Select a course to view students</div>
            </div>
            
            <div class="students-content" id="studentsContent">
                <div class="students-placeholder">
                    <i class="fas fa-mouse-pointer"></i>
                    <h3>Select a Course</h3>
                    <p>Click on a course from the left panel to view and manage enrolled students.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon" id="modalIcon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="modal-title" id="modalTitle">Success</div>
                <div class="modal-message" id="modalMessage">Operation completed successfully</div>
            </div>
            <div class="modal-footer">
                <button class="modal-close-btn" onclick="closeMessageModal()">OK</button>
            </div>
        </div>
    </div>

    <script>
        // Function to handle grade input
        function inputGrades(studentId, sectionId, studentName) {
            if (confirm(`Input grades for ${studentName}?`)) {
                window.location.href = `input_grades.php?student_id=${studentId}&section_id=${sectionId}`;
            }
        }

        let currentSelectedSection = null;

        // Show message modal function
        function showMessageModal(type, title, message) {
            const modal = document.getElementById('messageModal');
            const icon = document.getElementById('modalIcon');
            const titleEl = document.getElementById('modalTitle');
            const messageEl = document.getElementById('modalMessage');
            
            // Set content
            titleEl.textContent = title;
            messageEl.textContent = message;
            
            // Set icon and styling
            if (type === 'success') {
                icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                icon.className = 'modal-icon success';
            } else {
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                icon.className = 'modal-icon error';
            }
            
            // Show modal
            modal.style.display = 'block';
        }

        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }

        // Check for PHP messages and show modal
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showMessageModal(
                '<?php echo $message_type; ?>', 
                '<?php echo $message_type === 'success' ? 'Success' : 'Error'; ?>', 
                '<?php echo addslashes($message); ?>'
            );
        });
        <?php endif; ?>

        function selectCourse(sectionId, courseCode, courseName) {
            // Hide all enrollment sections first
            document.querySelectorAll('.enrollment-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Remove active class from all subject items
            document.querySelectorAll('.subject-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to selected item
            document.querySelector(`[data-section-id="${sectionId}"]`).classList.add('active');
            
            // Show enrollment section for selected course
            document.getElementById(`enrollment-${sectionId}`).style.display = 'block';
            
            // Update right panel title
            document.getElementById('selectedCourseTitle').textContent = `ENROLLED STUDENTS - ${courseCode}`;
            document.getElementById('selectedCourseSubtitle').textContent = courseName;
            
            currentSelectedSection = sectionId;
            
            // Load students for selected course
            loadStudents(sectionId, courseCode);
        }

        function loadStudents(sectionId, courseCode) {
            const container = document.getElementById('studentsContent');
            
            // Show loading state
            container.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <span>Loading students...</span>
                </div>
            `;
            
            // Make AJAX request
            fetch('../api/get_enrolled_students.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `section_id=${sectionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayEnrolledStudents(container, data.students, sectionId);
                } else {
                    container.innerHTML = '<div class="no-students">Failed to load students</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = '<div class="no-students">Error loading students</div>';
            });
        }

        function displayEnrolledStudents(container, students, sectionId) {
            if (students.length === 0) {
                container.innerHTML = '<div class="no-students">No students enrolled in this course yet</div>';
                return;
            }
            
            let html = `
                <div class="students-header">
                    <i class="fas fa-users"></i>
                    ENROLLED STUDENTS (${students.length})
                </div>
                <div class="enrolled-students-list">
            `;
            
            students.forEach((student, index) => {
                const enrollmentDate = new Date(student.enrollment_date).toLocaleDateString();
                html += `
                    <div class="student-item-enrolled">
                        <div class="student-info-enrolled">
                            <div class="student-name-enrolled">${student.full_name}</div>
                            <div class="student-id-enrolled">Student ID: ${student.student_id}</div>
                            <div class="enrollment-date">Enrolled: ${enrollmentDate}</div>
                        </div>
                        <div class="student-actions">
                            <button class="btn btn-success" onclick="inputGrades(${student.user_id}, ${sectionId}, '${student.full_name}')">
                                <i class="fas fa-edit"></i>
                                Grades
                            </button>
                            <button class="btn btn-danger" onclick="deleteStudent(${student.user_id}, ${sectionId}, '${student.full_name}')">
                                <i class="fas fa-trash"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function deleteStudent(studentId, sectionId, studentName) {
            if (confirm(`⚠️ WARNING: This will permanently delete ${studentName} from the system.\n\nThis action will:\n• Remove the student from this course\n• Remove all associated data\n\nThis action CANNOT be undone. Are you sure you want to proceed?`)) {
                // Create and submit a form for deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_student';
                
                const studentIdInput = document.createElement('input');
                studentIdInput.type = 'hidden';
                studentIdInput.name = 'student_id';
                studentIdInput.value = studentId;
                
                const sectionIdInput = document.createElement('input');
                sectionIdInput.type = 'hidden';
                sectionIdInput.name = 'class_section_id';
                sectionIdInput.value = sectionId;
                
                form.appendChild(actionInput);
                form.appendChild(studentIdInput);
                form.appendChild(sectionIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-refresh enrolled students every 30 seconds
        setInterval(() => {
            if (currentSelectedSection) {
                loadStudents(currentSelectedSection, '');
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('messageModal');
            if (event.target === modal) {
                closeMessageModal();
            }
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMessageModal();
            }
        });
    </script>
</body>
</html>