<?php
/* --------------------------------------------------------------
   get_pages_by_story.php – WORKS WITH YOUR DB
   Uses: mp4_file_path (your actual column)
   -------------------------------------------------------------- */
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

include 'db_connect.php';

$storyId = $_GET['story_id'] ?? null;

if (!$storyId || !ctype_digit((string)$storyId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Valid story_id (integer) is required.'
    ]);
    exit;
}

$storyId = (int)$storyId;

// USE YOUR REAL COLUMN: mp4_file_path
$stmt = $conn->prepare("
    SELECT
        page_number,
        text_content,
        mp4_file_path,
        mp4_file_2,
        mp4_file_3,
        narration_mp3_path,
        background_music_mp3_path
    FROM pages
    WHERE story_id = ?
    ORDER BY page_number ASC
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'DB Error: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param('i', $storyId);
$stmt->execute();
$result = $stmt->get_result();

$pages = [];
while ($row = $result->fetch_assoc()) {
    $pages[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'pages'   => $pages
]);
?>