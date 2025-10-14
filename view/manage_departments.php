<?php
// Include database configuration
require_once '../config/database.php';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_department':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO departments (name, code, description, head_faculty_id, status) 
                        VALUES (?, ?, ?, ?, 'active')
                    ");
                    $head_faculty_id = !empty($_POST['head_faculty_id']) ? $_POST['head_faculty_id'] : NULL;
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['code'],
                        $_POST['description'],
                        $head_faculty_id
                    ]);
                    $message = 'Department added successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $message = 'Department code already exists!';
                        $message_type = 'error';
                    } else {
                        $message = 'Error adding department: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
                break;
                
            case 'edit_department':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE departments 
                        SET name = ?, code = ?, description = ?, head_faculty_id = ?
                        WHERE id = ?
                    ");
                    $head_faculty_id = !empty($_POST['head_faculty_id']) ? $_POST['head_faculty_id'] : NULL;
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['code'],
                        $_POST['description'],
                        $head_faculty_id,
                        $_POST['department_id']
                    ]);
                    $message = 'Department updated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $message = 'Department code already exists!';
                        $message_type = 'error';
                    } else {
                        $message = 'Error updating department: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
                break;
                
            case 'delete_department':
                try {
                    // Check if department has programs
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE department_id = ?");
                    $stmt->execute([$_POST['department_id']]);
                    $program_count = $stmt->fetchColumn();
                    
                    if ($program_count > 0) {
                        $message = 'Cannot delete department: It has ' . $program_count . ' program(s). Please move or delete programs first.';
                        $message_type = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                        $stmt->execute([$_POST['department_id']]);
                        $message = 'Department deleted successfully!';
                        $message_type = 'success';
                    }
                } catch (PDOException $e) {
                    $message = 'Error deleting department: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'add_program':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO programs (program_code, program_name, department_id, degree_type, duration_years, description, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([
                        $_POST['program_code'],
                        $_POST['program_name'],
                        $_POST['department_id'],
                        $_POST['degree_type'],
                        $_POST['duration_years'],
                        $_POST['description']
                    ]);
                    $message = 'Program added successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $message = 'Program code already exists!';
                        $message_type = 'error';
                    } else {
                        $message = 'Error adding program: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
                break;
                
            case 'edit_program':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE programs 
                        SET program_code = ?, program_name = ?, department_id = ?, degree_type = ?, duration_years = ?, description = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['program_code'],
                        $_POST['program_name'],
                        $_POST['department_id'],
                        $_POST['degree_type'],
                        $_POST['duration_years'],
                        $_POST['description'],
                        $_POST['program_id']
                    ]);
                    $message = 'Program updated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $message = 'Program code already exists!';
                        $message_type = 'error';
                    } else {
                        $message = 'Error updating program: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
                break;
                
            case 'delete_program':
                try {
                    // Check if program has sections
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE program_id = ?");
                    $stmt->execute([$_POST['program_id']]);
                    $section_count = $stmt->fetchColumn();
                    
                    if ($section_count > 0) {
                        $message = 'Cannot delete program: It has ' . $section_count . ' section(s). Please delete sections first.';
                        $message_type = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM programs WHERE id = ?");
                        $stmt->execute([$_POST['program_id']]);
                        $message = 'Program deleted successfully!';
                        $message_type = 'success';
                    }
                } catch (PDOException $e) {
                    $message = 'Error deleting program: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'add_section':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO sections (section_name, year_level, program_id, academic_year_id, adviser_id, max_students, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $adviser_id = !empty($_POST['adviser_id']) ? $_POST['adviser_id'] : NULL;
                    $stmt->execute([
                        $_POST['section_name'],
                        $_POST['year_level'],
                        $_POST['program_id'],
                        $_POST['academic_year_id'],
                        $adviser_id,
                        $_POST['max_students']
                    ]);
                    $message = 'Section added successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error adding section: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'edit_section':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE sections 
                        SET section_name = ?, year_level = ?, program_id = ?, academic_year_id = ?, adviser_id = ?, max_students = ?
                        WHERE id = ?
                    ");
                    $adviser_id = !empty($_POST['adviser_id']) ? $_POST['adviser_id'] : NULL;
                    $stmt->execute([
                        $_POST['section_name'],
                        $_POST['year_level'],
                        $_POST['program_id'],
                        $_POST['academic_year_id'],
                        $adviser_id,
                        $_POST['max_students'],
                        $_POST['section_id']
                    ]);
                    $message = 'Section updated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating section: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'delete_section':
                try {
                    // Check if section has students
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_profiles WHERE section_id = ?");
                    $stmt->execute([$_POST['section_id']]);
                    $student_count = $stmt->fetchColumn();
                    
                    if ($student_count > 0) {
                        $message = 'Cannot delete section: It has ' . $student_count . ' student(s). Please move students first.';
                        $message_type = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
                        $stmt->execute([$_POST['section_id']]);
                        $message = 'Section deleted successfully!';
                        $message_type = 'success';
                    }
                } catch (PDOException $e) {
                    $message = 'Error deleting section: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get all departments with head faculty information
$stmt = $pdo->prepare("
    SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as head_name
    FROM departments d
    LEFT JOIN users u ON d.head_faculty_id = u.id
    ORDER BY d.name
");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all programs with department information
$stmt = $pdo->prepare("
    SELECT p.*, d.name as department_name, d.code as department_code
    FROM programs p
    JOIN departments d ON p.department_id = d.id
    ORDER BY d.name, p.program_code
");
$stmt->execute();
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all sections with program and adviser information
$stmt = $pdo->prepare("
    SELECT s.*, p.program_code, p.program_name, p.duration_years, d.name as department_name,
           CONCAT(u.first_name, ' ', u.last_name) as adviser_name,
           CONCAT(ay.year_start, '-', ay.year_end, ' (', ay.semester, ' Semester)') as academic_year,
           COUNT(sp.id) as student_count
    FROM sections s
    JOIN programs p ON s.program_id = p.id
    JOIN departments d ON p.department_id = d.id
    JOIN academic_years ay ON s.academic_year_id = ay.id
    LEFT JOIN users u ON s.adviser_id = u.id
    LEFT JOIN student_profiles sp ON s.id = sp.section_id
    GROUP BY s.id
    ORDER BY d.name, p.program_code, s.year_level, s.section_name
");
$stmt->execute();
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Colleges & Courses - ISATU Kiosk System</title>
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
                    <button class="tab-button active" onclick="openTab(event, 'add-department')">
                        <i class="fas fa-plus"></i> Add Colleges
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'manage-departments')">
                        <i class="fas fa-building"></i> Manage Colleges
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'add-program')">
                        <i class="fas fa-graduation-cap"></i> Add Course
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'manage-programs')">
                        <i class="fas fa-list"></i> Manage Courses
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'add-section')">
                        <i class="fas fa-users"></i> Add Section
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'manage-sections')">
                        <i class="fas fa-th-list"></i> Manage Sections
                    </button>
                </div>

                <!-- Add Department Tab -->
                <div id="add-department" class="tab-content active">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-plus-circle"></i> Add New College
                            </h3>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_department">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">College Name *</label>
                                    <input type="text" id="name" name="name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="code">College Code *</label>
                                    <input type="text" id="code" name="code" maxlength="10" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="head_faculty_id">College Dean</label>
                                    <select id="head_faculty_id" name="head_faculty_id">
                                        <option value="">Select College Dean (Optional)</option>
                                        <?php foreach ($faculty_members as $faculty): ?>
                                        <option value="<?php echo $faculty['id']; ?>">
                                            <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name'] . ' (' . $faculty['employee_id'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" placeholder="Department description (optional)"></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add College
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Manage Departments Tab -->
                <div id="manage-departments" class="tab-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-building"></i> All Colleges
                            </h3>
                        </div>
                        
                        <div class="search-bar">
                            <input type="text" id="departmentSearch" placeholder="Search College..." onkeyup="filterTable('departmentSearch', 'departmentsTable')">
                        </div>
                        
                        <div class="table-container">
                            <table class="table" id="departmentsTable">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>College Name</th>
                                        <th>College Head</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departments as $department): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($department['code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($department['name']); ?></td>
                                        <td><?php echo $department['head_name'] ? htmlspecialchars($department['head_name']) : 'Not assigned'; ?></td>
                                        <td><?php echo htmlspecialchars(substr($department['description'] ?: 'No description', 0, 50)); ?><?php echo strlen($department['description'] ?: '') > 50 ? '...' : ''; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $department['status']; ?>">
                                                <?php echo ucfirst($department['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-warning btn-sm" onclick="editDepartment(<?php echo htmlspecialchars(json_encode($department)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_department">
                                                    <input type="hidden" name="department_id" value="<?php echo $department['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to DELETE this college? This action cannot be undone.\n\nCollege: <?php echo htmlspecialchars($department['name']); ?>')">
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

                <!-- Add Program Tab -->
                <div id="add-program" class="tab-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-graduation-cap"></i> Add New Course
                            </h3>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_program">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="program_code">Course Code *</label>
                                    <input type="text" id="program_code" name="program_code" maxlength="20" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="program_department_id">College *</label>
                                    <select id="program_department_id" name="department_id" required>
                                        <option value="">Select College</option>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>">
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="program_name">Course Name *</label>
                                    <input type="text" id="program_name" name="program_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="degree_type">Degree Type *</label>
                                    <select id="degree_type" name="degree_type" required>
                                        <option value="bachelor">Bachelor</option>
                                        <option value="master">Master</option>
                                        <option value="doctorate">Doctorate</option>
                                        <option value="certificate">Certificate</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="duration_years">Duration (Years) *</label>
                                    <input type="number" id="duration_years" name="duration_years" min="1" max="8" value="4" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="program_description">Description</label>
                                    <textarea id="program_description" name="description" placeholder="Program description (optional)"></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Course
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Manage Programs Tab -->
                <div id="manage-programs" class="tab-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list"></i> All Courses
                            </h3>
                        </div>
                        
                        <div class="search-bar">
                            <input type="text" id="programSearch" placeholder="Search Courses..." onkeyup="filterTable('programSearch', 'programsTable')">
                        </div>
                        
                        <div class="table-container">
                            <table class="table" id="programsTable">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>College</th>
                                        <th>Degree Type</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($programs as $program): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($program['program_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($program['program_name']); ?></td>
                                        <td><?php echo htmlspecialchars($program['department_name']); ?></td>
                                        <td><?php echo ucfirst($program['degree_type']); ?></td>
                                        <td><?php echo $program['duration_years']; ?> year<?php echo $program['duration_years'] != 1 ? 's' : ''; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $program['status']; ?>">
                                                <?php echo ucfirst($program['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-warning btn-sm" onclick="editProgram(<?php echo htmlspecialchars(json_encode($program)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_program">
                                                    <input type="hidden" name="program_id" value="<?php echo $program['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to DELETE this Course? This action cannot be undone.\n\nCourse: <?php echo htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']); ?>')">
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

                <!-- Add Section Tab -->
                <div id="add-section" class="tab-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users"></i> Add New Section
                            </h3>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_section">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="section_program_id">Course *</label>
                                    <select id="section_program_id" name="program_id" required onchange="loadYearLevels()">
                                        <option value="">Select Program</option>
                                        <?php foreach ($programs as $program): ?>
                                            <?php if ($program['status'] === 'active'): ?>
                                            <option value="<?php echo $program['id']; ?>" data-duration="<?php echo $program['duration_years']; ?>">
                                                <?php echo htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']); ?>
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="year_level">Year Level *</label>
                                    <select id="year_level" name="year_level" required>
                                        <option value="">Select Course First</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="section_name">Section Name *</label>
                                    <input type="text" id="section_name" name="section_name" placeholder="e.g., A, B, 1A, 2B" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="section_academic_year_id">Academic Year *</label>
                                    <select id="section_academic_year_id" name="academic_year_id" required>
                                        <option value="">Select Academic Year</option>
                                        <?php foreach ($academic_years as $year): ?>
                                        <option value="<?php echo $year['id']; ?>">
                                            <?php echo htmlspecialchars($year['year_label']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="section_adviser_id">Section Adviser</label>
                                    <select id="section_adviser_id" name="adviser_id">
                                        <option value="">Select Section Adviser (Optional)</option>
                                        <?php foreach ($faculty_members as $faculty): ?>
                                        <option value="<?php echo $faculty['id']; ?>">
                                            <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name'] . ' (' . $faculty['employee_id'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="section_max_students">Max Students *</label>
                                    <input type="number" id="section_max_students" name="max_students" min="1" max="100" value="40" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Section
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Manage Sections Tab -->
                <div id="manage-sections" class="tab-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-th-list"></i> All Sections
                            </h3>
                        </div>
                        
                        <div class="search-bar">
                            <input type="text" id="sectionSearch" placeholder="Search sections..." onkeyup="filterTable('sectionSearch', 'sectionsTable')">
                        </div>
                        
                        <div class="table-container">
                            <table class="table" id="sectionsTable">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Year Level</th>
                                        <th>Section</th>
                                        <th>Academic Year</th>
                                        <th>Adviser</th>
                                        <th>Students</th>
                                        <th>Max Students</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sections as $section): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($section['program_code']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($section['department_name']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($section['year_level']); ?></td>
                                        <td><?php echo htmlspecialchars($section['section_name']); ?></td>
                                        <td><?php echo htmlspecialchars($section['academic_year']); ?></td>
                                        <td><?php echo $section['adviser_name'] ? htmlspecialchars($section['adviser_name']) : 'Not assigned'; ?></td>
                                        <td><?php echo $section['student_count']; ?></td>
                                        <td><?php echo $section['max_students']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $section['status']; ?>">
                                                <?php echo ucfirst($section['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-warning btn-sm" onclick="editSection(<?php echo htmlspecialchars(json_encode($section)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_section">
                                                    <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to DELETE this section? This action cannot be undone.\n\nSection: <?php echo htmlspecialchars($section['program_code'] . ' ' . $section['year_level'] . '-' . $section['section_name']); ?>')">
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

            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div id="editDepartmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit College</h2>
                <span class="close" onclick="closeModal('editDepartmentModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_department">
                <input type="hidden" id="edit_department_id" name="department_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_dept_name">College Name *</label>
                        <input type="text" id="edit_dept_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_dept_code">College Code *</label>
                        <input type="text" id="edit_dept_code" name="code" maxlength="10" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_dept_head_faculty_id">College Dean</label>
                        <select id="edit_dept_head_faculty_id" name="head_faculty_id">
                            <option value="">Select College Dean (Optional)</option>
                            <?php foreach ($faculty_members as $faculty): ?>
                            <option value="<?php echo $faculty['id']; ?>">
                                <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name'] . ' (' . $faculty['employee_id'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_dept_description">Description</label>
                        <textarea id="edit_dept_description" name="description"></textarea>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editDepartmentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update College
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Program Modal -->
    <div id="editProgramModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Course</h2>
                <span class="close" onclick="closeModal('editProgramModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_program">
                <input type="hidden" id="edit_program_id" name="program_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_program_code">Course Code *</label>
                        <input type="text" id="edit_program_code" name="program_code" maxlength="20" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_program_department_id">College *</label>
                        <select id="edit_program_department_id" name="department_id" required>
                            <option value="">Select College</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>">
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_program_name">Course Name *</label>
                        <input type="text" id="edit_program_name" name="program_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_degree_type">Degree Type *</label>
                        <select id="edit_degree_type" name="degree_type" required>
                            <option value="bachelor">Bachelor</option>
                            <option value="master">Master</option>
                            <option value="doctorate">Doctorate</option>
                            <option value="certificate">Certificate</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_duration_years">Duration (Years) *</label>
                        <input type="number" id="edit_duration_years" name="duration_years" min="1" max="8" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_program_description">Description</label>
                        <textarea id="edit_program_description" name="description"></textarea>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editProgramModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Course
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Section Modal -->
    <div id="editSectionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Section</h2>
                <span class="close" onclick="closeModal('editSectionModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_section">
                <input type="hidden" id="edit_section_id" name="section_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_section_program_id">Course *</label>
                        <select id="edit_section_program_id" name="program_id" required onchange="loadEditYearLevels()">
                            <option value="">Select Course</option>
                            <?php foreach ($programs as $program): ?>
                                <?php if ($program['status'] === 'active'): ?>
                                <option value="<?php echo $program['id']; ?>" data-duration="<?php echo $program['duration_years']; ?>">
                                    <?php echo htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']); ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_year_level">Year Level *</label>
                        <select id="edit_year_level" name="year_level" required>
                            <option value="">Select Course First</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_section_name">Section Name *</label>
                        <input type="text" id="edit_section_name" name="section_name" placeholder="e.g., A, B, 1A, 2B" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_section_academic_year_id">Academic Year *</label>
                        <select id="edit_section_academic_year_id" name="academic_year_id" required>
                            <option value="">Select Academic Year</option>
                            <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo $year['id']; ?>">
                                <?php echo htmlspecialchars($year['year_label']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_section_adviser_id">Section Adviser</label>
                        <select id="edit_section_adviser_id" name="adviser_id">
                            <option value="">Select Section Adviser (Optional)</option>
                            <?php foreach ($faculty_members as $faculty): ?>
                            <option value="<?php echo $faculty['id']; ?>">
                                <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name'] . ' (' . $faculty['employee_id'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_section_max_students">Max Students *</label>
                        <input type="number" id="edit_section_max_students" name="max_students" min="1" max="100" required>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editSectionModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Section
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

        // Dynamic Year Level Loading based on Program Duration
        function loadYearLevels() {
            const programSelect = document.getElementById('section_program_id');
            const yearSelect = document.getElementById('year_level');
            
            // Clear existing options
            yearSelect.innerHTML = '<option value="">Select Year Level</option>';
            
            if (programSelect.value) {
                const selectedOption = programSelect.options[programSelect.selectedIndex];
                const duration = parseInt(selectedOption.dataset.duration) || 4;
                
                const yearLevels = ['1st', '2nd', '3rd', '4th', '5th'];
                
                for (let i = 0; i < duration && i < yearLevels.length; i++) {
                    const option = document.createElement('option');
                    option.value = yearLevels[i];
                    option.textContent = yearLevels[i] + ' Year';
                    yearSelect.appendChild(option);
                }
            }
        }

        // Edit modal functions
        function loadEditYearLevels() {
            const programSelect = document.getElementById('edit_section_program_id');
            const yearSelect = document.getElementById('edit_year_level');
            
            // Clear existing options
            yearSelect.innerHTML = '<option value="">Select Year Level</option>';
            
            if (programSelect.value) {
                const selectedOption = programSelect.options[programSelect.selectedIndex];
                const duration = parseInt(selectedOption.dataset.duration) || 4;
                
                const yearLevels = ['1st', '2nd', '3rd', '4th', '5th'];
                
                for (let i = 0; i < duration && i < yearLevels.length; i++) {
                    const option = document.createElement('option');
                    option.value = yearLevels[i];
                    option.textContent = yearLevels[i] + ' Year';
                    yearSelect.appendChild(option);
                }
            }
        }

        function editDepartment(department) {
            document.getElementById('edit_department_id').value = department.id;
            document.getElementById('edit_dept_name').value = department.name;
            document.getElementById('edit_dept_code').value = department.code;
            document.getElementById('edit_dept_head_faculty_id').value = department.head_faculty_id || '';
            document.getElementById('edit_dept_description').value = department.description || '';
            
            document.getElementById('editDepartmentModal').style.display = 'block';
        }

        function editProgram(program) {
            document.getElementById('edit_program_id').value = program.id;
            document.getElementById('edit_program_code').value = program.program_code;
            document.getElementById('edit_program_name').value = program.program_name;
            document.getElementById('edit_program_department_id').value = program.department_id;
            document.getElementById('edit_degree_type').value = program.degree_type;
            document.getElementById('edit_duration_years').value = program.duration_years;
            document.getElementById('edit_program_description').value = program.description || '';
            
            document.getElementById('editProgramModal').style.display = 'block';
        }

        function editSection(section) {
            document.getElementById('edit_section_id').value = section.id;
            document.getElementById('edit_section_program_id').value = section.program_id;
            document.getElementById('edit_section_academic_year_id').value = section.academic_year_id;
            document.getElementById('edit_section_adviser_id').value = section.adviser_id || '';
            document.getElementById('edit_section_max_students').value = section.max_students;
            
            // Load year levels first
            loadEditYearLevels();
            
            // Set year level and section name after a short delay to ensure options are loaded
            setTimeout(() => {
                document.getElementById('edit_year_level').value = section.year_level;
                document.getElementById('edit_section_name').value = section.section_name;
            }, 100);
            
            document.getElementById('editSectionModal').style.display = 'block';
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
            // Department code validation
            const deptCodeInputs = document.querySelectorAll('input[name="code"]');
            deptCodeInputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                });
            });

            // Program code validation
            const programCodeInputs = document.querySelectorAll('input[name="program_code"]');
            programCodeInputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                });
            });

            // Max students validation
            const maxStudentsInputs = document.querySelectorAll('input[name="max_students"]');
            maxStudentsInputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value < 1) this.value = 1;
                    if (this.value > 100) this.value = 100;
                });
            });

            // Duration years validation
            const durationInputs = document.querySelectorAll('input[name="duration_years"]');
            durationInputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value < 1) this.value = 1;
                    if (this.value > 8) this.value = 8;
                });
            });

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