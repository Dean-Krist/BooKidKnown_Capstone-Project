<?php
header("Access-Control-Allow-Origin: *");
include 'db_connect.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];
$target_dir = "uploads/";

// Ensure uploads directory exists
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

/* --------------------------------------------------------------
   1. DELETE PAGE (if requested)
   -------------------------------------------------------------- */
if (isset($_POST['delete_page']) && $_POST['delete_page'] === '1') {
    if (!isset($_POST['story_id']) || !isset($_POST['page_number'])) {
        echo json_encode(['success' => false, 'message' => 'Missing story_id or page_number for delete.']);
        exit();
    }

    $storyId = intval($_POST['story_id']);
    $pageNumber = intval($_POST['page_number']);

    // Fetch old files to delete
    $stmt_fetch = $conn->prepare("SELECT mp4_file_path, mp4_file_2, mp4_file_3, narration_mp3_path, background_music_mp3_path 
                                  FROM pages WHERE story_id = ? AND page_number = ?");
    $stmt_fetch->bind_param("ii", $storyId, $pageNumber);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    $old_files = $result->fetch_assoc();
    $stmt_fetch->close();

    // Delete physical files
    $fields = ['mp4_file_path', 'mp4_file_2', 'mp4_file_3', 'narration_mp3_path', 'background_music_mp3_path'];
    foreach ($fields as $field) {
        if (!empty($old_files[$field]) && file_exists($old_files[$field])) {
            @unlink($old_files[$field]);
        }
    }

    // Delete DB row
    $stmt_delete = $conn->prepare("DELETE FROM pages WHERE story_id = ? AND page_number = ?");
    $stmt_delete->bind_param("ii", $storyId, $pageNumber);
    $success = $stmt_delete->execute();
    $stmt_delete->close();

    if ($success) {
        $response['success'] = true;
        $response['message'] = 'Page deleted successfully.';
    } else {
        $response['message'] = 'Failed to delete page.';
    }

    echo json_encode($response);
    $conn->close();
    exit();
}

/* --------------------------------------------------------------
   2. UPDATE OR INSERT PAGE
   -------------------------------------------------------------- */
if (!isset($_POST['story_id']) || !isset($_POST['page_number'])) {
    echo json_encode(['success' => false, 'message' => 'Missing story_id or page_number.']);
    exit();
}

$storyId     = intval($_POST['story_id']);
$pageNumber  = intval($_POST['page_number']);
$textContent = $_POST['text_content'] ?? '';

// Check if page exists
$stmt_check = $conn->prepare("SELECT mp4_file_path, mp4_file_2, mp4_file_3, narration_mp3_path, background_music_mp3_path 
                              FROM pages WHERE story_id = ? AND page_number = ?");
$stmt_check->bind_param("ii", $storyId, $pageNumber);
$stmt_check->execute();
$result = $stmt_check->get_result();
$pageExists = $result->num_rows > 0;
$old_paths = $pageExists ? $result->fetch_assoc() : [];
$stmt_check->close();

// Improved file upload handler: returns old path on failure/no upload
function handleFileUpload($fieldKey, $oldPath, $target_dir) {
    // No new file → keep old path
    if (!isset($_FILES[$fieldKey]) || $_FILES[$fieldKey]['error'] !== UPLOAD_ERR_OK) {
        return $oldPath;
    }

    // Delete old file
    if ($oldPath && file_exists($oldPath)) {
        @unlink($oldPath);
    }

    $ext = strtolower(pathinfo($_FILES[$fieldKey]['name'], PATHINFO_EXTENSION));
    $allowed = ['mp4' => true, 'mp3' => true, 'mpeg' => true];
    if (!isset($allowed[$ext])) {
        return $oldPath; // reject invalid type
    }

    $newName = 'file_' . uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $target_dir . $newName;

    if (move_uploaded_file($_FILES[$fieldKey]['tmp_name'], $dest)) {
        return $dest;
    } else {
        error_log("Upload failed for: " . $_FILES[$fieldKey]['name']);
        return $oldPath; // fallback to old file
    }
}

// Process all media files
$mp4_1     = handleFileUpload('mp4_file', $old_paths['mp4_file_path'] ?? null, $target_dir);
$mp4_2     = handleFileUpload('mp4_file_2', $old_paths['mp4_file_2'] ?? null, $target_dir);
$mp4_3     = handleFileUpload('mp4_file_3', $old_paths['mp4_file_3'] ?? null, $target_dir);
$narration = handleFileUpload('narration_mp3_file', $old_paths['narration_mp3_path'] ?? null, $target_dir);
$bgm       = handleFileUpload('background_music_mp3_file', $old_paths['background_music_mp3_path'] ?? null, $target_dir);

// Convert empty strings to NULL for DB
$mp4_1     = $mp4_1     ?: null;
$mp4_2     = $mp4_2     ?: null;
$mp4_3     = $mp4_3     ?: null;
$narration = $narration ?: null;
$bgm       = $bgm       ?: null;

// Now save to database
if ($pageExists) {
    // UPDATE existing page
    $sql = "UPDATE pages SET 
                text_content = ?,
                mp4_file_path = ?,
                mp4_file_2 = ?,
                mp4_file_3 = ?,
                narration_mp3_path = ?,
                background_music_mp3_path = ?
            WHERE story_id = ? AND page_number = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssii", $textContent, $mp4_1, $mp4_2, $mp4_3, $narration, $bgm, $storyId, $pageNumber);
} else {
    // INSERT new page
    $sql = "INSERT INTO pages 
                (story_id, page_number, text_content, mp4_file_path, mp4_file_2, mp4_file_3, narration_mp3_path, background_music_mp3_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissssss", $storyId, $pageNumber, $textContent, $mp4_1, $mp4_2, $mp4_3, $narration, $bgm);
}

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Page saved successfully.';
} else {
    $response['message'] = 'Database error: ' . $stmt->error;
}

$stmt->close();
$conn->close();
echo json_encode($response);
?>