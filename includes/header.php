<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'QA Reporter'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <base href="<?php echo BASE_URL; ?>">
    <link rel="icon" href="<?php echo BASE_URL; ?>/images/favicon.ico">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <!-- Dashboard - accessible to all users -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url(); ?>">Dashboard</a>
                    </li>

                    <!-- Projects - admin only -->
                    <?php if ($user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('projects.php'); ?>">Projects</a>
                    </li>
                    <?php endif; ?>

                    <!-- Users - admin only -->
                    <?php if ($user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('users.php'); ?>">Users</a>
                    </li>
                    <?php endif; ?>

                    <!-- Reports - admin only -->
                    <?php if ($user_role === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Reports
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="reportsDropdown">
                            <li><a class="dropdown-item" href="<?php echo url('reports.php'); ?>">All Reports</a></li>
                            <li><a class="dropdown-item" href="<?php echo url('current_projects.php'); ?>">Current Projects</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Checklist - admin and qa_manager -->
                    <?php if (in_array($user_role, ['admin', 'qa_manager'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('manage_checklist.php'); ?>">Checklist</a>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="navbar-nav">
                    <!-- Auto-update - admin only -->
                    <?php if ($user_role === 'admin'): ?>
                    <a class="nav-link auto-update" href="<?php echo url('admin/check_update.php'); ?>">
                        <i class="bi bi-cloud-download"></i>
                    </a>
                    <?php endif; ?>

                    <span class="nav-item nav-link text-light">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a class="nav-link" href="<?php echo url('logout.php'); ?>">Logout</a>
                </div>

                <!-- Notifications dropdown -->
                <?php
                // Create a new connection just for notifications to avoid sync issues
                $notifications_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

                // Get unread notifications for the current user
                $user_id = $_SESSION['user_id'];
                $user_role = getUserRole();

                // Initialize notification count
                $notifications_count = 0;

                // Get unread notifications for current user (specific to this user or their role)
                if (isset($user_id) && isset($user_role)) {
                    $notifications_query = "SELECT COUNT(*) as count FROM notifications WHERE
                                           (user_id = ? OR (role = ? AND (user_id IS NULL OR user_id = 0)))
                                           AND is_read = 0";
                    $stmt = $notifications_conn->prepare($notifications_query);
                    if ($stmt) {
                        $stmt->bind_param("is", $user_id, $user_role);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result) {
                            $notifications_count = $result->fetch_assoc()['count'];
                        }
                        $stmt->close();
                    }
                }
                ?>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <?php if ($notifications_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $notifications_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="min-width: 300px; max-height: 400px; overflow-y: auto;">
                        <h6 class="dropdown-header">Notifications</h6>
                        <?php
                        $notifications_list_query = "SELECT * FROM notifications WHERE
                                                    (user_id = ? OR (role = ? AND (user_id IS NULL OR user_id = 0)))
                                                    ORDER BY created_at DESC LIMIT 10";
                        $stmt = $notifications_conn->prepare($notifications_list_query);
                        if ($stmt) {
                            $stmt->bind_param("is", $user_id, $user_role);
                            $stmt->execute();
                            $notifications = $stmt->get_result();

                            if ($notifications && $notifications->num_rows > 0) {
                                while ($notification = $notifications->fetch_assoc()) {
                                    $bg_class = $notification['is_read'] ? '' : 'bg-light';
                                    $type_class = 'text-' . $notification['type'];
                                    ?>
                                    <div class="dropdown-item <?php echo $bg_class; ?>" style="white-space: normal;">
                                        <div class="d-flex">
                                            <div class="me-2 <?php echo $type_class; ?>">
                                                <i class="bi bi-info-circle-fill"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <small class="text-muted"><?php echo date('M d, H:i', strtotime($notification['created_at'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                                ?>
                                <a class="dropdown-item text-center small text-primary" href="notifications.php">Show all notifications</a>
                                <?php
                            } else {
                                ?>
                                <div class="dropdown-item text-center">No notifications</div>
                                <?php
                            }
                            $stmt->close();
                        } else {
                            ?>
                            <div class="dropdown-item text-center">Error loading notifications</div>
                            <?php
                        }

                        // Close the notifications connection
                        $notifications_conn->close();
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>