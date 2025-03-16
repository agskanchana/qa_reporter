<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and has admin permissions
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if project name exists
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_name'])) {
    $project_name = trim($_POST['project_name']);

    // Optional: allow excluding the current project in edit mode
    $exclude_id = isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : 0;

    $query = "SELECT COUNT(*) as count FROM projects WHERE name = ?";
    $params = [$project_name];

    if ($exclude_id > 0) {
        $query .= " AND id != ?";
        $params[] = $exclude_id;
    }

    $stmt = $conn->prepare($query);

    if ($exclude_id > 0) {
        $stmt->bind_param("si", $project_name, $exclude_id);
    } else {
        $stmt->bind_param("s", $project_name);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];

    header('Content-Type: application/json');
    echo json_encode(['exists' => $count > 0]);
    exit();
}

header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid request']);
exit();