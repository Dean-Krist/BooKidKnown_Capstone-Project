<?php
header("Access-Control-Allow-Origin: *");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (isset($_GET['story_id']) && isset($_GET['page_number'])) {
    $storyId = intval($_GET['story_id']);
    $pageNumber = intval($_GET['page_number']);

    // CORRECTED: Added mp4_file_2 and mp4_file_3 to the SELECT statement
    $stmt = $conn->prepare("SELECT page_id, text_content, mp4_file_path, mp4_file_2, mp4_file_3, narration_mp3_path, background_music_mp3_path FROM pages WHERE story_id = ? AND page_number = ?");
    $stmt->bind_param("ii", $storyId, $pageNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $page = $result->fetch_assoc();
        $response['success'] = true;
        $response['page'] = $page;
        $response['message'] = 'Page details fetched successfully.';
    } else {
        $response['message'] = 'Page not found for the given Story ID and Page Number.';
    }

    $stmt->close();
} else {
    $response['message'] = 'Missing Story ID or Page Number.';
}

$conn->close();
echo json_encode($response);
?>