<?php
/* --------------------------------------------------------------
   get_pages_by_story.php
   Returns all pages for a given story_id.
   Must be named EXACTLY this – the JS calls this endpoint.
   -------------------------------------------------------------- */

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

include 'db_connect.php';   // <-- uses the same connection as the rest of the app

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

/* --------------------------------------------------------------
   Query – note the column aliases that the front-end expects:
   mp4_file_path  → mp4_file_1
   mp4_file_2     → mp4_file_2
   mp4_file_3     → mp4_file_3
   -------------------------------------------------------------- */
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
        'message' => 'Prepare failed: ' . $conn->error
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

/* --------------------------------------------------------------
   ALWAYS return success:true – even when there are no pages.
   The front-end checks `data.success` and then uses `data.pages`.
   -------------------------------------------------------------- */
echo json_encode([
    'success' => true,
    'pages'   => $pages
]);
?>