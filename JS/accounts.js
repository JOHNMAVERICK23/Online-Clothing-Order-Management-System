let allAccounts = [];

document.addEventListener('DOMContentLoaded', function() {
    loadAccounts();
    setupEventListeners();
});

function setupEventListeners() {
    const btnAddAccount = document.getElementById('btnAddAccount');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const accountForm = document.getElementById('accountForm');
    const searchInput = document.getElementById('searchInput');

    btnAddAccount.addEventListener('click', openAddModal);
    closeModal.addEventListener('click', closeAccountModal);
    cancelBtn.addEventListener('click', closeAccountModal);
    accountForm.addEventListener('submit', submitAccount);
    searchInput.addEventListener('keyup', searchAccounts);

    // Close modal when clicking outside
    document.getElementById('accountModal').addEventListener('click', function(e) {
        if (e.target.id === 'accountModal') {
            closeAccountModal();
        }
    });
}

function loadAccounts() {
    fetch('../PROCESS/getAccounts.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allAccounts = data.accounts;
                displayAccounts(allAccounts);
            }
        })
        .catch(error => {
            showAlert('Error loading accounts', 'danger');
            console.error('Error:', error);
        });
}

function displayAccounts(accounts) {
    const tbody = document.getElementById('accountsTable');
    tbody.innerHTML = '';

    if (accounts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 30px;">No accounts found</td></tr>';
        return;
    }

    accounts.forEach(account => {
        const row = document.createElement('tr');
        const fullName = account.first_name + ' ' + account.last_name;
        const statusBadge = `<span class="badge ${account.status === 'active' ? 'badge-active' : 'badge-inactive'}">${account.status.toUpperCase()}</span>`;
        const roleBadge = `<span class="badge ${account.role === 'admin' ? 'badge-admin' : 'badge-staff'}">${account.role.toUpperCase()}</span>`;
        const lastLogin = account.last_login_at ? 
            new Date(account.last_login_at).toLocaleString() : 
            'Never';
        
        row.innerHTML = `
            <td>${fullName}</td>
            <td>${account.email}</td>
            <td>${roleBadge}</td>
            <td>${statusBadge}</td>
            <td>${lastLogin}</td>
            <td>${new Date(account.created_at).toLocaleDateString()}</td>
            <td>
                <div class="table-actions">
                    <button class="btn-warning" onclick="openEditModal(${account.id})">Edit</button>
                    <button class="btn-danger" onclick="deleteAccount(${account.id})">Delete</button>
                </div>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Account';
    document.getElementById('accountForm').reset();
    document.getElementById('accountId').value = '';
    document.getElementById('password').placeholder = 'Enter password';
    document.getElementById('accountModal').classList.add('active');
}

function openEditModal(id) {
    const account = allAccounts.find(a => a.id == id); // == instead of ===
    if (!account) {
        console.warn('Account not found for id:', id);
        return;
    }

    document.getElementById('modalTitle').textContent = 'Edit Account';
    document.getElementById('accountId').value = account.id;
    document.getElementById('firstName').value = account.first_name;
    document.getElementById('lastName').value = account.last_name;
    document.getElementById('email').value = account.email;
    document.getElementById('password').placeholder = 'Leave blank to keep current password';
    document.getElementById('password').value = '';
    document.getElementById('role').value = account.role;
    document.getElementById('accountModal').classList.add('active');
}

function closeAccountModal() {
    document.getElementById('accountModal').classList.remove('active');
    document.getElementById('accountForm').reset();
}

function submitAccount(e) {
    e.preventDefault();

    const id = document.getElementById('accountId').value;
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const role = document.getElementById('role').value;

    if (!firstName || !lastName || !email || !role) {
        showAlert('Please fill in all required fields', 'danger');
        return;
    }

    if (id === '' && !password) {
        showAlert('Password is required for new accounts', 'danger');
        return;
    }

    const formData = new FormData();
    formData.append('id', id);
    formData.append('first_name', firstName);
    formData.append('last_name', lastName);
    formData.append('email', email);
    formData.append('password', password);
    formData.append('role', role);

    fetch('../PROCESS/saveAccount.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeAccountModal();
            loadAccounts();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error saving account', 'danger');
        console.error('Error:', error);
    });
}

function deleteAccount(id) {
    if (!confirm('Are you sure you want to delete this account?')) return;

    const formData = new FormData();
    formData.append('id', id);

    fetch('../PROCESS/deleteAccount.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadAccounts();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error deleting account', 'danger');
        console.error('Error:', error);
    });
}

function searchAccounts() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const filtered = allAccounts.filter(account => {
        const fullName = (account.first_name + ' ' + account.last_name).toLowerCase();
        const email = account.email.toLowerCase();
        return fullName.includes(searchTerm) || email.includes(searchTerm);
    });
    displayAccounts(filtered);
}

function showAlert(message, type) {
    const alertContainer = document.getElementById('alertContainer');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertContainer.appendChild(alertDiv);

    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}


// =============================================
// PASSWORD RESET REQUESTS
// =============================================
function loadResetRequests() {
    fetch('../PROCESS/getResetRequests.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayResetRequests(data.requests);
                updatePendingBadge(data.requests);
            }
        })
        .catch(error => console.error('Error loading reset requests:', error));
}

function updatePendingBadge(requests) {
    const pending = requests.filter(r => r.status === 'pending').length;
    const badge = document.getElementById('pendingBadge');
    if (pending > 0) {
        badge.textContent = pending;
        badge.style.display = 'inline';
    } else {
        badge.style.display = 'none';
    }
}

function displayResetRequests(requests) {
    const tbody = document.getElementById('resetRequestsTable');
    tbody.innerHTML = '';

    if (requests.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">No password reset requests</td></tr>';
        return;
    }

    requests.forEach(req => {
        const isPending = req.status === 'pending';
        const statusBadge = `<span class="badge ${
            req.status === 'pending' ? 'badge-warning' :
            req.status === 'approved' ? 'badge-active' : 'badge-inactive'
        }">${req.status.toUpperCase()}</span>`;

        const actions = isPending ? `
            <div class="table-actions">
                <button class="btn-primary" onclick="handleResetRequest(${req.id}, 'approve')">Approve</button>
                <button class="btn-danger" onclick="handleResetRequest(${req.id}, 'reject')">Reject</button>
            </div>
        ` : '—';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${req.first_name} ${req.last_name}</td>
            <td>${req.email}</td>
            <td>${new Date(req.requested_at).toLocaleString()}</td>
            <td>${statusBadge}</td>
            <td>${actions}</td>
        `;
        tbody.appendChild(row);
    });
}

function handleResetRequest(requestId, action) {
    const confirmMsg = action === 'approve' 
        ? 'Approve this password reset request?' 
        : 'Reject this password reset request?';
    
    if (!confirm(confirmMsg)) return;

    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('action', action);

    fetch('../PROCESS/approvePasswordReset.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadResetRequests();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error processing request', 'danger');
        console.error('Error:', error);
    });
}

window.handleResetRequest = handleResetRequest;

// I-load kapag nag-load na ang page
loadResetRequests();

window.openEditModal = openEditModal;
window.deleteAccount = deleteAccount;