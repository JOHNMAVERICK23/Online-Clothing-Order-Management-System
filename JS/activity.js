let allActivities = [];
let currentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadActivityLogs();
    loadActivityStats();
    setupEventListeners();
});

function setupEventListeners() {
    const searchInput = document.getElementById('searchInput');
    const actionFilter = document.getElementById('actionFilter');
    const dateFilter = document.getElementById('dateFilter');

    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    }

    if (actionFilter) {
        actionFilter.addEventListener('change', applyFilters);
    }

    if (dateFilter) {
        dateFilter.addEventListener('change', applyFilters);
    }
}

function loadActivityLogs(page = 1) {
    const search = document.getElementById('searchInput')?.value || '';
    const action = document.getElementById('actionFilter')?.value || '';
    const date = document.getElementById('dateFilter')?.value || '';

    const params = new URLSearchParams({
        search: search,
        action: action,
        date: date,
        page: page
    });

    fetch(`../PROCESS/getActivityLogs.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayActivityLogs(data.activities);
                updatePagination(data.pagination);
            } else {
                showError('Failed to load activity logs');
            }
        })
        .catch(error => {
            showError('Error loading activity logs');
            console.error('Error:', error);
        });
}

function displayActivityLogs(activities) {
    const tbody = document.getElementById('activityBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (activities.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px;">
                    <i class="bi bi-inbox" style="font-size: 48px; color: #ddd; display: block; margin-bottom: 15px;"></i>
                    <h5 style="color: #999;">No activities found</h5>
                    <p class="text-muted">Try adjusting your filters</p>
                </td>
            </tr>
        `;
        return;
    }

    activities.forEach(activity => {
        const row = document.createElement('tr');
        const fullName = activity.first_name && activity.last_name ? 
            `${activity.first_name} ${activity.last_name}` : 
            (activity.email || 'System');
        
        const actionBadge = getActionBadge(activity.action);
        const statusBadge = getStatusBadge(activity.action);
        const dateTime = new Date(activity.created_at).toLocaleString();

        row.innerHTML = `
            <td>
                <div style="font-weight: 600; color: #1a1a1a;">${fullName}</div>
                <div style="font-size: 12px; color: #999;">${activity.email || 'N/A'}</div>
            </td>
            <td>${actionBadge}</td>
            <td>
                <small>${activity.details || 'No details'}</small>
            </td>
            <td>
                <code style="background: #f5f5f5; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                    ${activity.ip_address || 'N/A'}
                </code>
            </td>
            <td>
                <small>${dateTime}</small>
            </td>
            <td>${statusBadge}</td>
        `;

        tbody.appendChild(row);
    });
}

function getActionBadge(action) {
    if (!action) return '<span class="badge bg-secondary">Unknown</span>';

    action = action.toLowerCase();

    if (action.includes('login')) {
        return '<span class="badge bg-success"><i class="bi bi-box-arrow-in-right"></i> Login</span>';
    } else if (action.includes('logout')) {
        return '<span class="badge bg-secondary"><i class="bi bi-box-arrow-right"></i> Logout</span>';
    } else if (action.includes('created') || action.includes('create')) {
        return '<span class="badge bg-info"><i class="bi bi-plus-circle"></i> Created</span>';
    } else if (action.includes('updated') || action.includes('update')) {
        return '<span class="badge bg-warning"><i class="bi bi-pencil"></i> Updated</span>';
    } else if (action.includes('deleted') || action.includes('delete')) {
        return '<span class="badge bg-danger"><i class="bi bi-trash"></i> Deleted</span>';
    } else {
        return `<span class="badge bg-secondary">${action}</span>`;
    }
}

function getStatusBadge(action) {
    if (!action) return '<span class="badge bg-light text-dark">Unknown</span>';

    action = action.toLowerCase();

    if (action.includes('failed') || action.includes('error')) {
        return '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Failed</span>';
    } else if (action.includes('login') || action.includes('logout')) {
        return '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Success</span>';
    } else {
        return '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Success</span>';
    }
}

function loadActivityStats() {
    const params = new URLSearchParams({
        search: '',
        action: '',
        date: '',
        page: 1
    });

    fetch(`../PROCESS/getActivityLogs.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stats) {
                animateCounter('totalActivities', data.stats.total_activities);
                animateCounter('loginCount', data.stats.logins_today);
                animateCounter('editCount', data.stats.changes_made);
                animateCounter('failedCount', data.stats.failed_actions);
            }
        })
        .catch(error => console.error('Error:', error));
}

function animateCounter(elementId, targetValue) {
    const element = document.getElementById(elementId);
    if (!element) return;

    const duration = 800;
    const step = 20;
    const totalSteps = duration / step;
    const increment = targetValue / totalSteps;
    let current = 0;
    let stepCount = 0;

    const timer = setInterval(() => {
        stepCount++;
        current += increment;
        if (stepCount >= totalSteps) {
            current = targetValue;
            clearInterval(timer);
        }
        element.textContent = Math.round(current);
    }, step);
}

function updatePagination(pagination) {
    const paginationContainer = document.getElementById('paginationContainer');
    if (!paginationContainer) return;

    if (pagination.total_pages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }

    let html = '<nav aria-label="Page navigation"><ul class="pagination">';

    // Previous button
    html += `
        <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${pagination.current_page - 1})">Previous</a>
        </li>
    `;

    // Page numbers
    const maxPages = 5;
    let startPage = Math.max(1, pagination.current_page - Math.floor(maxPages / 2));
    let endPage = Math.min(pagination.total_pages, startPage + maxPages - 1);

    if (endPage - startPage + 1 < maxPages) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `
            <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="goToPage(${i})">${i}</a>
            </li>
        `;
    }

    // Next button
    html += `
        <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${pagination.current_page + 1})">Next</a>
        </li>
    `;

    html += '</ul></nav>';
    paginationContainer.innerHTML = html;
}

function applyFilters() {
    currentPage = 1;
    loadActivityLogs(1);
}

function goToPage(page) {
    if (page < 1) return;
    currentPage = page;
    loadActivityLogs(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showError(message) {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;

    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show';
    alert.innerHTML = `
        <i class="bi bi-exclamation-triangle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    alertContainer.appendChild(alert);

    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Make functions globally available
window.applyFilters = applyFilters;
window.goToPage = goToPage;