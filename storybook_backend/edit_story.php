<?php
include 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storyId = $_POST['story_id'] ?? null;
    $title = $_POST['title'] ?? null;
    $description = $_POST['description'] ?? null;
    $coverImagePath = null; // New cover image path if uploaded

    if (!$storyId) {
        echo json_encode(['success' => false, 'message' => 'Story ID is required for editing.']);
        exit();
    }

    $uploadDir = 'uploads/'; // Directory where files are stored
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Handle cover image upload if a new one is provided
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        // First, get the old cover image path to delete it
        $stmt_old_path = $conn->prepare("SELECT cover_image_path FROM stories WHERE story_id = ?");
        $stmt_old_path->bind_param("i", $storyId);
        $stmt_old_path->execute();
        $result_old_path = $stmt_old_path->get_result();
        if ($result_old_path->num_rows > 0) {
            $row = $result_old_path->fetch_assoc();
            $oldCoverImagePath = $row['cover_image_path'];
            if (!empty($oldCoverImagePath) && file_exists($oldCoverImagePath)) {
                // To safely delete the file, ensure the file path is accessible and relative to the script
                // Since the database stores relative paths ('uploads/...'), the old code might be using 
                // the path relative to the script. We keep the original unlink logic.
                unlink($oldCoverImagePath); // Delete old file
            }
        }
        $stmt_old_path->close();

        // Upload the new cover image
        $coverImageFileName = uniqid() . '_' . basename($_FILES['cover_image']['name']);
        $coverImageUploadPath = $uploadDir . $coverImageFileName;
        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $coverImageUploadPath)) {
            $coverImagePath = $coverImageUploadPath;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload new cover image.']);
            exit();
        }
    }

    // Prepare the update query based on what fields are provided
    $updateFields = [];
    $bindParams = '';
    $bindValues = [];

    if ($title !== null) {
        $updateFields[] = "`title` = ?";
        $bindParams .= 's';
        $bindValues[] = $title;
    }
    if ($description !== null) {
        $updateFields[] = "`description` = ?";
        $bindParams .= 's';
        $bindValues[] = $description;
    }
    if ($coverImagePath !== null) { // Only update if a new image was uploaded
        $updateFields[] = "`cover_image_path` = ?";
        $bindParams .= 's';
        $bindValues[] = $coverImagePath;
    }

    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'message' => 'No fields provided for update.']);
        exit();
    }

    $sql = "UPDATE stories SET " . implode(', ', $updateFields) . " WHERE story_id = ?";
    $bindParams .= 'i'; // Add type for story_id
    $bindValues[] = $storyId;

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }

    // --- FIX START ---
    // Create an array of references for bind_param
    $bindRefs = [];
    $bindRefs[] = &$bindParams; 
    
    // Convert values to references and add to the bind array
    foreach ($bindValues as $key => $value) {
        $bindRefs[] = &$bindValues[$key];
    }
    
    // Call bind_param using call_user_func_array with the array of references
    call_user_func_array([$stmt, 'bind_param'], $bindRefs);
    // --- FIX END ---

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Story updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made or story ID not found.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update story: ' . $stmt->error]);
    }
    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>