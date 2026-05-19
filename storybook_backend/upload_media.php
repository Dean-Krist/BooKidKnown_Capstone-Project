<?php
// upload_media.php
require_once 'db_config.php'; // For consistent error handling/headers, though no DB interaction here directly

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit();
}

// Define upload directory relative to this script
// Make sure this directory exists and has write permissions for Apache
$uploadDir = 'uploads/';
$baseURL = "http://localhost/storybook_api/"; // Adjust to your actual base URL

if (isset($_FILES['file'])) {
    $file = $_FILES['file'];

    // Basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'File upload error: ' . $file['error']]);
        exit();
    }

    // Generate a unique filename to prevent overwrites and security issues
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uniqueFileName = uniqid() . '.' . $fileExtension;
    $filePath = $uploadDir . $uniqueFileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        echo json_encode(['status' => 'success', 'message' => 'File uploaded successfully!', 'url' => $baseURL . $filePath]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded. Ensure the input name is "file".']);
}
?>