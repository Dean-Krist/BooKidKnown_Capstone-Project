<?php
// Set headers to allow cross-origin requests and respond with JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

// 1. INCLUDE DATABASE CONNECTION
// This file should contain: $conn = mysqli_connect("server", "user", "password", "database");
require 'db_connect.php'; 

// Function to send a standardized JSON response and exit
function sendResponse($success, $message, $data = []) {
    global $conn;
    if ($conn) {
        mysqli_close($conn);
    }
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Invalid request method.");
}

// Define the root upload directory (MUST EXIST AND BE WRITEABLE)
$upload_dir_root = 'uploads/';

// --- 2. INPUT VALIDATION & INITIAL DATA EXTRACTION ---
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$total_pages = isset($_POST['total_pages']) ? (int)$_POST['total_pages'] : 0;

if (empty($title) || $total_pages < 1) {
    sendResponse(false, "Story title is required, and you must add at least one page (Total Pages: {$total_pages}).");
}

if (!isset($_FILES['cover_image']) || $_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) {
    sendResponse(false, "Cover image upload failed or is missing.");
}

// Start database transaction for atomicity
mysqli_autocommit($conn, false);
$success_flag = true;
$uploaded_files = [];
$new_story_id = null;


// --- 3. STORY METADATA UPLOAD (Table: stories) ---

// Create a unique directory for this story's files
$story_dir = $upload_dir_root . uniqid('story_', true) . '/';

if (!mkdir($story_dir, 0777, true)) {
    sendResponse(false, "Failed to create story directory.");
}

// 3.1. Handle Cover Image Upload
$cover_image = $_FILES['cover_image'];
$cover_image_ext = pathinfo($cover_image['name'], PATHINFO_EXTENSION);
$cover_image_name = 'cover.' . $cover_image_ext;
$cover_image_path = $story_dir . $cover_image_name;

if (move_uploaded_file($cover_image['tmp_name'], $cover_image_path)) {
    $uploaded_files[] = $cover_image_path;
} else {
    // Cleanup the directory created
    rmdir($story_dir);
    sendResponse(false, "Failed to upload cover image.");
}

// 3.2. Insert into stories table
$cover_db_path = str_replace($upload_dir_root, '', $cover_image_path); // Path relative to uploads dir
$sql_story = "INSERT INTO stories (title, description, cover_image_path) VALUES (?, ?, ?)";
$stmt_story = mysqli_prepare($conn, $sql_story);
mysqli_stmt_bind_param($stmt_story, "sss", $title, $description, $cover_db_path);

if (!mysqli_stmt_execute($stmt_story)) {
    $success_flag = false;
    $error_msg = "Database Error (Story): " . mysqli_error($conn);
} else {
    $new_story_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_story);
}


// --- 4. PAGE CONTENT UPLOAD (Table: pages) ---

if ($success_flag) {
    // Prepare the page insertion statement
    $sql_page = "INSERT INTO pages (story_id, page_number, text_content, mp4_file_path, mp4_file_2_path, mp4_file_3_path, narration_mp3_path, bgm_mp3_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_page = mysqli_prepare($conn, $sql_page);

    // Loop through each dynamic page created by the user
    for ($i = 1; $i <= $total_pages; $i++) {
        $page_number = $i;
        
        // Retrieve page text content
        $page_text = $_POST["page_text_content_{$i}"] ?? '';

        // Initialize all file paths for this page to NULL
        $mp4_1_path = null;
        $mp4_2_path = null;
        $mp4_3_path = null;
        $narration_path = null;
        $bgm_path = null;
        
        $file_fields = [
            'mp4_file' => 'mp4_1_path',
            'mp4_file_2' => 'mp4_2_path',
            'mp4_file_3' => 'mp4_3_path',
            'narration_mp3_file' => 'narration_path',
            'background_music_mp3_file' => 'bgm_path',
        ];

        // Process file uploads for the current page
        foreach ($file_fields as $post_name_base => &$db_path_var) {
            $post_name = "{$post_name_base}_page{$i}";

            if (isset($_FILES[$post_name]) && $_FILES[$post_name]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$post_name];
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                
                // Create a clean, unique name for the file
                $file_name_clean = "page{$i}_" . $post_name_base . '.' . $file_ext;
                $target_file = $story_dir . $file_name_clean;

                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    $db_path_var = str_replace($upload_dir_root, '', $target_file); // Store path relative to uploads dir
                    $uploaded_files[] = $target_file; // Track for potential rollback
                } else {
                    $success_flag = false;
                    $error_msg = "File upload failed for page {$i} ({$post_name}).";
                    break 2; // Exit both loops
                }
            }
        }
        unset($db_path_var); // Clean up reference

        if (!$success_flag) break;

        // Execute the page insertion
        // Bind parameters: (i)story_id, (i)page_number, (s)text_content, (s)mp4_1_path, ...
        mysqli_stmt_bind_param($stmt_page, "iissiissss", 
            $new_story_id, 
            $page_number, 
            $page_text, 
            $mp4_1_path, 
            $mp4_2_path, 
            $mp4_3_path, 
            $narration_path, 
            $bgm_path
        );

        if (!mysqli_stmt_execute($stmt_page)) {
            $success_flag = false;
            $error_msg = "Database Error (Page {$i}): " . mysqli_error($conn);
            break;
        }
    }
    mysqli_stmt_close($stmt_page);
}


// --- 5. TRANSACTION FINALIZATION ---

if ($success_flag) {
    mysqli_commit($conn);
    sendResponse(true, "Story '{$title}' with {$total_pages} pages uploaded successfully! Story ID: {$new_story_id}");
} else {
    // Rollback: Delete database entries and uploaded files
    mysqli_rollback($conn);

    // Use a function to safely delete the story directory and its contents
    if (isset($story_dir) && is_dir($story_dir)) {
        array_map('unlink', glob("{$story_dir}/*"));
        rmdir($story_dir);
    }
    
    sendResponse(false, "Upload Failed. Rolled back transaction. Error: " . ($error_msg ?? "Unknown file processing error."));
}

// Close connection (handled by sendResponse but good practice)
mysqli_close($conn);

?>