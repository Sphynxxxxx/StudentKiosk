<?php
session_start();

// Include database configuration
require_once '../config/database.php';

$success_message = '';
$error_message = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_status') {
            $appeal_id = (int)$_POST['appeal_id'];
            $status = $_POST['status'];
            $admin_remarks = $_POST['admin_remarks'] ?? '';
            
            // Update without tracking reviewer
            $stmt = $pdo->prepare("
                UPDATE grade_appeals 
                SET status = ?, 
                    admin_remarks = ?, 
                    reviewed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $admin_remarks, $appeal_id]);
            
            $success_message = "Appeal status updated successfully!";
        }
    } catch (PDOException $e) {
        error_log("Appeal update error: " . $e->getMessage());
        $error_message = "Error updating appeal: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

try {
    // Build query with filters - Updated to match your database schema
    $query = "
        SELECT 
            ga.id,
            ga.grade_id,
            ga.student_id,
            ga.reason,
            ga.status,
            ga.admin_remarks,
            ga.submitted_at,
            ga.reviewed_at,
            s.first_name as student_first_name,
            s.last_name as student_last_name,
            s.student_id as student_number,
            subj.course_code,
            subj.subject_name,
            g.midterm_grade,
            g.final_grade,
            g.overall_grade,
            g.letter_grade,
            g.remarks as grade_remarks,
            f.first_name as faculty_first_name,
            f.last_name as faculty_last_name,
            ay.year_start,
            ay.year_end,
            ay.semester,
            reviewer.first_name as reviewer_first_name,
            reviewer.last_name as reviewer_last_name,
            e.id as enrollment_id
        FROM grade_appeals ga
        INNER JOIN grades g ON ga.grade_id = g.id
        INNER JOIN enrollments e ON g.enrollment_id = e.id
        INNER JOIN users s ON ga.student_id = s.id
        INNER JOIN class_sections cs ON e.class_section_id = cs.id
        INNER JOIN subjects subj ON cs.subject_id = subj.id
        INNER JOIN academic_years ay ON cs.academic_year_id = ay.id
        INNER JOIN users f ON cs.faculty_id = f.id
        LEFT JOIN users reviewer ON ga.reviewed_by = reviewer.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status_filter) {
        $query .= " AND ga.status = ?";
        $params[] = $status_filter;
    }
    
    if ($search) {
        $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR subj.course_code LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($date_from) {
        $query .= " AND DATE(ga.submitted_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $query .= " AND DATE(ga.submitted_at) <= ?";
        $params[] = $date_to;
    }
    
    $query .= " ORDER BY ga.submitted_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM grade_appeals
    ");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Appeal reports error: " . $e->getMessage());
    $appeals = [];
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Appeal Reports - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin_dashboard.css" rel="stylesheet">
    <style>
        .appeals-content {
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2em;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #6c757d;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }

        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.approved { border-left-color: #28a745; }
        .stat-card.rejected { border-left-color: #dc3545; }

        .stat-label {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 2em;
            font-weight: 600;
            color: #2c3e50;
        }

        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }

        .filter-input, .filter-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95em;
        }

        .filter-btn {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
        }

        .filter-btn:hover {
            background: #2980b9;
        }

        .appeals-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .appeals-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .appeals-table th {
            background: #2c3e50;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 500;
        }

        .appeals-table td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
        }

        .appeals-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
            margin-right: 5px;
        }

        .btn-view {
            background: #3498db;
            color: white;
        }

        .btn-view:hover {
            background: #2980b9;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            max-width: 800px;
            margin: 50px auto;
            border-radius: 10px;
            padding: 30px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }

        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #6c757d;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .detail-label {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .detail-value {
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95em;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .btn-submit {
            padding: 10px 30px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            font-size: 1em;
        }

        .btn-submit:hover {
            background: #218838;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="appeals-content">
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <div class="page-header">
                    <h1><i class="fas fa-exclamation-triangle"></i> Grade Appeal Reports</h1>
                    <p>Review and manage student grade appeals</p>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Appeals</div>
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                    </div>
                    <div class="stat-card pending">
                        <div class="stat-label">Pending Review</div>
                        <div class="stat-value"><?php echo $stats['pending']; ?></div>
                    </div>
                    <div class="stat-card approved">
                        <div class="stat-label">Approved</div>
                        <div class="stat-value"><?php echo $stats['approved']; ?></div>
                    </div>
                    <div class="stat-card rejected">
                        <div class="stat-label">Rejected</div>
                        <div class="stat-value"><?php echo $stats['rejected']; ?></div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" action="">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label>Search</label>
                                <input type="text" name="search" class="filter-input" 
                                       placeholder="Student name, ID, or subject..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status" class="filter-select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" class="filter-input" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" class="filter-input" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="filter-group">
                                <button type="submit" class="filter-btn">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Appeals Table -->
                <div class="appeals-table">
                    <?php if (empty($appeals)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Appeals Found</h3>
                        <p>No grade appeals match your current filters.</p>
                    </div>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Appeal ID</th>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Current Grade</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appeals as $appeal): ?>
                            <tr>
                                <td>#<?php echo $appeal['id']; ?></td>
                                <td>
                                    <div><strong><?php echo htmlspecialchars($appeal['student_first_name'] . ' ' . $appeal['student_last_name']); ?></strong></div>
                                    <div style="font-size: 0.85em; color: #6c757d;">
                                        <?php echo htmlspecialchars($appeal['student_number']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><strong><?php echo htmlspecialchars($appeal['course_code']); ?></strong></div>
                                    <div style="font-size: 0.85em; color: #6c757d;">
                                        <?php echo htmlspecialchars($appeal['subject_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($appeal['overall_grade']): ?>
                                        <strong><?php echo number_format($appeal['overall_grade'], 2); ?></strong>
                                        (<?php echo htmlspecialchars($appeal['letter_grade']); ?>)
                                    <?php else: ?>
                                        <span style="color: #6c757d;">No grade</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $appeal['status']; ?>">
                                        <?php echo ucfirst($appeal['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($appeal['submitted_at'])); ?></td>
                                <td>
                                    <button class="action-btn btn-view" onclick="viewAppeal(<?php echo $appeal['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Appeal Detail Modal -->
    <div id="appealModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Appeal Details</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script src="../assets/js/admin_dashboard.js"></script>
    <script>
        const appeals = <?php echo json_encode($appeals); ?>;
        
        function viewAppeal(appealId) {
            const appeal = appeals.find(a => a.id == appealId);
            if (!appeal) return;
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Appeal ID</div>
                        <div class="detail-value">#${appeal.id}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <span class="status-badge status-${appeal.status}">
                                ${appeal.status.toUpperCase()}
                            </span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Student</div>
                        <div class="detail-value">${appeal.student_first_name} ${appeal.student_last_name}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Student ID</div>
                        <div class="detail-value">${appeal.student_number}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Subject</div>
                        <div class="detail-value">${appeal.course_code} - ${appeal.subject_name}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Faculty</div>
                        <div class="detail-value">${appeal.faculty_first_name} ${appeal.faculty_last_name}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Academic Year</div>
                        <div class="detail-value">${appeal.year_start}-${appeal.year_end} (${appeal.semester})</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Current Grade</div>
                        <div class="detail-value">
                            ${appeal.overall_grade ? `${parseFloat(appeal.overall_grade).toFixed(2)} (${appeal.letter_grade})` : 'No grade'}
                        </div>
                    </div>
                </div>
                
                <div class="detail-item" style="margin-bottom: 20px;">
                    <div class="detail-label">Reason for Appeal</div>
                    <div class="detail-value">${appeal.reason || 'N/A'}</div>
                </div>
                
                <div class="detail-item" style="margin-bottom: 20px;">
                    <div class="detail-label">Date Submitted</div>
                    <div class="detail-value">${new Date(appeal.submitted_at).toLocaleDateString()}</div>
                </div>
                
                ${appeal.reviewed_at ? `
                <div class="detail-item" style="margin-bottom: 20px;">
                    <div class="detail-label">Reviewed By</div>
                    <div class="detail-value">Administrator on ${new Date(appeal.reviewed_at).toLocaleDateString()}</div>
                </div>
                ` : ''}
                
                ${appeal.admin_remarks ? `
                <div class="detail-item" style="margin-bottom: 20px;">
                    <div class="detail-label">Admin Remarks</div>
                    <div class="detail-value">${appeal.admin_remarks}</div>
                </div>
                ` : ''}
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="appeal_id" value="${appeal.id}">
                    
                    <div class="form-group">
                        <label>Update Status</label>
                        <select name="status" class="form-control" required>
                            <option value="pending" ${appeal.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="approved" ${appeal.status === 'approved' ? 'selected' : ''}>Approved</option>
                            <option value="rejected" ${appeal.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Remarks</label>
                        <textarea name="admin_remarks" class="form-control" placeholder="Enter your remarks...">${appeal.admin_remarks || ''}</textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update Appeal
                    </button>
                </form>
            `;
            
            document.getElementById('appealModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('appealModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('appealModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>