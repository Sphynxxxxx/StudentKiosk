// Faculty Management JavaScript Functions

// Global variables
let currentFacultyData = [];
let facultyDataMap = {};

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Load faculty data
    loadFacultyData();
    
    // Setup event listeners
    setupEventListeners();
    
    // Initialize faculty data map
    initializeFacultyDataMap();
});

// Load faculty data from table for search and filtering
function loadFacultyData() {
    const table = document.getElementById('facultyTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    
    currentFacultyData = Array.from(rows).map(row => ({
        element: row,
        checkbox: row.querySelector('.faculty-checkbox'),
        employeeId: row.cells[1]?.textContent?.toLowerCase() || '',
        name: row.cells[2]?.textContent?.toLowerCase() || '',
        email: row.cells[3]?.textContent?.toLowerCase() || '',
        department: row.cells[4]?.textContent?.toLowerCase() || '',
        position: row.cells[5]?.textContent?.toLowerCase() || '',
        employmentType: row.cells[6]?.textContent?.toLowerCase() || '',
        status: row.cells[7]?.textContent?.toLowerCase() || ''
    }));
}

// Initialize faculty data map from PHP data
function initializeFacultyDataMap() {
    if (typeof allFacultyData !== 'undefined') {
        allFacultyData.forEach(faculty => {
            facultyDataMap[faculty.id] = faculty;
        });
    }
}

// Setup event listeners
function setupEventListeners() {
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.classList.remove('show');
            }
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('.faculty-form');
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
    });
    
    // Auto-generate username
    setupUsernameGeneration();
}

// Auto-generate username from first and last name
function setupUsernameGeneration() {
    const firstNameField = document.getElementById('first_name');
    const lastNameField = document.getElementById('last_name');
    
    if (firstNameField && lastNameField) {
        firstNameField.addEventListener('blur', generateUsername);
        lastNameField.addEventListener('blur', generateUsername);
    }
}

function generateUsername() {
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const usernameField = document.getElementById('username');
    
    if (firstName && lastName && !usernameField.value) {
        const username = (firstName.charAt(0) + lastName).toLowerCase().replace(/[^a-z0-9]/g, '');
        usernameField.value = username;
    }
}

// Search and Filter Functions
function searchFaculty() {
    filterFaculty();
}

function filterFaculty() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const departmentFilter = document.getElementById('departmentFilter').value.toLowerCase();
    const positionFilter = document.getElementById('positionFilter').value.toLowerCase();
    const employmentFilter = document.getElementById('employmentFilter').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    
    currentFacultyData.forEach(faculty => {
        const matchesSearch = 
            faculty.employeeId.includes(searchTerm) ||
            faculty.name.includes(searchTerm) ||
            faculty.email.includes(searchTerm) ||
            faculty.department.includes(searchTerm) ||
            faculty.position.includes(searchTerm);
            
        const matchesDepartment = !departmentFilter || faculty.department.includes(departmentFilter);
        const matchesPosition = !positionFilter || faculty.position.includes(positionFilter);
        const matchesEmployment = !employmentFilter || faculty.employmentType.includes(employmentFilter);
        const matchesStatus = !statusFilter || faculty.status.includes(statusFilter);
        
        const isVisible = matchesSearch && matchesDepartment && matchesPosition && matchesEmployment && matchesStatus;
        faculty.element.style.display = isVisible ? '' : 'none';
    });
    
    updateNoResultsMessage();
    updateFilterSummary();
}

// Clear all filters
function clearAllFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('departmentFilter').value = '';
    document.getElementById('positionFilter').value = '';
    document.getElementById('employmentFilter').value = '';
    document.getElementById('statusFilter').value = '';
    
    // Show all faculty
    currentFacultyData.forEach(faculty => {
        faculty.element.style.display = '';
    });
    
    // Remove no results message
    const existingMessage = document.querySelector('.no-results-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    updateFilterSummary();
}

