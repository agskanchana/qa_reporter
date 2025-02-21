<?php
require_once 'includes/config.php';

if (isset($_POST['email'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $query = "SELECT COUNT(*) as count FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];

    echo json_encode(['exists' => $count > 0]);
}