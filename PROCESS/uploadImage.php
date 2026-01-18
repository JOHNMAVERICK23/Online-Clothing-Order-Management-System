<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    exit(json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]));
}

// Check if file was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    exit(json_encode([
        'success' => false,
        'message' => 'No file uploaded or upload error'
    ]));
}

$file = $_FILES['image'];

// Validate file size
if ($file['size'] > MAX_FILE_SIZE) {
    exit(json_encode([
        'success' => false,
        'message' => 'File size must be less than 5MB'
    ]));
}

// Validate file type
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileExt, ALLOWED_TYPES)) {
    exit(json_encode([
        'success' => false,
        'message' => 'Only JPG, PNG, GIF, and WebP files are allowed'
    ]));
}

// Generate unique filename
$uniqueName = uniqid() . '_' . time() . '.' . $fileExt;
$uploadPath = UPLOAD_DIR . $uniqueName;

// Create uploads directory if it doesn't exist
if (!file_exists(dirname($uploadPath))) {
    mkdir(dirname($uploadPath), 0777, true);
}

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // Return the relative URL
    $imageUrl = 'uploads/products/' . $uniqueName;
    
    exit(json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'imageUrl' => $imageUrl
    ]));
} else {
    exit(json_encode([
        'success' => false,
        'message' => 'Failed to upload image'
    ]));
}
?>
