<?php
header("Access-Control-Allow-Origin: *");
include 'db_connect.php';
header('Content-Type: application/json');

/* --------------------------------------------------------------
   GET ALL STORIES – Robust & Frontend-Ready
   -------------------------------------------------------------- */
$sql = "SELECT story_id, title, description, cover_image_path 
          FROM stories 
         ORDER BY story_id DESC";

$result = $conn->query($sql);

$stories = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stories[] = [
            'story_id'         => (int)$row['story_id'],
            'title'            => $row['title'] ?? 'Untitled',
            'description'      => $row['description'] ?? '',
            'cover_image_path' => $row['cover_image_path'] ?? null
        ];
    }
} else {
    // Always return valid JSON – even if empty
    $stories = [];
}

echo json_encode($stories);

$conn->close();
?>