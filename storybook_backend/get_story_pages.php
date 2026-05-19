<?php
header("Access-Control-Allow-Origin: *");
include 'db_connect.php';

header('Content-Type: application/json');

$storyId = $_GET['story_id'] ?? null;

if (!$storyId) {
    echo json_encode([]);
    $conn->close();
    exit();
}

$sql = "SELECT page_id, page_number, text_content, mp4_file_path, mp4_file_2, narration_mp3_path, background_music_mp3_path FROM pages WHERE story_id = ? ORDER BY page_number ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $storyId);
$stmt->execute();
$result = $stmt->get_result();

$pages = [];
while ($row = $result->fetch_assoc()) {
    $pages[] = $row;
}

echo json_encode($pages);

$stmt->close();
$conn->close();
?>