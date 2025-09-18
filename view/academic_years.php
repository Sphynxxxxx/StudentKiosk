<?php
// Include database configuration
require_once '../config/database.php';



$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_academic_year':
                try {
                    // Check if there's already an active academic year and deactivate it if requested
                    if (isset($_POST['is_active']) && $_POST['is_active'] == '1') {
                        $stmt = $pdo->prepare("UPDATE academic_years SET is_active = 0");
                        $stmt->execute();
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO academic_years (year_start, year_end, semester, is_active) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    $stmt->execute([
                        $_POST['year_start'],
                        $_POST['year_end'],
                        $_POST['semester'],
                        $is_active
                    ]);
                    $message = 'Academic year added successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error adding academic year: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;

            case 'edit_academic_year':
                try {
                    // Check if setting this year as active and deactivate others if needed
                    if (isset($_POST['is_active']) && $_POST['is_active'] == '1') {
                        $stmt = $pdo->prepare("UPDATE academic_years SET is_active = 0 WHERE id != ?");
                        $stmt->execute([$_POST['academic_year_id']]);
                    }

                    $stmt = $pdo->prepare("
                        UPDATE academic_years 
                        SET year_start = ?, year_end = ?, semester = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    $stmt->execute([
                        $_POST['year_start'],
                        $_POST['year_end'],
                        $_POST['semester'],
                        $is_active,
                        $_POST['academic_year_id']
                    ]);
                    $message = 'Academic year updated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating academic year: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;

            case 'set_active':
                try {
                    // Deactivate all academic years first
                    $stmt = $pdo->prepare("UPDATE academic_years SET is_active = 0");
                    $stmt->execute();

                    // Activate the selected academic year
                    $stmt = $pdo->prepare("UPDATE academic_years SET is_active = 1 WHERE id = ?");
                    $stmt->execute([$_POST['academic_year_id']]);
                    
                    $message = 'Academic year activated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error activating academic year: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;

            case 'delete_academic_year':
                try {
                    // Check if this academic year has any class sections or enrollments
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM class_sections WHERE academic_year_id = ?
                    ");
                    $stmt->execute([$_POST['academic_year_id']]);
                    $class_sections_count = $stmt->fetchColumn();

                    if ($class_sections_count > 0) {
                        $message = 'Cannot delete academic year: It has associated class sections.';
                        $message_type = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM academic_years WHERE id = ?");
                        $stmt->execute([$_POST['academic_year_id']]);
                        $message = 'Academic year deleted successfully!';
                        $message_type = 'success';
                    }
                } catch (PDOException $e) {
                    $message = 'Error deleting academic year: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get all academic years with statistics
$stmt = $pdo->prepare("
    SELECT ay.*, 
           COUNT(cs.id) as class_sections_count,
           COUNT(DISTINCT e.student_id) as enrolled_students_count
    FROM academic_years ay
    LEFT JOIN class_sections cs ON ay.id = cs.academic_year_id
    LEFT JOIN enrollments e ON cs.id = e.class_section_id
    GROUP BY ay.id
    ORDER BY ay.year_start DESC, ay.semester
");
$stmt->execute();
$academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current year for default values
$current_year = date('Y');
$next_year = $current_year + 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Academic Years - ISATU Kiosk System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin_dashboard.css" rel="stylesheet">
    <style>
        .tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .tab-button {
            flex: 1;
            padding: 20px;
            background: none;
            border: none;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #666;
        }

        .tab-button.active {
            background-color: #1e3a8a;
            color: white;
        }

        .tab-button:hover:not(.active) {
            background-color: #f8f9fa;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1e3a8a;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: #1e3a8a;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.875rem;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background-color: #1e3a8a;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }

        .table tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #333;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #000;
        }

        .search-bar {
            margin-bottom: 20px;
        }

        .search-bar input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .year-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .year-label {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .active-indicator {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .stats-mini {
            display: flex;
            gap: 15px;
            margin-top: 5px;
        }

        .stat-mini {
            font-size: 0.875rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .action-buttons {
                justify-content: center;
            }
        }
    </style>
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
                    <button class="tab-button active" onclick="openTab(event, 'add-year')">
                        <i class="fas fa-plus"></i> Add Academic Year
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'manage-years')">
                        <i class="fas fa-calendar-alt"></i> Manage Years
                    </button>
                </div>

                <!-- Add Academic Year Tab -->
                <div id="add-year" class="tab-content active">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-plus-circle"></i> Add New Academic Year
                            </h3>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_academic_year">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="year_start">Start Year *</label>
                                    <input type="number" id="year_start" name="year_start" 
                                           min="2020" max="2050" value="<?php echo $current_year; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="year_end">End Year *</label>
                                    <input type="number" id="year_end" name="year_end" 
                                           min="2020" max="2050" value="<?php echo $next_year; ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="semester">Semester *</label>
                                    <select id="semester" name="semester" required>
                                        <option value="">Select Semester</option>
                                        <option value="1st">1st Semester</option>
                                        <option value="2nd">2nd Semester</option>
                                        <option value="summer">Summer</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_active" name="is_active" value="1">
                                        <label for="is_active">Set as Active Academic Year</label>
                                    </div>
                                    <small style="color: #666; margin-top: 5px;">
                                        Note: Setting this as active will deactivate the current active academic year.
                                    </small>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Academic Year
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Manage Academic Years Tab -->
                <div id="manage-years" class="tab-content">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calendar-alt"></i> All Academic Years
                            </h3>
                        </div>
                        
                        <div class="search-bar">
                            <input type="text" id="yearSearch" placeholder="Search academic years..." onkeyup="filterTable('yearSearch', 'yearsTable')">
                        </div>
                        
                        <div class="table-container">
                            <table class="table" id="yearsTable">
                                <thead>
                                    <tr>
                                        <th>Academic Year</th>
                                        <th>Status</th>
                                        <th>Class Sections</th>
                                        <th>Enrolled Students</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($academic_years as $year): ?>
                                    <tr>
                                        <td>
                                            <div class="year-info">
                                                <span class="year-label">
                                                    <?php echo htmlspecialchars($year['year_start'] . '-' . $year['year_end']); ?>
                                                </span>
                                                <?php if ($year['is_active']): ?>
                                                    <span class="active-indicator">ACTIVE</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="color: #666; font-size: 0.9rem; margin-top: 2px;">
                                                <?php echo ucfirst($year['semester']); ?> Semester
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $year['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $year['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($year['class_sections_count']); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($year['enrolled_students_count']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-warning btn-sm" onclick="editYear(<?php echo htmlspecialchars(json_encode($year)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                
                                                <?php if (!$year['is_active']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="set_active">
                                                    <input type="hidden" name="academic_year_id" value="<?php echo $year['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                            onclick="return confirm('Set this as the active academic year?')">
                                                        <i class="fas fa-check"></i> Activate
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($year['class_sections_count'] == 0): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_academic_year">
                                                    <input type="hidden" name="academic_year_id" value="<?php echo $year['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to delete this academic year? This action cannot be undone.')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                                <?php endif; ?>
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

    <!-- Edit Academic Year Modal -->
    <div id="editYearModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Academic Year</h2>
                <span class="close" onclick="closeModal('editYearModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_academic_year">
                <input type="hidden" id="edit_year_id" name="academic_year_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_year_start">Start Year *</label>
                        <input type="number" id="edit_year_start" name="year_start" 
                               min="2020" max="2050" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_year_end">End Year *</label>
                        <input type="number" id="edit_year_end" name="year_end" 
                               min="2020" max="2050" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_semester">Semester *</label>
                        <select id="edit_semester" name="semester" required>
                            <option value="">Select Semester</option>
                            <option value="1st">1st Semester</option>
                            <option value="2nd">2nd Semester</option>
                            <option value="summer">Summer</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                            <label for="edit_is_active">Set as Active Academic Year</label>
                        </div>
                        <small style="color: #666; margin-top: 5px;">
                            Note: Setting this as active will deactivate the current active academic year.
                        </small>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editYearModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Academic Year
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

        function editYear(year) {
            document.getElementById('edit_year_id').value = year.id;
            document.getElementById('edit_year_start').value = year.year_start;
            document.getElementById('edit_year_end').value = year.year_end;
            document.getElementById('edit_semester').value = year.semester;
            document.getElementById('edit_is_active').checked = year.is_active == 1;
            
            document.getElementById('editYearModal').style.display = 'block';
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

        // Form validation and auto-update
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-update end year when start year changes
            const yearStartInput = document.getElementById('year_start');
            const yearEndInput = document.getElementById('year_end');
            
            if (yearStartInput && yearEndInput) {
                yearStartInput.addEventListener('input', function() {
                    const startYear = parseInt(this.value);
                    if (startYear) {
                        yearEndInput.value = startYear + 1;
                    }
                });
            }

            // Same for edit form
            const editYearStartInput = document.getElementById('edit_year_start');
            const editYearEndInput = document.getElementById('edit_year_end');
            
            if (editYearStartInput && editYearEndInput) {
                editYearStartInput.addEventListener('input', function() {
                    const startYear = parseInt(this.value);
                    if (startYear) {
                        editYearEndInput.value = startYear + 1;
                    }
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

            // Validate year range
            const yearInputs = document.querySelectorAll('input[type="number"][min="2020"]');
            yearInputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value < 2020) this.value = 2020;
                    if (this.value > 2050) this.value = 2050;
                });
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