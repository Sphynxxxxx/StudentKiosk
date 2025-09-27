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
                        $message = 'Student removed from subject successfully!';
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
    // Get faculty's assigned subjects with class sections
    $stmt = $pdo->prepare("
        SELECT 
            s.id as subject_id,
            s.course_code,
            s.subject_name,
            cs.id as section_id,
            cs.section_name,
            cs.schedule,
            cs.room,
            cs.max_students,
            ay.year_start,
            ay.year_end,
            ay.semester,
            COUNT(e.student_id) as enrolled_count
        FROM subjects s
        INNER JOIN class_sections cs ON s.id = cs.subject_id
        INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
        LEFT JOIN enrollments e ON cs.id = e.class_section_id AND e.status = 'enrolled'
        WHERE cs.faculty_id = ? AND ay.is_active = 1 AND cs.status = 'active'
        GROUP BY s.id, cs.id
        ORDER BY s.course_code, cs.section_name
    ");
    $stmt->execute([$_SESSION['faculty_id']]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Faculty dashboard error: " . $e->getMessage());
    $subjects = [];
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
                Subject Management System
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Left Panel - Subjects List -->
        <div class="card courses-panel">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-book"></i>
                    ASSIGNED SUBJECTS
                </h2>
                <div class="card-subtitle">Academic Year <?php echo date('Y'); ?>-<?php echo date('Y')+1; ?></div>
            </div>
            
            <?php if (empty($subjects)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No Subjects Assigned</h3>
                    <p>You currently have no subjects assigned for this academic year.</p>
                </div>
            <?php else: ?>
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($subjects); ?></div>
                        <div class="stat-label">Total Subjects</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo array_sum(array_column($subjects, 'enrolled_count')); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo array_sum(array_column($subjects, 'max_students')); ?></div>
                        <div class="stat-label">Max Capacity</div>
                    </div>
                </div>

                <div class="subjects-list">
                    <?php foreach ($subjects as $subject): ?>
                        <div class="subject-item" data-section-id="<?php echo $subject['section_id']; ?>">
                            <div class="subject-header" onclick="selectSubject(<?php echo $subject['section_id']; ?>, '<?php echo htmlspecialchars($subject['course_code']); ?>', '<?php echo htmlspecialchars($subject['subject_name']); ?>')">
                                <div class="subject-info">
                                    <div class="course-code"><?php echo htmlspecialchars($subject['course_code']); ?></div>
                                    <div class="course-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                    <div class="subject-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-layer-group"></i>
                                            <span>Section <?php echo htmlspecialchars($subject['section_name']); ?></span>
                                        </div>
                                        <?php if ($subject['room']): ?>
                                            <div class="meta-item">
                                                <i class="fas fa-door-open"></i>
                                                <span><?php echo htmlspecialchars($subject['room']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="meta-item">
                                            <span class="enrollment-count">
                                                <?php echo $subject['enrolled_count']; ?>/<?php echo $subject['max_students']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="schedule-info">
                                    <button class="view-btn">
                                        <i class="fas fa-users"></i> Manage Students
                                    </button>
                                    <?php if ($subject['schedule']): ?>
                                        <div class="schedule-text"><?php echo htmlspecialchars($subject['schedule']); ?></div>
                                    <?php endif; ?>
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
                    <span id="selectedSubjectTitle">ENROLLED STUDENTS</span>
                </h2>
                <div class="card-subtitle" id="selectedSubjectSubtitle">Select a subject to view students</div>
            </div>
            
            <div class="students-content" id="studentsContent">
                <div class="students-placeholder">
                    <i class="fas fa-mouse-pointer"></i>
                    <h3>Select a Subject</h3>
                    <p>Click on a subject from the left panel to view and manage enrolled students.</p>
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

        function selectSubject(sectionId, subjectCode, subjectName) {
            // Remove active class from all subject items
            document.querySelectorAll('.subject-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to selected item
            document.querySelector(`[data-section-id="${sectionId}"]`).classList.add('active');
            
            // Update right panel title
            document.getElementById('selectedSubjectTitle').textContent = `ENROLLED STUDENTS - ${subjectCode}`;
            document.getElementById('selectedSubjectSubtitle').textContent = subjectName;
            
            currentSelectedSection = sectionId;
            
            // Load students for selected subject
            loadStudents(sectionId, subjectCode);
        }

        function loadStudents(sectionId, subjectCode) {
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
                container.innerHTML = '<div class="no-students">No students enrolled in this subject yet</div>';
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
            if (confirm(`⚠️ WARNING: This will permanently delete ${studentName} from the system.\n\nThis action will:\n• Remove the student from this subject\n• Remove all associated data\n\nThis action CANNOT be undone. Are you sure you want to proceed?`)) {
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