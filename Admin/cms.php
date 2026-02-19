<?php
session_start();
require_once '../PROCESS/db_config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.html');
    exit;
}

// Handle AJAX requests for getting data
if (isset($_GET['ajax'])) {
    $ajax_type = $_GET['ajax'];
    
    if ($ajax_type === 'get_section' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM cms_sections WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $section = $result->fetch_assoc();
            // Ensure images is a valid JSON string
            if (empty($section['images']) || $section['images'] === 'null') {
                $section['images'] = '[]';
            }
            echo json_encode($section);
        } else {
            echo json_encode(['error' => 'Section not found']);
        }
        exit;
    }
    elseif ($ajax_type === 'get_feature' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM cms_features WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $feature = $result->fetch_assoc();
            echo json_encode($feature);
        } else {
            echo json_encode(['error' => 'Feature not found']);
        }
        exit;
    }
}

// Handle CMS actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'save_section') {
            $section_id = $_POST['section_id'] ?? 0;
            $page = $_POST['page'];
            $section_type = $_POST['section_type'];
            $title = $_POST['title'];
            $subtitle = $_POST['subtitle'] ?? '';
            $description = $_POST['description'] ?? '';
            $content = $_POST['content'] ?? '';
            $button1_text = $_POST['button1_text'] ?? '';
            $button1_link = $_POST['button1_link'] ?? '';
            $button2_text = $_POST['button2_text'] ?? '';
            $button2_link = $_POST['button2_link'] ?? '';
            $images = $_POST['images'] ?? '[]';
            $status = $_POST['status'] ?? 'active';
            
            if ($section_id > 0) {
                // Update existing section
                $stmt = $conn->prepare("UPDATE cms_sections SET 
                    page = ?, section_type = ?, title = ?, subtitle = ?, 
                    description = ?, content = ?, button1_text = ?, button1_link = ?,
                    button2_text = ?, button2_link = ?, images = ?, status = ?,
                    updated_at = NOW()
                    WHERE id = ?");
                $stmt->bind_param("ssssssssssssi", 
                    $page, $section_type, $title, $subtitle, $description, $content,
                    $button1_text, $button1_link, $button2_text, $button2_link,
                    $images, $status, $section_id);
            } else {
                // Insert new section
                $stmt = $conn->prepare("INSERT INTO cms_sections 
                    (page, section_type, title, subtitle, description, content,
                     button1_text, button1_link, button2_text, button2_link,
                     images, status, sort_order, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())");
                $stmt->bind_param("ssssssssssss", 
                    $page, $section_type, $title, $subtitle, $description, $content,
                    $button1_text, $button1_link, $button2_text, $button2_link,
                    $images, $status);
            }
            
            if ($stmt->execute()) {
                $_SESSION['cms_success'] = 'Section saved successfully!';
            } else {
                $_SESSION['cms_error'] = 'Error saving section: ' . $stmt->error;
            }
        }
        elseif ($action === 'save_feature') {
            $id = $_POST['id'] ?? 0;
            $title = $_POST['title'];
            $description = $_POST['description'];
            $icon = $_POST['icon'];
            $sort_order = $_POST['sort_order'] ?? 0;
            $status = $_POST['status'] ?? 'active';
            
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE cms_features SET 
                    title = ?, description = ?, icon = ?, sort_order = ?, status = ?,
                    updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("sssisi", $title, $description, $icon, $sort_order, $status, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO cms_features 
                    (title, description, icon, sort_order, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("sssis", $title, $description, $icon, $sort_order, $status);
            }
            
            if ($stmt->execute()) {
                $_SESSION['cms_success'] = 'Feature saved successfully!';
            }
        }
        elseif ($action === 'delete_section') {
            $section_id = $_POST['section_id'];
            $stmt = $conn->prepare("DELETE FROM cms_sections WHERE id = ?");
            $stmt->bind_param("i", $section_id);
            if ($stmt->execute()) {
                $_SESSION['cms_success'] = 'Section deleted successfully!';
            }
        }
        elseif ($action === 'delete_feature') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM cms_features WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['cms_success'] = 'Feature deleted successfully!';
            }
        }
        
        // Refresh the page to show updated content
        header('Location: cms.php');
        exit;
    }
}

