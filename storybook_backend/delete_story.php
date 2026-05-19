<?php
header("Access-Control-Allow-Origin: *");
include 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storyId = $_POST['story_id'] ?? null;

    if (!$storyId) {
        echo json_encode(['success' => false, 'message' => 'Story ID is required for deletion.']);
        exit();
    }

    // Start a transaction to ensure atomicity
    $conn->begin_transaction();

    try {
        // 1. Get paths of all files associated with this story's pages
        $filePathsToDelete = [];
        $stmt_pages = $conn->prepare("SELECT mp4_file_path, mp4_file_2, mp4_file_3, narration_mp3_path, background_music_mp3_path FROM pages WHERE story_id = ?");
        $stmt_pages->bind_param("i", $storyId);
        $stmt_pages->execute();
        $result_pages = $stmt_pages->get_result();

        while ($row = $result_pages->fetch_assoc()) {
            if (!empty($row['mp4_file_path'])) $filePathsToDelete[] = $row['mp4_file_path'];
            if (!empty($row['mp4_file_2'])) $filePathsToDelete[] = $row['mp4_file_2'];
            if (!empty($row['mp4_file_3'])) $filePathsToDelete[] = $row['mp4_file_3'];
            if (!empty($row['narration_mp3_path'])) $filePathsToDelete[] = $row['narration_mp3_path'];
            if (!empty($row['background_music_mp3_path'])) $filePathsToDelete[] = $row['background_music_mp3_path'];
        }
        $stmt_pages->close();

        // 2. Delete all pages from the database for the given story ID
        $stmt_delete_pages = $conn->prepare("DELETE FROM pages WHERE story_id = ?");
        $stmt_delete_pages->bind_param("i", $storyId);
        $stmt_delete_pages->execute();
        $stmt_delete_pages->close();

        // 3. Delete the story itself
        $stmt_delete_story = $conn->prepare("DELETE FROM stories WHERE story_id = ?");
        $stmt_delete_story->bind_param("i", $storyId);
        $stmt_delete_story->execute();

        if ($stmt_delete_story->affected_rows > 0) {
            // Commit transaction if successful so far
            $conn->commit();

            // 4. Delete the actual files from the server's filesystem
            $deletedFilesCount = 0;
            foreach ($filePathsToDelete as $filePath) {
                if (file_exists($filePath)) {
                    if (unlink($filePath)) {
                        $deletedFilesCount++;
                    } else {
                        error_log("Failed to delete file: " . $filePath);
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => "Story ID $storyId and its pages deleted successfully. ($deletedFilesCount associated files removed)."]);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Story not found or no rows deleted.']);
        }
        $stmt_delete_story->close();

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'An error occurred during deletion: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>