<?php
require_once '../PROCESS/auth_check.php';
requireAdmin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Clothing Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span>Admin</span>
            </div>
        </div>
        <ul class="sidebar-menu sidebar-scroll">
            <li><a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="accounts.html"><i class="bi bi-people"></i> Manage Accounts</a></li>
            <li><a href="activityLog.html"><i class="bi bi-clock-history"></i> Activity Log</a></li>
            <li><a href="products.html"><i class="bi bi-box-seam"></i>Product Management</a></li>
            <li><a href="orders.php"><i class="bi bi-receipt"></i>Order Management</a></li>
            <li><a href="inventory.php"><i class="bi bi-boxes"></i>Inventory</a></li>
            <li><a href="cms.php" class="active"><i class="bi bi-file-earmark-text"></i> Content Management</a></li>
            <li><a href="../PROCESS/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
        
        
    </div>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">
                <i class="bi bi-speedometer2"></i> Dashboard
            </div>
            <div class="user-info">
                <span class="user-role">ADMIN</span>
                <a href="../PROCESS/logout.php" class="btn-logout">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-box primary">
                    <div class="stat-icon"><i class="bi-bag-check"></i></div>
                    <div class="stat-number" id="totalUsers">0</div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box success">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-number" id="adminCount">0</div>
                    <div class="stat-label">Pending Orders</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box warning">
                    <div class="stat-icon"><i class="bi bi-truck"></i></div>
                    <div class="stat-number" id="staffCount">0</div>
                    <div class="stat-label">Orders Shipped</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box danger">
                    <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                    <div class="stat-number" id="activeCount">0</div>
                    <div class="stat-label">Total Sales</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i class="bi bi-clock-history"></i> Recent Accounts</h2>
                        <a href="accounts.html" class="btn-primary">
                            <i class="bi bi-arrow-right"></i> View All
                        </a>
                    </div>
                    <div class="recent-activity">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                </tr>
                            </thead>
                            <tbody id="recentAccounts">
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 30px;">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i class="bi bi-lightning"></i> Quick Actions</h2>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="accounts.html" class="quick-action-btn">
                                <span class="quick-action-icon"><i class="bi bi-person-plus"></i></span>
                                Add New Account
                            </a>
                            <a href="activityLog.html" class="quick-action-btn">
                                <span class="quick-action-icon"><i class="bi bi-list-check"></i></span>
                                View Activity Log
                            </a>
                            <a href="#" class="quick-action-btn" onclick="refreshDashboard()">
                                <span class="quick-action-icon"><i class="bi bi-arrow-clockwise"></i></span>
                                Refresh Dashboard
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h2 class="card-title"><i class="bi bi-info-circle"></i> System Status</h2>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                            <span>Database Connection</span>
                            <span class="badge badge-active" id="dbStatus">Checking...</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                            <span>Server Uptime</span>
                            <span id="uptime">--:--:--</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span>Last Backup</span>
                            <span id="lastBackup">Never</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS/toast.js"></script>
    <script src="../JS/dashboard.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    loadOrderStats();
});

function loadOrderStats() {
    fetch('../PROCESS/getOrderStats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Total Orders (gamit ang totalUsers ID)
                document.getElementById('totalUsers').textContent = data.total_orders;

                // Pending Orders (gamit ang adminCount ID)
                document.getElementById('adminCount').textContent = data.pending_orders;

                // Shipped Orders (gamit ang staffCount ID)
                document.getElementById('staffCount').textContent = data.shipped_orders;

                // Total Sales (gamit ang activeCount ID)
                document.getElementById('activeCount').textContent = '₱' + parseFloat(data.total_sales).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                console.error('Failed to load order stats');
            }
        })
        .catch(error => {
            console.error('Error loading order stats:', error);
        });
}
</script>
</body>
</html>
