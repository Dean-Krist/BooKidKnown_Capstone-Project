<?php
include 'db_connect.php';

header('Content-Type: application/json');

$storyId = $_GET['story_id'] ?? null;

if ($storyId) {
    $stmt = $conn->prepare("SELECT story_id, title, description, cover_image_path FROM stories WHERE story_id = ?");
    $stmt->bind_param("i", $storyId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $story = $result->fetch_assoc();
        echo json_encode(['success' => true, 'story' => $story]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Story not found.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Story ID not provided.']);
}

$conn->close();
?>