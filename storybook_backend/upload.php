<?php
header("Access-Control-Allow-Origin: *");
include 'db_connect.php';
header('Content-Type: application/json');

$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$response = ['success' => false, 'message' => ''];

/* --------------------------------------------------------------
   1. CREATE STORY (only if title is provided and no story_id)
   -------------------------------------------------------------- */
$storyId = $_POST['story_id'] ?? null;
$title   = $_POST['title'] ?? null;
$desc    = $_POST['description'] ?? null;

// Only create new story if title is given AND no story_id passed
if (!$storyId && $title) {
    $coverPath = null;

    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $coverPath = $uploadDir . 'cover_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            move_uploaded_file($_FILES['cover_image']['tmp_name'], $coverPath);
        }
    }

    $stmt = $conn->prepare("INSERT INTO stories (title, description, cover_image_path) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $desc, $coverPath);
    if ($stmt->execute()) {
        $storyId = $conn->insert_id;
    }
    $stmt->close();

    if (!$storyId) {
        echo json_encode(['success' => false, 'message' => 'Failed to create story.']);
        exit();
    }
}

// If no storyId at this point → error
if (!$storyId) {
    echo json_encode(['success' => false, 'message' => 'No story to save pages to.']);
    exit();
}

/* --------------------------------------------------------------
   2. SAVE PAGES (with proper file handling & NULL safety)
   -------------------------------------------------------------- */
if (empty($_POST['pages'])) {
    echo json_encode(['success' => true, 'story_id' => $storyId, 'message' => 'Story ready (no pages added yet)']);
    exit();
}

$pages = $_POST['pages'];
$successCount = 0;

// Helper: safely upload file and return path or null
function uploadFileIfExists($index, $fieldKey, $uploadDir) {
    if (!isset($_FILES['pages']['name'][$index][$fieldKey]) || $_FILES['pages']['error'][$index][$fieldKey] !== UPLOAD_ERR_OK) {
        return null; // No file → let DB keep old value (or NULL if new page)
    }

    $name = $_FILES['pages']['name'][$index][$fieldKey];
    $tmp  = $_FILES['pages']['tmp_name'][$index][$fieldKey];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    $allowed = [
        'mp4_file'               => ['mp4'],
        'mp4_file_2'             => ['mp4'],
        'mp4_file_3'             => ['mp4'],
        'narration_mp3_file'     => ['mp3', 'mpeg'],
        'background_music_mp3_file' => ['mp3', 'mpeg']
    ];

    if (!in_array($ext, $allowed[$fieldKey] ?? [])) {
        return null;
    }

    $newName = 'file_' . uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $uploadDir . $newName;

    if (move_uploaded_file($tmp, $dest)) {
        return $dest;
    }

    error_log("Upload failed: " . $name);
    return null;
}

foreach ($pages as $i => $p) {
    $pageNum = (int)($p['page_number'] ?? 0);
    $text    = $p['text_content'] ?? '';

    if ($pageNum < 1) continue;

    // Get uploaded file paths (null if not uploaded)
    $mp4_1     = uploadFileIfExists($i, 'mp4_file', $uploadDir);
    $mp4_2     = uploadFileIfExists($i, 'mp4_file_2', $uploadDir);
    $mp4_3     = uploadFileIfExists($i, 'mp4_file_3', $uploadDir);
    $narration = uploadFileIfExists($i, 'narration_mp3_file', $uploadDir);
    $bgm       = uploadFileIfExists($i, 'background_music_mp3_file', $uploadDir);

    // For new pages: all null is fine
    // For existing pages: null means "do not change" → we use COALESCE in SQL

    $sql = "INSERT INTO pages 
            (story_id, page_number, text_content, mp4_file_path, mp4_file_2, mp4_file_3, narration_mp3_path, background_music_mp3_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            text_content = VALUES(text_content),
            mp4_file_path = COALESCE(VALUES(mp4_file_path), mp4_file_path),
            mp4_file_2 = COALESCE(VALUES(mp4_file_2), mp4_file_2),
            mp4_file_3 = COALESCE(VALUES(mp4_file_3), mp4_file_3),
            narration_mp3_path = COALESCE(VALUES(narration_mp3_path), narration_mp3_path),
            background_music_mp3_path = COALESCE(VALUES(background_music_mp3_path), background_music_mp3_path)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iissssss",
        $storyId,
        $pageNum,
        $text,
        $mp4_1,
        $mp4_2,
        $mp4_3,
        $narration,
        $bgm
    );

    if ($stmt->execute()) {
        $successCount++;
    }
    $stmt->close();
}

/* --------------------------------------------------------------
   3. FINAL RESPONSE
   -------------------------------------------------------------- */
if ($successCount > 0 || isset($_POST['pages'])) {
    echo json_encode([
        'success'  => true,
        'story_id' => $storyId,
        'message'  => $successCount > 0 ? "$successCount page(s) saved!" : "Story updated."
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No changes made.']);
}

$conn->close();
?>