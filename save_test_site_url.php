<?php
require_once 'includes/config.php';

if (!isLoggedIn() || getUserRole() !== 'webmaster') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Set header for JSON response
header('Content-Type: application/json');

// Get JSON data from request
$json_data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($json_data['project_id']) || !isset($json_data['test_site_url'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$project_id = (int)$json_data['project_id'];
$test_site_url = $conn->real_escape_string($json_data['test_site_url']);

// Validate URL format
if (!filter_var($test_site_url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid URL format']);
    exit;
}

// Check if user is assigned to this project
$query = "SELECT id FROM projects WHERE id = ? AND webmaster_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $project_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'You are not authorized to update this project']);
    exit;
}

// Update the project with the test site URL
$update_query = "UPDATE projects SET test_site_link = ? WHERE id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("si", $test_site_url, $project_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save test site URL']);
}
?>