// Get CMS sections
$sectionsQuery = $conn->query("SELECT * FROM cms_sections WHERE page = 'homepage' ORDER BY sort_order ASC");
$sections = $sectionsQuery->fetch_all(MYSQLI_ASSOC);

// Get CMS features
$featuresQuery = $conn->query("SELECT * FROM cms_features ORDER BY sort_order ASC");
$cmsFeatures = $featuresQuery->fetch_all(MYSQLI_ASSOC);

// Common icon options for features
$iconOptions = [
    'fas fa-award', 'fas fa-truck', 'fas fa-undo', 'fas fa-headset',
    'fas fa-shield-alt', 'fas fa-gift', 'fas fa-clock', 'fas fa-tag',
    'fas fa-star', 'fas fa-heart', 'fas fa-lock', 'fas fa-check-circle',
    'fas fa-shipping-fast', 'fas fa-credit-card', 'fas fa-smile', 'fas fa-users'
];

// Common page sections for homepage
$homepageSections = ['hero', 'features', 'categories', 'featured', 'new_arrivals', 'newsletter'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - Clothing Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/cms.css">
    <style>
        /* Modal Fixes */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .modal-box.wide-modal {
            max-width: 900px;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }

        .modal-close:hover {
            color: #000;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 1rem 0;
            position: sticky;
            bottom: 0;
            background: white;
            border-top: 1px solid #dee2e6;
        }

        /* Icon selection styles */
        .icon-option {
            display: block;
            text-align: center;
            padding: 10px;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .icon-option:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }

        .icon-option input[type="radio"] {
            display: none;
        }

        .icon-option input[type="radio"]:checked + i {
            color: #0d6efd;
        }

        .icon-option i {
            font-size: 1.5rem;
            color: #6c757d;
        }

        /* Preview cards */
        .feature-preview-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            height: 100%;
            transition: transform 0.2s;
            background: white;
        }

        .feature-preview-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .feature-icon-preview {
            font-size: 2rem;
            color: #0d6efd;
            margin-bottom: 15px;
        }

        .feature-actions {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
            margin-top: 10px;
        }

        /* Table improvements */
        .table-actions {
            display: flex;
            gap: 5px;
        }

        /* Card improvements */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
        }

        .card-title {
            margin: 0;
            font-size: 1.25rem;
        }

        /* Fix for Bootstrap modal conflict */
        body.modal-open {
            overflow: hidden;
        }

        /* Button styles */
        .btn-primary, .btn-secondary {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: #0d6efd;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
        }

        /* Alert styles */
        .alert {
            margin: 1rem;
            border-radius: 5px;
        }

        /* Stat boxes */
        .stat-box {
            border-radius: 8px;
            padding: 1.5rem;
            color: white;
            text-align: center;
        }

        .stat-box.primary { background-color: #0d6efd; }
        .stat-box.success { background-color: #198754; }
        .stat-box.warning { background-color: #ffc107; color: #212529; }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">Admin</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="accounts.html"><i class="bi bi-people"></i> Manage Accounts</a></li>
            <li><a href="activityLog.html"><i class="bi bi-clock-history"></i> Activity Log</a></li>
            <li><a href="products.html"><i class="bi bi-box-seam"></i> Product Management</a></li>
            <li><a href="orders.php"><i class="bi bi-receipt"></i> Order Management</a></li>
            <li><a href="inventory.php"><i class="bi bi-boxes"></i> Inventory</a></li>
            <li><a href="cms.php" class="active"><i class="bi bi-file-earmark-text"></i> Content Management</a></li>
            <li><a href="../PROCESS/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title"><i class="bi bi-file-earmark-text"></i> Content Management System</div>
            <div class="user-info">
                <span class="user-role"><?php echo strtoupper($_SESSION['user_role']); ?></span>
                <a href="../PROCESS/logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>

        <?php if (isset($_SESSION['cms_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['cms_success']; unset($_SESSION['cms_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['cms_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['cms_error']; unset($_SESSION['cms_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- CMS Dashboard -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="stat-box primary">
                    <div class="stat-icon"><i class="bi bi-file-text"></i></div>
                    <div class="stat-number"><?php echo count($sections); ?></div>
                    <div class="stat-label">Page Sections</div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="stat-box success">
                    <div class="stat-icon"><i class="bi bi-star"></i></div>
                    <div class="stat-number"><?php echo count($cmsFeatures); ?></div>
                    <div class="stat-label">Features</div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" id="cmsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button" role="tab">
                    <i class="bi bi-file-text"></i> Page Sections
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features" type="button" role="tab">
                    <i class="bi bi-star"></i> Features
                </button>
            </li>
        </ul>

        <div class="tab-content" id="cmsTabContent">
            <!-- Page Sections Tab -->
            <div class="tab-pane fade show active" id="sections" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Homepage Sections</h2>
                        <button class="btn-primary" onclick="openSectionModal()">
                            <i class="bi bi-plus"></i> Add New Section
                        </button>
                    </div>
                    
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Section</th>
                                    <th>Title</th>
                                    <th>Subtitle</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sections)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No sections found. Click "Add New Section" to create one.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($sections as $section): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo ucfirst($section['section_type']); ?></strong><br>
                                        <small class="text-muted"><?php echo $section['page']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($section['title'], 0, 50)); ?>...</td>
                                    <td><?php echo htmlspecialchars(substr($section['subtitle'] ?? '', 0, 30)); ?>...</td>
                                    <td>
                                        <span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($section['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($section['updated_at'])); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editSection(<?php echo $section['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSection(<?php echo $section['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Features Tab -->
            <div class="tab-pane fade" id="features" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Features Management</h2>
                        <button class="btn-primary" onclick="openFeatureModal()">
                            <i class="bi bi-plus"></i> Add New Feature
                        </button>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($cmsFeatures)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-star" style="font-size: 3rem; color: #6c757d;"></i>
                            <h4 class="mt-3">No features found</h4>
                            <p>Click "Add New Feature" to create your first feature</p>
                        </div>
                        <?php else: ?>
                        <div class="row g-3 mb-3">
                            <?php foreach ($cmsFeatures as $feature): ?>
                            <div class="col-md-3">
                                <div class="feature-preview-card">
                                    <div class="feature-icon-preview">
                                        <i class="<?php echo htmlspecialchars($feature['icon']); ?>"></i>
                                    </div>
                                    <h5><?php echo htmlspecialchars($feature['title']); ?></h5>
                                    <p><?php echo htmlspecialchars(substr($feature['description'], 0, 80)); ?>...</p>
                                    <div class="feature-actions">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editFeature(<?php echo $feature['id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteFeature(<?php echo $feature['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Modal -->
    <div class="modal-overlay" id="sectionModal">
        <div class="modal-box wide-modal">
            <div class="modal-header">
                <h3 id="sectionModalTitle">Edit Section</h3>
                <button class="modal-close" onclick="closeSectionModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="sectionForm" method="POST">
                    <input type="hidden" name="action" value="save_section">
                    <input type="hidden" id="section_id" name="section_id" value="0">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Page</label>
                            <select class="form-control" id="page" name="page" required>
                                <option value="homepage">Homepage</option>
                                <option value="about">About Page</option>
                                <option value="contact">Contact Page</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Section Type</label>
                            <select class="form-control" id="section_type" name="section_type" required>
                                <option value="">Select Section Type</option>
                                <?php foreach ($homepageSections as $section): ?>
                                <option value="<?php echo $section; ?>"><?php echo ucfirst(str_replace('_', ' ', $section)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subtitle</label>
                            <input type="text" class="form-control" id="subtitle" name="subtitle">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Content (HTML allowed)</label>
                        <textarea class="form-control" id="content" name="content" rows="5" placeholder="Full HTML content for the section"></textarea>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Button 1 Text</label>
                            <input type="text" class="form-control" id="button1_text" name="button1_text">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Button 1 Link</label>
                            <input type="text" class="form-control" id="button1_link" name="button1_link" placeholder="shop.php">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Button 2 Text</label>
                            <input type="text" class="form-control" id="button2_text" name="button2_text">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Button 2 Link</label>
                            <input type="text" class="form-control" id="button2_link" name="button2_link" placeholder="#new-arrivals">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Images (JSON array of URLs)</label>
                        <textarea class="form-control" id="images" name="images" rows="3" placeholder='["image1.jpg", "image2.jpg"]'></textarea>
                        <small class="text-muted">Enter image URLs as JSON array. Leave empty for default images.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeSectionModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Save Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Feature Modal -->
    <div class="modal-overlay" id="featureModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="featureModalTitle">Edit Feature</h3>
                <button class="modal-close" onclick="closeFeatureModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="featureForm" method="POST">
                    <input type="hidden" name="action" value="save_feature">
                    <input type="hidden" id="feature_id" name="id" value="0">
                    
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" id="feature_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="feature_description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Icon</label>
                        <div class="row g-2 mb-2">
                            <?php foreach ($iconOptions as $icon): ?>
                            <div class="col-3">
                                <label class="icon-option">
                                    <input type="radio" name="icon" value="<?php echo $icon; ?>">
                                    <i class="<?php echo $icon; ?>"></i>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" class="form-control mt-2" id="feature_icon_input" name="icon_input" placeholder="Or enter custom icon class">
                        <input type="hidden" id="selected_icon" name="icon" value="">
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" id="feature_sort_order" name="sort_order" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-control" id="feature_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeFeatureModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Save Feature</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent body scroll when modal is open
        function preventBodyScroll(prevent) {
            document.body.style.overflow = prevent ? 'hidden' : '';
        }
        
        // Section Management
        function openSectionModal(sectionId = 0) {
            const modal = document.getElementById('sectionModal');
            const title = document.getElementById('sectionModalTitle');
            
            if (sectionId === 0) {
                title.textContent = 'Add New Section';
                document.getElementById('sectionForm').reset();
                document.getElementById('section_id').value = '0';
                document.getElementById('page').value = 'homepage';
                document.getElementById('status').value = 'active';
                modal.classList.add('active');
                preventBodyScroll(true);
            } else {
                // Fetch section data via AJAX
                fetch(`cms.php?ajax=get_section&id=${sectionId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            return;
                        }
                        
                        title.textContent = 'Edit Section';
                        document.getElementById('section_id').value = data.id;
                        document.getElementById('page').value = data.page || 'homepage';
                        document.getElementById('section_type').value = data.section_type || '';
                        document.getElementById('title').value = data.title || '';
                        document.getElementById('subtitle').value = data.subtitle || '';
                        document.getElementById('description').value = data.description || '';
                        document.getElementById('content').value = data.content || '';
                        document.getElementById('button1_text').value = data.button1_text || '';
                        document.getElementById('button1_link').value = data.button1_link || '';
                        document.getElementById('button2_text').value = data.button2_text || '';
                        document.getElementById('button2_link').value = data.button2_link || '';
                        document.getElementById('images').value = data.images || '[]';
                        document.getElementById('status').value = data.status || 'active';
                        
                        modal.classList.add('active');
                        preventBodyScroll(true);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to load section data');
                    });
            }
        }
        
        function closeSectionModal() {
            document.getElementById('sectionModal').classList.remove('active');
            preventBodyScroll(false);
        }
        
        function editSection(sectionId) {
            openSectionModal(sectionId);
        }
        
        function deleteSection(sectionId) {
            if (confirm('Are you sure you want to delete this section? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_section">
                    <input type="hidden" name="section_id" value="${sectionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Feature Management
        function openFeatureModal(featureId = 0) {
            const modal = document.getElementById('featureModal');
            const title = document.getElementById('featureModalTitle');
            
            if (featureId === 0) {
                title.textContent = 'Add New Feature';
                document.getElementById('featureForm').reset();
                document.getElementById('feature_id').value = '0';
                document.getElementById('feature_status').value = 'active';
                
                // Reset icon selection
                document.querySelectorAll('input[name="icon"]').forEach(radio => {
                    radio.checked = false;
                });
                document.getElementById('feature_icon_input').value = '';
                document.getElementById('selected_icon').value = '';
                
                modal.classList.add('active');
                preventBodyScroll(true);
            } else {
                fetch(`cms.php?ajax=get_feature&id=${featureId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            return;
                        }
                        
                        title.textContent = 'Edit Feature';
                        document.getElementById('feature_id').value = data.id;
                        document.getElementById('feature_title').value = data.title || '';
                        document.getElementById('feature_description').value = data.description || '';
                        document.getElementById('feature_sort_order').value = data.sort_order || 0;
                        document.getElementById('feature_status').value = data.status || 'active';
                        
                        // Set icon selection
                        let iconFound = false;
                        document.querySelectorAll('input[name="icon"]').forEach(radio => {
                            if (radio.value === data.icon) {
                                radio.checked = true;
                                iconFound = true;
                                document.getElementById('selected_icon').value = data.icon;
                            }
                        });
                        
                        // Set custom icon input if no radio matches
                        if (!iconFound && data.icon) {
                            document.getElementById('feature_icon_input').value = data.icon;
                            document.getElementById('selected_icon').value = data.icon;
                        }
                        
                        modal.classList.add('active');
                        preventBodyScroll(true);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to load feature data');
                    });
            }
        }
        
        function closeFeatureModal() {
            document.getElementById('featureModal').classList.remove('active');
            preventBodyScroll(false);
        }
        
        function editFeature(featureId) {
            openFeatureModal(featureId);
        }
        
        function deleteFeature(featureId) {
            if (confirm('Are you sure you want to delete this feature? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_feature">
                    <input type="hidden" name="id" value="${featureId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Form submissions
        document.getElementById('sectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
        
        document.getElementById('featureForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Get selected icon or custom input
            const selectedIcon = document.querySelector('input[name="icon"]:checked');
            const customIcon = document.getElementById('feature_icon_input').value;
            
            // Set the hidden icon field
            const iconValue = selectedIcon ? selectedIcon.value : customIcon;
            document.getElementById('selected_icon').value = iconValue;
            
            if (!iconValue) {
                alert('Please select or enter an icon');
                return;
            }
            
            this.submit();
        });
        
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
                preventBodyScroll(false);
            }
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal-overlay.active');
                modals.forEach(modal => {
                    modal.classList.remove('active');
                    preventBodyScroll(false);
                });
            }
        });
        
        // Icon selection handling
        document.querySelectorAll('input[name="icon"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('selected_icon').value = this.value;
                document.getElementById('feature_icon_input').value = '';
            });
        });
        
        // Custom icon input handling
        document.getElementById('feature_icon_input').addEventListener('input', function() {
            if (this.value) {
                document.querySelectorAll('input[name="icon"]').forEach(radio => {
                    radio.checked = false;
                });
                document.getElementById('selected_icon').value = this.value;
            }
        });
        
        // Initialize Bootstrap tabs
        const triggerTabList = [].slice.call(document.querySelectorAll('#cmsTabs button'));
        triggerTabList.forEach(function (triggerEl) {
            const tabTrigger = new bootstrap.Tab(triggerEl);
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });
    </script>
</body>
</html>