<?php
require_once 'includes/config.php';

if (isset($_POST['username'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $query = "SELECT COUNT(*) as count FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];

    echo json_encode(['exists' => $count > 0]);
}