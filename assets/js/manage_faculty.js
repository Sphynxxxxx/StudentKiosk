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