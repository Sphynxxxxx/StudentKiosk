<?php
// Include database configuration
require_once '../config/database.php';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_subject':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO subjects (course_code, subject_name, description, credits, department_id, status) 
                        VALUES (?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([
                        $_POST['course_code'],
                        $_POST['subject_name'],
                        $_POST['description'],
                        $_POST['credits'],
                        $_POST['department_id']
                    ]);
                    $message = 'Subject added successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $message = 'Subject code already exists!';
                        $message_type = 'error';
                    } else {
                        $message = 'Error adding subject: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
                break;
                
            case 'edit_subject':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE subjects 
                        SET course_code = ?, subject_name = ?, description = ?, credits = ?, department_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['course_code'],
                        $_POST['subject_name'],
                        $_POST['description'],
                        $_POST['credits'],
                        $_POST['department_id'],
                        $_POST['subject_id']
                    ]);
                    $message = 'Subject updated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $message = 'Subject code already exists!';
                        $message_type = 'error';
                    } else {
                        $message = 'Error updating subject: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
                break;
                
            case 'delete_subject':
                try {
                    // Check if subject is assigned to any class sections
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_sections WHERE subject_id = ?");
                    $stmt->execute([$_POST['subject_id']]);
                    $section_count = $stmt->fetchColumn();
                    
                    if ($section_count > 0) {
                        $message = 'Cannot delete subject: It is assigned to ' . $section_count . ' class section(s). Please remove all assignments first.';
                        $message_type = 'error';
                    } else {
                        // Check if subject has any enrolled students (if you have an enrollments table)
                        // $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE subject_id = ?");
                        // $stmt->execute([$_POST['subject_id']]);
                        // $enrollment_count = $stmt->fetchColumn();
                        
                        // if ($enrollment_count > 0) {
                        //     $message = 'Cannot delete subject: It has enrolled students. Please unenroll all students first.';
                        //     $message_type = 'error';
                        // } else {
                            // Safe to delete
                            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                            $stmt->execute([$_POST['subject_id']]);
                            $message = 'Subject deleted successfully!';
                            $message_type = 'success';
                        // }
                    }
                } catch (PDOException $e) {
                    $message = 'Error deleting subject: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'assign_faculty':
                try {
                    // Check if class section already exists for this subject and academic year
                    $stmt = $pdo->prepare("
                        SELECT id FROM class_sections 
                        WHERE subject_id = ? AND academic_year_id = ? AND section_name = ?
                    ");
                    $stmt->execute([$_POST['subject_id'], $_POST['academic_year_id'], $_POST['section_name']]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = 'Section already exists for this subject and academic year!';
                        $message_type = 'error';
                    } else {
                        // Create new class section
                        $stmt = $pdo->prepare("
                            INSERT INTO class_sections (subject_id, faculty_id, academic_year_id, section_name, schedule, room, max_students, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                        ");
                        $stmt->execute([
                            $_POST['subject_id'],
                            $_POST['faculty_id'],
                            $_POST['academic_year_id'],
                            $_POST['section_name'],
                            $_POST['schedule'],
                            $_POST['room'],
                            $_POST['max_students']
                        ]);
                        $message = 'Faculty assigned to subject successfully!';
                        $message_type = 'success';
                    }
                } catch (PDOException $e) {
                    $message = 'Error assigning faculty: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'delete_section':
                try {
                    // Check if section has any enrolled students (if you have an enrollments table)
                    // $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE class_section_id = ?");
                    // $stmt->execute([$_POST['section_id']]);
                    // $enrollment_count = $stmt->fetchColumn();
                    
                    // if ($enrollment_count > 0) {
                    //     $message = 'Cannot delete class section: It has ' . $enrollment_count . ' enrolled student(s). Please unenroll all students first.';
                    //     $message_type = 'error';
                    // } else {
                        // Safe to delete
                        $stmt = $pdo->prepare("DELETE FROM class_sections WHERE id = ?");
                        $stmt->execute([$_POST['section_id']]);
                        $message = 'Class section deleted successfully!';
                        $message_type = 'success';
                    // }
                } catch (PDOException $e) {
                    $message = 'Error deleting class section: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get all subjects with department information
$stmt = $pdo->prepare("
    SELECT s.*, d.name as department_name, d.code as department_code
    FROM subjects s
    JOIN departments d ON s.department_id = d.id
    ORDER BY s.course_code
");
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all departments for dropdown
$stmt = $pdo->prepare("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all faculty members for dropdown
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.employee_id, 
           fp.department_id, d.name as department_name
    FROM users u
    LEFT JOIN faculty_profiles fp ON u.id = fp.user_id
    LEFT JOIN departments d ON fp.department_id = d.id
    WHERE u.role = 'faculty' AND u.status = 'active'
    ORDER BY u.first_name, u.last_name
");
$stmt->execute();
$faculty_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active academic years
$stmt = $pdo->prepare("
    SELECT id, CONCAT(year_start, '-', year_end, ' (', semester, ' Semester)') as year_label
    FROM academic_years
    ORDER BY year_start DESC, semester
");
$stmt->execute();
$academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get class sections with faculty assignments
$stmt = $pdo->prepare("
    SELECT cs.*, s.course_code, s.subject_name,
           CONCAT(u.first_name, ' ', u.last_name) as faculty_name,
           CONCAT(ay.year_start, '-', ay.year_end, ' (', ay.semester, ' Semester)') as academic_year
    FROM class_sections cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN users u ON cs.faculty_id = u.id
    JOIN academic_years ay ON cs.academic_year_id = ay.id
    ORDER BY s.course_code, cs.section_name
");
$stmt->execute();
$class_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin_dashboard.css" rel="stylesheet">
    <link href="../assets/css/manage_subjects.css" rel="stylesheet">
    
</head>
<body>
    <div class="admin-layout">
        
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="dashboard-content">
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'info' : 'warning'; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <div class="tabs">
                    <button class="tab-button active" onclick="openTab(event, 'add-subject')">
                        <i class="fas fa-plus"></i> Add Subject
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'manage-subjects')">
                        <i class="fas fa-list"></i> Manage Subjects
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'assign-faculty')">
                        <i class="fas fa-user-tie"></i> Assign Faculty
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'class-sections')">
                        <i class="fas fa-users"></i> Class Sections
                    </button>
                </div>

                <!-- Add Subject Tab -->
                <div id="add-subject" class="tab-content active">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-plus-circle"></i> Add New Subject
                            </h3>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_subject">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="course_code">Subject Code *</label>
                                    <input type="text" id="course_code" name="course_code" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="department_id">Department *</label>
                                    <select id="department_id" name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>">
                                            <?php echo htmlspecialchars($dept['name'] . ' (' . $dept['code'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="subject_name">Subject Name *</label>
                                    <input type="text" id="subject_name" name="subject_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="credits">Credits *</label>
                                    <input type="number" id="credits" name="credits" min="1" max="6" value="3" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" placeholder="Subject description (optional)"></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Subject
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Manage Subjects Tab -->
                <div id="manage-subjects" class="tab-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list"></i> All Subjects
                            </h3>
                        </div>
                        
                        <div class="search-bar">
                            <input type="text" id="subjectSearch" placeholder="Search subjects..." onkeyup="filterTable('subjectSearch', 'subjectsTable')">
                        </div>
                        
                        <div class="table-container">
                            <table class="table" id="subjectsTable">
                                <thead>
                                    <tr>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Department</th>
                                        <th>Credits</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subject['course_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['department_name']); ?></td>
                                        <td><?php echo $subject['credits']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $subject['status']; ?>">
                                                <?php echo ucfirst($subject['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-warning btn-sm" onclick="editSubject(<?php echo htmlspecialchars(json_encode($subject)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_subject">
                                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to DELETE this subject? This action cannot be undone.\n\nSubject: <?php echo htmlspecialchars($subject['course_code'] . ' - ' . $subject['subject_name']); ?>')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Assign Faculty Tab -->
                <div id="assign-faculty" class="tab-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user-tie"></i> Assign Faculty to Subject
                            </h3>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="assign_faculty">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="assign_subject_id">Subject *</label>
                                    <select id="assign_subject_id" name="subject_id" required>
                                        <option value="">Select Subject</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <?php if ($subject['status'] === 'active'): ?>
                                            <option value="<?php echo $subject['id']; ?>">
                                                <?php echo htmlspecialchars($subject['course_code'] . ' - ' . $subject['subject_name']); ?>
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="faculty_id">Faculty Member *</label>
                                    <select id="faculty_id" name="faculty_id" required>
                                        <option value="">Select Faculty</option>
                                        <?php foreach ($faculty_members as $faculty): ?>
                                        <option value="<?php echo $faculty['id']; ?>">
                                            <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name'] . ' (' . $faculty['employee_id'] . ')'); ?>
                                            <?php if ($faculty['department_name']): ?>
                                                - <?php echo htmlspecialchars($faculty['department_name']); ?>
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="academic_year_id">Academic Year *</label>
                                    <select id="academic_year_id" name="academic_year_id" required>
                                        <option value="">Select Academic Year</option>
                                        <?php foreach ($academic_years as $year): ?>
                                        <option value="<?php echo $year['id']; ?>">
                                            <?php echo htmlspecialchars($year['year_label']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="section_name">Section Name *</label>
                                    <input type="text" id="section_name" name="section_name" placeholder="e.g., A, B, 1A, 2B" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="schedule">Schedule</label>
                                    <input type="text" id="schedule" name="schedule" placeholder="e.g., MWF 8:00-9:00 AM">
                                </div>
                                
                                <div class="form-group">
                                    <label for="room">Room</label>
                                    <input type="text" id="room" name="room" placeholder="e.g., Room 101, Lab A">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="max_students">Max Students</label>
                                    <input type="number" id="max_students" name="max_students" min="1" value="40">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Assign Faculty
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Class Sections Tab -->
                <div id="class-sections" class="tab-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users"></i> Class Sections
                            </h3>
                        </div>
                        
                        <div class="search-bar">
                            <input type="text" id="sectionSearch" placeholder="Search class sections..." onkeyup="filterTable('sectionSearch', 'sectionsTable')">
                        </div>
                        
                        <div class="table-container">
                            <table class="table" id="sectionsTable">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Section</th>
                                        <th>Faculty</th>
                                        <th>Academic Year</th>
                                        <th>Schedule</th>
                                        <th>Room</th>
                                        <th>Max Students</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($class_sections as $section): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($section['course_code']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($section['subject_name']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($section['section_name']); ?></td>
                                        <td><?php echo htmlspecialchars($section['faculty_name']); ?></td>
                                        <td><?php echo htmlspecialchars($section['academic_year']); ?></td>
                                        <td><?php echo htmlspecialchars($section['schedule'] ?: 'Not set'); ?></td>
                                        <td><?php echo htmlspecialchars($section['room'] ?: 'Not set'); ?></td>
                                        <td><?php echo $section['max_students']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $section['status']; ?>">
                                                <?php echo ucfirst($section['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_section">
                                                <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to DELETE this class section? This action cannot be undone.\n\nSubject: <?php echo htmlspecialchars($section['course_code'] . ' - ' . $section['subject_name']); ?>\nSection: <?php echo htmlspecialchars($section['section_name']); ?>\nFaculty: <?php echo htmlspecialchars($section['faculty_name']); ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Edit Subject Modal -->
    <div id="editSubjectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Subject</h2>
                <span class="close" onclick="closeModal('editSubjectModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_subject">
                <input type="hidden" id="edit_subject_id" name="subject_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_course_code">Subject Code *</label>
                        <input type="text" id="edit_course_code" name="course_code" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_department_id">Department *</label>
                        <select id="edit_department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>">
                                <?php echo htmlspecialchars($dept['name'] . ' (' . $dept['code'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_subject_name">Subject Name *</label>
                        <input type="text" id="edit_subject_name" name="subject_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_credits">Credits *</label>
                        <input type="number" id="edit_credits" name="credits" min="1" max="6" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description"></textarea>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editSubjectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Subject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin_dashboard.js"></script>
    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tabbuttons;
            
            // Hide all tab content
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Remove active class from all tab buttons
            tabbuttons = document.getElementsByClassName("tab-button");
            for (i = 0; i < tabbuttons.length; i++) {
                tabbuttons[i].classList.remove("active");
            }
            
            // Show selected tab and mark button as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        function editSubject(subject) {
            document.getElementById('edit_subject_id').value = subject.id;
            document.getElementById('edit_course_code').value = subject.course_code;
            document.getElementById('edit_subject_name').value = subject.subject_name;
            document.getElementById('edit_credits').value = subject.credits;
            document.getElementById('edit_department_id').value = subject.department_id;
            document.getElementById('edit_description').value = subject.description || '';
            
            document.getElementById('editSubjectModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modals = document.getElementsByClassName('modal');
            for (var i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }

        function filterTable(inputId, tableId) {
            var input, filter, table, tr, td, i, j, txtValue;
            input = document.getElementById(inputId);
            filter = input.value.toUpperCase();
            table = document.getElementById(tableId);
            tr = table.getElementsByTagName("tr");

            // Loop through all table rows (skip header)
            for (i = 1; i < tr.length; i++) {
                tr[i].style.display = "none";
                td = tr[i].getElementsByTagName("td");
                
                // Loop through all columns in the row
                for (j = 0; j < td.length; j++) {
                    if (td[j]) {
                        txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            tr[i].style.display = "";
                            break;
                        }
                    }
                }
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Subject code validation
            const subjectCodeInputs = document.querySelectorAll('input[name="course_code"]');
            subjectCodeInputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^A-Za-z0-9\s]/g, '').toUpperCase();
                });
            });

            // Credits validation
            const creditsInputs = document.querySelectorAll('input[name="credits"]');
            creditsInputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value < 1) this.value = 1;
                    if (this.value > 6) this.value = 6;
                });
            });

            // Max students validation
            const maxStudentsInput = document.getElementById('max_students');
            if (maxStudentsInput) {
                maxStudentsInput.addEventListener('input', function() {
                    if (this.value < 1) this.value = 1;
                    if (this.value > 100) this.value = 100;
                });
            }

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (modal.style.display === 'block') {
                        modal.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>