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
        'message' => 'No file uploaded or upload error occurred'
    ]));
}

$file = $_FILES['image'];
$fileName = $file['name'];
$fileTmp = $file['tmp_name'];
$fileSize = $file['size'];
$fileError = $file['error'];

// Validate file
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Check file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$fileMimeType = finfo_file($finfo, $fileTmp);
finfo_close($finfo);

if (!in_array($fileMimeType, $allowedTypes)) {
    exit(json_encode([
        'success' => false,
        'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.'
    ]));
}

// Check file size
if ($fileSize > $maxSize) {
    exit(json_encode([
        'success' => false,
        'message' => 'File size exceeds 5MB limit'
    ]));
}

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/products/';

// Create nested directories
if (!is_dir(__DIR__ . '/../uploads')) {
    mkdir(__DIR__ . '/../uploads', 0755, true);
}
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
$newFileName = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
$uploadPath = $uploadDir . $newFileName;

// Move uploaded file
if (!move_uploaded_file($fileTmp, $uploadPath)) {
    exit(json_encode([
        'success' => false,
        'message' => 'Failed to save file'
    ]));
}

// Set proper permissions
chmod($uploadPath, 0644);

// Generate URL for the uploaded image
// IMPORTANT: Adjust this path based on your project structure
$imageUrl = '../uploads/products/' . $newFileName;

// Verify file exists
if (!file_exists($uploadPath)) {
    exit(json_encode([
        'success' => false,
        'message' => 'File verification failed'
    ]));
}

exit(json_encode([
    'success' => true,
    'message' => 'Image uploaded successfully',
    'imageUrl' => $imageUrl,
    'fileName' => $newFileName
]));
?>