<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'], $data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$id = $data['id'];
$content = $data['content'];

if (!preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

// Connect to DB
$mysqli = new mysqli("localhost", "NoteFlow_User", "Oracle@123", "NoteFlow");
if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $mysqli->connect_error]);
    exit;
}

// Insert or update content
$stmt = $mysqli->prepare("INSERT INTO notes (id, content) 
    VALUES (?, ?) 
    ON DUPLICATE KEY UPDATE content = VALUES(content), last_updated = CURRENT_TIMESTAMP");

$stmt->bind_param("ss", $id, $content);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}

$stmt->close();
$mysqli->close();
?>
