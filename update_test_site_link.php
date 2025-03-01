<?php
require_once 'includes/config.php';

if (!isLoggedIn() || getUserRole() !== 'webmaster') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)$_POST['project_id'];
    $test_site_link = $conn->real_escape_string($_POST['test_site_link']);

    // Validate URL
    if (!filter_var($test_site_link, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => 'Please enter a valid URL']);
        exit();
    }

    // Update the test_site_link in the projects table
    $query = "UPDATE projects SET test_site_link = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $test_site_link, $project_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update test site link']);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
exit();
?>