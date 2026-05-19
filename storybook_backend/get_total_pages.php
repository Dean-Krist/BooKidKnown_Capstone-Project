<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "storybook_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$story_id = isset($_GET['story_id']) ? intval($_GET['story_id']) : 0;

$sql = "SELECT COUNT(*) AS total_pages FROM pages WHERE story_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $story_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$count = $row['total_pages'];

echo $count;

$stmt->close();
$conn->close();
?>