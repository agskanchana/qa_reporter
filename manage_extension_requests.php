<?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/manage_extension_requests.php
require_once 'includes/config.php';

// Only allow admins and qa_managers
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Update request status
        $stmt = $conn->prepare("UPDATE deadline_extension_requests
                               SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                               WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $request_id);

        if ($stmt->execute()) {
            // If approved, update the project deadline
            if ($status === 'approved') {
                // Get request details
                $req_stmt = $conn->prepare("SELECT project_id, deadline_type, requested_deadline FROM deadline_extension_requests WHERE id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                // Update the appropriate deadline
                $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';
                $update_stmt = $conn->prepare("UPDATE projects SET $deadline_field = ? WHERE id = ?");
                $update_stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
                $update_stmt->execute();

                // Get project and webmaster info for notification
                $info_stmt = $conn->prepare("SELECT p.name, u.id as webmaster_id, u.username FROM projects p
                                            JOIN users u ON p.webmaster_id = u.id
                                            WHERE p.id = ?");
                $info_stmt->bind_param("i", $request['project_id']);
                $info_stmt->execute();
                $project_info = $info_stmt->get_result()->fetch_assoc();

                // Create notification for webmaster
                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$project_info['name']}' has been approved.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'success')");
                $notif_stmt->bind_param("is", $project_info['webmaster_id'], $message);
                $notif_stmt->execute();
            } else {
                // If denied, notify webmaster
                $req_stmt = $conn->prepare("SELECT der.project_id, der.deadline_type, p.name, u.id as webmaster_id
                                          FROM deadline_extension_requests der
                                          JOIN projects p ON der.project_id = p.id
                                          JOIN users u ON p.webmaster_id = u.id
                                          WHERE der.id = ?");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();

                $deadline_label = ($request['deadline_type'] === 'wp_conversion') ? 'WP Conversion' : 'Project';
                $message = "Your {$deadline_label} deadline extension for project '{$request['name']}' has been denied.";

                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'danger')");
                $notif_stmt->bind_param("is", $request['webmaster_id'], $message);
                $notif_stmt->execute();
            }

            $_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
        } else {
            $_SESSION['error'] = "Failed to update extension request.";
        }
    }

    header("Location: manage_extension_requests.php");
    exit();
}

// Get all pending extension requests
$query = "SELECT er.*,
          p.name as project_name,
          wb.username as requested_by_name,
          CASE
              WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
              ELSE 'Project'
          END as deadline_type_name
          FROM deadline_extension_requests er
          JOIN projects p ON er.project_id = p.id
          JOIN users wb ON er.requested_by = wb.id
          WHERE er.status = 'pending'
          ORDER BY er.created_at ASC";

$result = $conn->query($query);
$pending_requests = [];

while ($row = $result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Get past requests (approved/denied)
$history_query = "SELECT er.*,
                 p.name as project_name,
                 wb.username as requested_by_name,
                 rv.username as reviewed_by_name,
                 CASE
                     WHEN er.deadline_type = 'wp_conversion' THEN 'WP Conversion'
                     ELSE 'Project'
                 END as deadline_type_name
                 FROM deadline_extension_requests er
                 JOIN projects p ON er.project_id = p.id
                 JOIN users wb ON er.requested_by = wb.id
                 LEFT JOIN users rv ON er.reviewed_by = rv.id
                 WHERE er.status != 'pending'
                 ORDER BY er.reviewed_at DESC
                 LIMIT 20";

$history_result = $conn->query($history_query);
$past_requests = [];

while ($row = $history_result->fetch_assoc()) {
    $past_requests[] = $row;
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Manage Deadline Extension Requests</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Requests (<?php echo count($pending_requests); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted mb-0">No pending deadline extension requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Deadline Type</th>
                                        <th>Requested By</th>
                                        <th>Original Deadline</th>
                                        <th>Requested Deadline</th>
                                        <th>Extension</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $request['project_id']; ?>">
                                                    <?php echo htmlspecialchars($request['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $request['deadline_type_name']; ?></td>
                                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['original_deadline'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['requested_deadline'])); ?></td>
                                            <td>
                                                <?php
                                                    $days = (strtotime($request['requested_deadline']) - strtotime($request['original_deadline'])) / (60 * 60 * 24);
                                                    echo $days . ' days';
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#reasonModal<?php echo $request['id']; ?>">
                                                    View Reason
                                                </button>

                                                <!-- Reason Modal -->
                                                <div class="modal fade" id="reasonModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Extension Reason</h5>
                                                                <button
