<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $notification_id = (int)$_POST['id'];
    $user_id = $_SESSION['user_id'];

    // Make sure the notification belongs to this user or their role
    $user_role = getUserRole();

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1
                           WHERE id = ? AND (user_id = ? OR role = ?)");
    $stmt->bind_param("iis", $notification_id, $user_id, $user_role);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No notification found or not authorized']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>