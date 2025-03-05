<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();
$error = '';
$success = '';

// Mark all notifications as read
if (isset($_GET['mark_read'])) {
    $update_query = "UPDATE notifications SET is_read = 1
                     WHERE user_id = ? OR role = ?";
    $stmt = $conn->prepare($update_query);
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $user_role);

        if ($stmt->execute()) {
            $success = "All notifications marked as read.";
        }
        $stmt->close();
    }

    header("Location: notifications.php");
    exit;
}

// Get all notifications for current user/role
$notif_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check if user_role is one of the valid enum values
$valid_roles = ['admin', 'qa_manager', 'qa_reporter', 'webmaster'];
$role_is_valid = in_array($user_role, $valid_roles);

// Get notifications for the current user - modify the query based on role validity
if ($role_is_valid) {
    $notifications_query = "SELECT * FROM notifications
                          WHERE user_id = ? OR (role = ? AND (user_id IS NULL OR user_id = 0))
                          ORDER BY created_at DESC";
    $stmt = $notif_conn->prepare($notifications_query);
    $stmt->bind_param("is", $user_id, $user_role);
} else {
    // If role isn't valid for the enum, only query by user_id
    $notifications_query = "SELECT * FROM notifications
                          WHERE user_id = ?
                          ORDER BY created_at DESC";
    $stmt = $notif_conn->prepare($notifications_query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$notifications = $stmt->get_result();
$num_notifications = $notifications ? $notifications->num_rows : 0;

// Count unread notifications - use the same logic for role
if ($role_is_valid) {
    $unread_query = "SELECT COUNT(*) as unread FROM notifications
                    WHERE (user_id = ? OR (role = ? AND (user_id IS NULL OR user_id = 0))) AND is_read = 0";
    $stmt = $notif_conn->prepare($unread_query);
    $stmt->bind_param("is", $user_id, $user_role);
} else {
    $unread_query = "SELECT COUNT(*) as unread FROM notifications
                    WHERE user_id = ? AND is_read = 0";
    $stmt = $notif_conn->prepare($unread_query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result ? $result->fetch_assoc()['unread'] : 0;
$stmt->close();

// Mark displayed notifications as read
$mark_read_query = "UPDATE notifications SET is_read = 1
                    WHERE (user_id = ? OR (role = ? AND (user_id IS NULL OR user_id = 0))) AND is_read = 0";
$stmt = $notif_conn->prepare($mark_read_query);
$stmt->bind_param("is", $user_id, $user_role);
$stmt->execute();
$stmt->close();

// Before closing the connection, let's store notifications in a PHP array
$all_notifications = [];
if ($notifications && $notifications->num_rows > 0) {
    while ($notification = $notifications->fetch_assoc()) {
        $all_notifications[] = $notification;
    }
}

$notif_conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4>Your Notifications</h4>
                <div>
                    <?php if ($unread_count > 0): ?>
                    <a href="notifications.php?mark_read=1" class="btn btn-sm btn-outline-primary">
                        Mark all as read
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($all_notifications)): ?>
                    <div class="list-group">
                    <?php foreach ($all_notifications as $notification): ?>
                        <div class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <p class="mb-1">
                                    <?php
                                    // Display colored badge based on notification type
                                    $type = $notification['type'] ?? 'info';
                                    $badge_class = '';
                                    switch($type) {
                                        case 'warning': $badge_class = 'bg-warning text-dark'; break;
                                        case 'success': $badge_class = 'bg-success'; break;
                                        case 'danger': $badge_class = 'bg-danger'; break;
                                        default: $badge_class = 'bg-info'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?> me-2">
                                        <?php echo ucfirst($type); ?>
                                    </span>
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                <?php if (!$notification['is_read']): ?>
                                    <span class="badge bg-primary">New</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                <?php echo date('F j, Y, g:i a', strtotime($notification['created_at'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center p-3">
                        <i class="bi bi-bell-slash" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="mt-3">You don't have any notifications yet.</p>
                        <p>Click "Create Test Notification" above to create a test notification.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>