// Update no results message
function updateNoResultsMessage() {
    const table = document.getElementById('facultyTable');
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const visibleRows = tbody.querySelectorAll('tr[style=""], tr:not([style])');
    
    // Remove existing no results message
    const existingMessage = tbody.querySelector('.no-results-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // Add no results message if needed
    const visibleCount = Array.from(visibleRows).filter(row => !row.classList.contains('no-results-message')).length;
    if (visibleCount === 0) {
        const noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-results-message';
        noResultsRow.innerHTML = `
            <td colspan="9" style="text-align: center; padding: 3rem 2rem; color: #6b7280;">
                <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; display: block;"></i>
                <p style="margin: 0; font-size: 1.1rem;">No faculty members found matching your search criteria.</p>
                <button class="btn btn-outline" onclick="clearAllFilters()" style="margin-top: 1rem;">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
            </td>
        `;
        tbody.appendChild(noResultsRow);
    }
}

// Update filter summary
function updateFilterSummary() {
    const visibleRows = document.querySelectorAll('#facultyTable tbody tr[style=""], #facultyTable tbody tr:not([style])');
    const totalRows = currentFacultyData.length;
    const visibleCount = Array.from(visibleRows).filter(row => !row.classList.contains('no-results-message')).length;
    
    // Update or create summary
    let summary = document.querySelector('.filter-summary');
    if (!summary) {
        summary = document.createElement('div');
        summary.className = 'filter-summary';
        document.querySelector('.search-container').appendChild(summary);
    }
    
    if (visibleCount !== totalRows && totalRows > 0) {
        summary.innerHTML = `
            <i class="fas fa-filter"></i>
            Showing ${visibleCount} of ${totalRows} faculty members
            <button class="btn-link" onclick="clearAllFilters()">Show All</button>
        `;
        summary.style.display = 'flex';
    } else {
        summary.style.display = 'none';
    }
}

// Modal Functions
function openAddModal() {
    const modal = document.getElementById('addFacultyModal');
    if (!modal) return;
    
    modal.classList.add('show');
    
    // Reset form
    const form = modal.querySelector('.faculty-form');
    form.reset();
    clearFormErrors(form);
    
    // Focus first input
    setTimeout(() => {
        const firstInput = document.getElementById('first_name');
        if (firstInput) firstInput.focus();
    }, 100);
}

function closeAddModal() {
    const modal = document.getElementById('addFacultyModal');
    if (modal) {
        // Hide the modal
        modal.classList.remove('show');
        
        // Find and reset the form
        const form = modal.querySelector('.faculty-form');
        if (form) {
            form.reset();           // Clear all input values
            clearFormErrors(form);  // Remove validation errors
        }
    }
}

// Modal Functions
function openAddModal() {
    document.getElementById('addFacultyModal').style.display = 'block';
}

function closeAddModal() {
    document.getElementById('addFacultyModal').style.display = 'none';
    document.querySelector('#addFacultyModal form').reset();
}

function closeEditModal() {
    document.getElementById('editFacultyModal').style.display = 'none';
    document.getElementById('editFacultyForm').reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('addFacultyModal');
    const editModal = document.getElementById('editFacultyModal');
    
    if (event.target === addModal) {
        closeAddModal();
    }
    if (event.target === editModal) {
        closeEditModal();
    }
}

// View Faculty Details
function viewFaculty(facultyId) {
    const faculty = allFacultyData.find(f => f.id == facultyId);
    
    if (!faculty) {
        alert('Faculty member not found!');
        return;
    }
    
    // Create a detailed view modal
    const modalContent = `
        <div class="modal" id="viewFacultyModal" style="display: block;">
            <div class="modal-content" style="max-width: 900px; width: 90%; padding: 2rem;">
                <div class="modal-header">
                    <h2>Faculty Details</h2>
                    <span class="close" onclick="closeViewModal()">&times;</span>
                </div>
                <div class="faculty-details" style="padding: 1.5rem 0;">
                    <div class="detail-section">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Full Name:</label>
                                <span>${faculty.first_name} ${faculty.last_name}</span>
                            </div>
                            <div class="detail-item">
                                <label>Username:</label>
                                <span>@${faculty.username}</span>
                            </div>
                            <div class="detail-item">
                                <label>Email:</label>
                                <span>${faculty.email}</span>
                            </div>
                            <div class="detail-item">
                                <label>Employee ID:</label>
                                <span>${faculty.employee_id || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Phone:</label>
                                <span>${faculty.phone || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Status:</label>
                                <span class="status-badge status-${faculty.status}">${faculty.status.charAt(0).toUpperCase() + faculty.status.slice(1)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h3><i class="fas fa-briefcase"></i> Employment Information</h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Department:</label>
                                <span>${faculty.department_name || 'Not Assigned'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Position:</label>
                                <span>${faculty.position}</span>
                            </div>
                            <div class="detail-item">
                                <label>Employment Type:</label>
                                <span class="badge badge-${faculty.employment_type.toLowerCase().replace('-', '')}">${faculty.employment_type}</span>
                            </div>
                            <div class="detail-item">
                                <label>Hire Date:</label>
                                <span>${faculty.hire_date ? new Date(faculty.hire_date).toLocaleDateString() : 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Specialization:</label>
                                <span>${faculty.specialization || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h3><i class="fas fa-clock"></i> Consultation Hours</h3>
                        <p>${faculty.consultation_hours || 'No consultation hours set'}</p>
                    </div>
                    
                    <div class="detail-section">
                        <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Account Created:</label>
                                <span>${new Date(faculty.created_at).toLocaleString()}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
                    <button type="button" class="btn btn-primary" onclick="closeViewModal(); editFaculty(${facultyId});">
                        <i class="fas fa-edit"></i> Edit Faculty
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing view modal if any
    const existingModal = document.getElementById('viewFacultyModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalContent);
}

function closeViewModal() {
    const modal = document.getElementById('viewFacultyModal');
    if (modal) {
        modal.remove();
    }
}

// Edit Faculty
function editFaculty(facultyId) {
    const faculty = allFacultyData.find(f => f.id == facultyId);
    
    if (!faculty) {
        alert('Faculty member not found!');
        return;
    }
    
    // Populate edit form
    document.getElementById('edit_faculty_id').value = faculty.id;
    document.getElementById('edit_first_name').value = faculty.first_name;
    document.getElementById('edit_last_name').value = faculty.last_name;
    document.getElementById('edit_email').value = faculty.email;
    document.getElementById('edit_employee_id').value = faculty.employee_id || '';
    document.getElementById('edit_status').value = faculty.status;
    document.getElementById('edit_department_id').value = faculty.department_id || '';
    document.getElementById('edit_position').value = faculty.position;
    document.getElementById('edit_employment_type').value = faculty.employment_type;
    document.getElementById('edit_phone').value = faculty.phone || '';
    document.getElementById('edit_hire_date').value = faculty.hire_date || '';
    document.getElementById('edit_specialization').value = faculty.specialization || '';
    document.getElementById('edit_consultation_hours').value = faculty.consultation_hours || '';
    
    // Open edit modal
    document.getElementById('editFacultyModal').style.display = 'block';
}

// Delete Faculty
function deleteFaculty(facultyId, facultyName) {
    if (confirm(`Are you sure you want to delete ${facultyName}?\n\nThis action cannot be undone and will remove all associated data.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_faculty">
            <input type="hidden" name="faculty_id" value="${facultyId}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Reset Password
function resetPassword(facultyId) {
    const sendEmail = confirm(
        'Reset this faculty member\'s password?\n\n' +
        'Click OK to reset and send email notification\n' +
        'Click Cancel to abort'
    );
    
    if (sendEmail) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = `
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="faculty_id" value="${facultyId}">
            <input type="hidden" name="send_reset_email" value="1">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Search and Filter Functions
function searchFaculty() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const table = document.getElementById('facultyTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    let visibleCount = 0;
    
    for (let row of rows) {
        // Skip the "no data" row
        if (row.cells.length === 1) continue;
        
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    updateFilterSummary();
}

function filterFaculty() {
    const departmentFilter = document.getElementById('departmentFilter').value.toLowerCase();
    const positionFilter = document.getElementById('positionFilter').value.toLowerCase();
    const employmentFilter = document.getElementById('employmentFilter').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    
    const table = document.getElementById('facultyTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    let visibleCount = 0;
    
    for (let row of rows) {
        // Skip the "no data" row
        if (row.cells.length === 1) continue;
        
        const department = row.cells[4].textContent.toLowerCase();
        const position = row.cells[5].textContent.toLowerCase();
        const employment = row.cells[6].textContent.toLowerCase();
        const status = row.cells[7].textContent.toLowerCase();
        
        const departmentMatch = !departmentFilter || department.includes(departmentFilter);
        const positionMatch = !positionFilter || position.includes(positionFilter);
        const employmentMatch = !employmentFilter || employment.includes(employmentFilter);
        const statusMatch = !statusFilter || status.includes(statusFilter);
        
        if (departmentMatch && positionMatch && employmentMatch && statusMatch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    updateFilterSummary();
}

function clearAllFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('departmentFilter').value = '';
    document.getElementById('positionFilter').value = '';
    document.getElementById('employmentFilter').value = '';
    document.getElementById('statusFilter').value = '';
    
    const table = document.getElementById('facultyTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let row of rows) {
        row.style.display = '';
    }
    
    updateFilterSummary();
}

function updateFilterSummary() {
    const table = document.getElementById('facultyTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const summary = document.querySelector('.filter-summary');
    
    let visibleCount = 0;
    let totalCount = 0;
    
    for (let row of rows) {
        if (row.cells.length === 1) continue; // Skip "no data" row
        totalCount++;
        if (row.style.display !== 'none') {
            visibleCount++;
        }
    }
    
    if (visibleCount < totalCount) {
        summary.innerHTML = `<i class="fas fa-filter"></i> Showing ${visibleCount} of ${totalCount} faculty members`;
        summary.style.display = 'block';
    } else {
        summary.style.display = 'none';
    }
}

// Bulk Selection Functions
function selectAllFaculty() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.faculty-checkbox');
    
    checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        if (row.style.display !== 'none') {
            checkbox.checked = selectAll.checked;
        }
    });
    
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.faculty-checkbox:checked');
    const bulkActions = document.querySelector('.bulk-actions');
    const bulkCount = document.querySelector('.bulk-count');
    
    if (checkboxes.length > 0) {
        bulkActions.style.display = 'flex';
        bulkCount.textContent = checkboxes.length;
    } else {
        bulkActions.style.display = 'none';
    }
}

function clearSelection() {
    document.getElementById('selectAll').checked = false;
    const checkboxes = document.querySelectorAll('.faculty-checkbox');
    checkboxes.forEach(checkbox => checkbox.checked = false);
    updateBulkActions();
}

// Export Functions
function exportFaculty() {
    const table = document.getElementById('facultyTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    let csv = 'Employee ID,Name,Email,Department,Position,Employment Type,Status\n';
    
    for (let row of rows) {
        if (row.cells.length === 1 || row.style.display === 'none') continue;
        
        const employeeId = row.cells[1].textContent.trim();
        const name = row.cells[2].querySelector('strong').textContent.trim();
        const email = row.cells[3].textContent.trim();
        const department = row.cells[4].textContent.trim().replace(/\n/g, ' ');
        const position = row.cells[5].textContent.trim();
        const employment = row.cells[6].textContent.trim();
        const status = row.cells[7].textContent.trim();
        
        csv += `"${employeeId}","${name}","${email}","${department}","${position}","${employment}","${status}"\n`;
    }
    
    downloadCSV(csv, 'faculty_list.csv');
}

function exportSelected() {
    const checkboxes = document.querySelectorAll('.faculty-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select faculty members to export.');
        return;
    }
    
    let csv = 'Employee ID,Name,Email,Department,Position,Employment Type,Status\n';
    
    checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const employeeId = row.cells[1].textContent.trim();
        const name = row.cells[2].querySelector('strong').textContent.trim();
        const email = row.cells[3].textContent.trim();
        const department = row.cells[4].textContent.trim().replace(/\n/g, ' ');
        const position = row.cells[5].textContent.trim();
        const employment = row.cells[6].textContent.trim();
        const status = row.cells[7].textContent.trim();
        
        csv += `"${employeeId}","${name}","${email}","${department}","${position}","${employment}","${status}"\n`;
    });
    
    downloadCSV(csv, 'selected_faculty.csv');
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Print Function
function printFacultyList() {
    const table = document.getElementById('facultyTable').cloneNode(true);
    
    // Remove action column
    const headerRow = table.querySelector('thead tr');
    headerRow.deleteCell(-1); // Remove last header cell
    headerRow.deleteCell(0);  // Remove checkbox header
    
    const bodyRows = table.querySelectorAll('tbody tr');
    bodyRows.forEach(row => {
        if (row.cells.length > 1) {
            row.deleteCell(-1); // Remove action buttons
            row.deleteCell(0);  // Remove checkbox
        }
    });
    
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Faculty List</title>');
    printWindow.document.write('<style>');
    printWindow.document.write(`
        body { font-family: Arial, sans-serif; padding: 20px; }
        h1 { text-align: center; color: #1e3a8a; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #1e3a8a; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .status-active { background-color: #d1fae5; color: #065f46; }
        .status-inactive { background-color: #fee2e2; color: #991b1b; }
        @media print {
            body { padding: 10px; }
            button { display: none; }
        }
    `);
    printWindow.document.write('</style></head><body>');
    printWindow.document.write('<h1>ISATU Faculty List</h1>');
    printWindow.document.write('<p>Generated on: ' + new Date().toLocaleString() + '</p>');
    printWindow.document.write(table.outerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

// Test Email Function
function testEmail() {
    if (confirm('This will send a test email to verify your email configuration. Continue?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = '<input type="hidden" name="action" value="test_email">';
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Initialize filter summary
    updateFilterSummary();
});