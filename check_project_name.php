<?php
require_once 'includes/config.php';

if (isset($_POST['project_name'])) {
    $project_name = $conn->real_escape_string($_POST['project_name']);
    $query = "SELECT COUNT(*) as count FROM projects WHERE name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $project_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];

    echo json_encode(['exists' => $count > 0]);
}