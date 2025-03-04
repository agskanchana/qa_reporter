<?php

require_once 'includes/config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_role = getUserRole();
$user_id = $_SESSION['user_id'];

// Handle QA Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user_role, ['admin', 'qa_manager'])) {
    if (isset($_POST['assign_qa']) && isset($_POST['project_id']) && isset($_POST['qa_user_id'])) {
        $project_id = (int)$_POST['project_id'];
        $qa_user_id = (int)$_POST['qa_user_id'];

        // Check if project is already assigned
        $check_stmt = $conn->prepare("SELECT id FROM qa_assignments WHERE project_id = ?");
        $check_stmt->bind_param("i", $project_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result();

        if ($existing->num_rows > 0) {
            // Update existing assignment
            $stmt = $conn->prepare("UPDATE qa_assignments SET qa_user_id = ?, assigned_by = ?, assigned_at = NOW() WHERE project_id = ?");
            $stmt->bind_param("iii", $qa_user_id, $user_id, $project_id);
        } else {
            // Create new assignment
            $stmt = $conn->prepare("INSERT INTO qa_assignments (project_id, qa_user_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $project_id, $qa_user_id, $user_id);
        }
        $stmt->execute();

        header("Location: dashboard.php");
        exit();
    }
}

// Get all QA reporters for assignment dropdown
$qa_reporters = [];
if (in_array($user_role, ['admin', 'qa_manager'])) {
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE role IN ('qa_reporter', 'qa_manager', 'admin') ORDER BY username");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $qa_reporters[] = $row;
    }
}

// Get projects based on user role
$projects = [];
$unassigned_projects = [];

if ($user_role === 'qa_reporter') {
    // Get only assigned projects with page_creation_qa status for this QA reporter
    $query = "SELECT p.*,
              u.username as webmaster_name,
              qa.id as assignment_id,
              COALESCE(qa_user.username, 'None') as assigned_qa_username
              FROM projects p
              LEFT JOIN users u ON p.webmaster_id = u.id
              INNER JOIN qa_assignments qa ON p.id = qa.project_id
              LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
              WHERE qa.qa_user_id = ?
              AND p.current_status LIKE '%page_creation_qa%'
              ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
elseif (in_array($user_role, ['admin', 'qa_manager'])) {
    // Get all projects and separate assigned/unassigned
    $query = "SELECT p.*, u.username as webmaster_name,
              qa.id as assignment_id, IFNULL(qa_user.username, 'None') as assigned_qa_username
              FROM projects p
              LEFT JOIN users u ON p.webmaster_id = u.id
              LEFT JOIN qa_assignments qa ON p.id = qa.project_id
              LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
              ORDER BY p.created_at DESC";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {
        if ($row['assignment_id'] === null) {
            $unassigned_projects[] = $row;
        } else {
            $projects[] = $row;
        }
    }
}
elseif ($user_role === 'webmaster') {
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM projects WHERE webmaster_id = ?";
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_projects = $stmt->get_result()->fetch_assoc()['total'];

    // Pagination setup
    $items_per_page = 10;
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $total_pages = ceil($total_projects / $items_per_page);
    $offset = ($current_page - 1) * $items_per_page;

    // Get paginated projects
    $query = "SELECT p.*,
              u.username as webmaster_name,
              qa.id as assignment_id,
              COALESCE(qa_user.username, 'None') as assigned_qa_username
              FROM projects p
              LEFT JOIN users u ON p.webmaster_id = u.id
              LEFT JOIN qa_assignments qa ON p.id = qa.project_id
              LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
              WHERE p.webmaster_id = ?
              ORDER BY p.created_at DESC
              LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $items_per_page, $offset);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
else {
    // For webmaster, get their projects
    $query = "SELECT p.*, u.username as webmaster_name,
              qa.id as assignment_id, COALESCE(qa_user.username, 'None') as assigned_qa_username
              FROM projects p
              LEFT JOIN users u ON p.webmaster_id = u.id
              LEFT JOIN qa_assignments qa ON p.id = qa.project_id
              LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
              WHERE p.webmaster_id = ?
              ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Replace the webmaster projects query with this
if ($user_role === 'admin') {
    $query = "SELECT
                u.id as webmaster_id,
                COALESCE(u.username, 'Deleted User') as webmaster_name,
                p.id as project_id,
                p.name as project_name,
                p.current_status
              FROM users u
              LEFT JOIN projects p ON u.id = p.webmaster_id
                AND p.current_status != 'completed'
              WHERE u.role = 'webmaster'
              ORDER BY u.username, p.created_at DESC";

    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $webmaster_id = $row['webmaster_id'];
        if (!isset($webmaster_projects[$webmaster_id])) {
            $webmaster_projects[$webmaster_id] = [
                'name' => $row['webmaster_name'],
                'projects' => []
            ];
        }
        if ($row['project_id']) {
            $webmaster_projects[$webmaster_id]['projects'][] = [
                'id' => $row['project_id'],
                'name' => $row['project_name'],
                'status' => $row['current_status']
            ];
        }
    }
}

// Add after the webmaster_projects query

$qa_reporter_projects = [];
if (in_array($user_role, ['admin', 'qa_manager'])) {
    $query = "SELECT
                u.id as qa_id,
                COALESCE(u.username, 'Deleted User') as qa_name,
                p.id as project_id,
                p.name as project_name,
                p.current_status
              FROM users u
              LEFT JOIN qa_assignments qa ON u.id = qa.qa_user_id
              LEFT JOIN projects p ON qa.project_id = p.id
              WHERE (u.role = 'qa_reporter' OR u.role = 'qa_manager' OR u.role = 'admin' OR u.id IS NULL)
              AND (p.current_status != 'completed' OR p.current_status IS NULL)
              ORDER BY COALESCE(u.username, 'Deleted User'), p.created_at DESC";

    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $qa_id = $row['qa_id'];
        if (!isset($qa_reporter_projects[$qa_id])) {
            $qa_reporter_projects[$qa_id] = [
                'name' => $row['qa_name'],
                'projects' => []
            ];
        }
        if ($row['project_id']) {
            $qa_reporter_projects[$qa_id]['projects'][] = [
                'id' => $row['project_id'],
                'name' => $row['project_name'],
                'status' => $row['current_status']
            ];
        }
    }
}

// Add this after user role check, before the header inclusion

if (in_array($user_role, ['admin', 'qa_manager'])) {
    // Get all projects grouped by status
    $query = "SELECT p.*,
              u.username as webmaster_name,
              qa.id as assignment_id,
              IFNULL(qa_user.username, 'Unassigned') as assigned_qa_username,
              p.current_status
              FROM projects p
              LEFT JOIN users u ON p.webmaster_id = u.id
              LEFT JOIN qa_assignments qa ON p.id = qa.project_id
              LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
              WHERE p.current_status IS NOT NULL
              AND p.current_status != ''
              ORDER BY p.created_at DESC";

    $result = $conn->query($query);

    // Initialize arrays for different project groups
    $wp_conversion_qa_projects = [];
    $page_creation_qa_projects = [];
    $golive_qa_projects = [];
    $completed_projects = [];

    while ($row = $result->fetch_assoc()) {
        $statuses = !empty($row['current_status']) ? explode(',', $row['current_status']) : [];

        // Check each status and add project to appropriate groups
        if (in_array('wp_conversion_qa', $statuses)) {
            $wp_conversion_qa_projects[] = array_merge($row, ['display_status' => 'wp_conversion_qa']);
        }

        if (in_array('page_creation_qa', $statuses)) {
            $page_creation_qa_projects[] = array_merge($row, ['display_status' => 'page_creation_qa']);
        }

        if (in_array('golive_qa', $statuses)) {
            $golive_qa_projects[] = array_merge($row, ['display_status' => 'golive_qa']);
        }

        if ($row['current_status'] === 'completed') {
            $completed_projects[] = $row;
        }
    }
}

// Add after existing queries, before require_once 'includes/header.php'

// Get most failing checklist items by stage
$failing_items_query = "SELECT
    ci.title,
    ci.stage,
    COUNT(pcs.id) as fail_count
FROM checklist_items ci
JOIN project_checklist_status pcs ON ci.id = pcs.checklist_item_id
WHERE pcs.status = 'failed'
    AND ci.is_archived = 0
    AND pcs.is_archived = 0
GROUP BY ci.id, ci.title, ci.stage
HAVING COUNT(pcs.id) > 0
ORDER BY ci.stage, fail_count DESC";

$failing_items_result = $conn->query($failing_items_query);
$failing_items = [];
while ($row = $failing_items_result->fetch_assoc()) {
    $failing_items[$row['stage']][] = $row;
}

// Add this query where the other queries are (before the require_once 'includes/header.php')

if (in_array($user_role, ['admin', 'qa_manager'])) {
    // Get webmaster projects that are in basic stages (not QA or completed)
    $webmaster_active_query = "SELECT p.*,
        u.username as webmaster_name,
        qa.id as assignment_id,
        IFNULL(qa_user.username, 'Unassigned') as assigned_qa_username
    FROM projects p
    LEFT JOIN users u ON p.webmaster_id = u.id
    LEFT JOIN qa_assignments qa ON p.id = qa.project_id
    LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
    WHERE p.current_status IN ('wp_conversion', 'page_creation', 'golive')
    ORDER BY p.created_at DESC";

    $result = $conn->query($webmaster_active_query);
    $webmaster_active_projects = $result->fetch_all(MYSQLI_ASSOC);
}

require_once 'includes/header.php';

// Add this code right after the header include and before the container div
// Get notifications for the current user or role
$notifications = [];
$notifications_query = "SELECT * FROM notifications WHERE
                        (user_id = ? OR role = ?)
                        AND is_read = 0
                        ORDER BY created_at DESC
                        LIMIT 5";
$stmt = $conn->prepare($notifications_query);
$stmt->bind_param("is", $user_id, $user_role);
$stmt->execute();
$notifications_result = $stmt->get_result();

while ($row = $notifications_result->fetch_assoc()) {
    $notifications[] = $row;
}

// Add role-specific dynamic notifications
if ($user_role === 'webmaster') {
    // Get projects with approaching deadlines (within 7 days)
    $deadline_query = "SELECT id, name, project_deadline, wp_conversion_deadline
                      FROM projects
                      WHERE webmaster_id = ?
                      AND (
                          (project_deadline IS NOT NULL AND project_deadline <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
                          OR
                          (wp_conversion_deadline IS NOT NULL AND wp_conversion_deadline <= DATE_ADD(CURDATE(), INTERVAL 3 DAY))
                      )";
    $stmt = $conn->prepare($deadline_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $deadline_result = $stmt->get_result();

    while ($project = $deadline_result->fetch_assoc()) {
        if (!empty($project['wp_conversion_deadline']) && strtotime($project['wp_conversion_deadline']) <= strtotime('+3 days')) {
            $days = ceil((strtotime($project['wp_conversion_deadline']) - time()) / (60 * 60 * 24));
            $notifications[] = [
                'message' => 'WP Conversion deadline approaching for project: ' . htmlspecialchars($project['name']) .
                             ' (' . date('M j, Y', strtotime($project['wp_conversion_deadline'])) . ', ' .
                             ($days <= 0 ? 'today' : $days . ' day' . ($days > 1 ? 's' : '') . ' left') . ')',
                'type' => 'warning',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        if (!empty($project['project_deadline']) && strtotime($project['project_deadline']) <= strtotime('+7 days')) {
            $days = ceil((strtotime($project['project_deadline']) - time()) / (60 * 60 * 24));
            $notifications[] = [
                'message' => 'Project deadline approaching for: ' . htmlspecialchars($project['name']) .
                             ' (' . date('M j, Y', strtotime($project['project_deadline'])) . ', ' .
                             ($days <= 0 ? 'today' : $days . ' day' . ($days > 1 ? 's' : '') . ' left') . ')',
                'type' => 'danger',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
    }

    // Get projects with failed checklist items
    $failed_items_query = "SELECT p.id, p.name, COUNT(pcs.id) as failed_count
                          FROM projects p
                          JOIN project_checklist_status pcs ON p.id = pcs.project_id
                          WHERE p.webmaster_id = ?
                          AND pcs.status = 'failed'
                          GROUP BY p.id
                          HAVING failed_count > 0";
    $stmt = $conn->prepare($failed_items_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $failed_result = $stmt->get_result();

    while ($project = $failed_result->fetch_assoc()) {
        $notifications[] = [
            'message' => $project['failed_count'] . ' item' . ($project['failed_count'] > 1 ? 's' : '') .
                         ' need attention in project: ' . htmlspecialchars($project['name']),
            'type' => 'danger',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
} elseif ($user_role === 'qa_reporter') {
    // Get newly assigned projects
    $new_assignments_query = "SELECT p.id, p.name, qa.assigned_at
                             FROM projects p
                             JOIN qa_assignments qa ON p.id = qa.project_id
                             WHERE qa.qa_user_id = ?
                             AND qa.assigned_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                             ORDER BY qa.assigned_at DESC";
    $stmt = $conn->prepare($new_assignments_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $assignments_result = $stmt->get_result();

    while ($project = $assignments_result->fetch_assoc()) {
        $notifications[] = [
            'message' => 'New project assigned for QA: ' . htmlspecialchars($project['name']) .
                         ' (' . date('M j g:i A', strtotime($project['assigned_at'])) . ')',
            'type' => 'info',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
} elseif (in_array($user_role, ['admin', 'qa_manager'])) {
    // Get unassigned QA projects
    $unassigned_query = "SELECT COUNT(*) as count FROM projects p
                        LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                        WHERE qa.id IS NULL
                        AND p.current_status LIKE '%_qa%'";
    $unassigned_result = $conn->query($unassigned_query);
    $unassigned_count = $unassigned_result->fetch_assoc()['count'];

    if ($unassigned_count > 0) {
        $notifications[] = [
            'message' => $unassigned_count . ' project' . ($unassigned_count > 1 ? 's' : '') .
                         ' need QA assignment',
            'type' => 'warning',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    // Get projects with approaching deadlines (within 7 days)
    $deadline_query = "SELECT id, name, project_deadline, webmaster_id,
                      (SELECT username FROM users WHERE id = webmaster_id) as webmaster_name
                      FROM projects
                      WHERE project_deadline IS NOT NULL
                      AND project_deadline <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    $deadline_result = $conn->query($deadline_query);

    while ($project = $deadline_result->fetch_assoc()) {
        $days = ceil((strtotime($project['project_deadline']) - time()) / (60 * 60 * 24));
        $webmaster_name = htmlspecialchars($project['webmaster_name'] ?: 'Unassigned');

        $notifications[] = [
            'message' => 'Project deadline approaching: ' . htmlspecialchars($project['name']) .
                         ' (' . date('M j, Y', strtotime($project['project_deadline'])) . ', ' .
                         ($days <= 0 ? 'today' : $days . ' day' . ($days > 1 ? 's' : '') . ' left') . ') - ' .
                         'Webmaster: ' . $webmaster_name,
            'type' => 'danger',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}

// Sort notifications by date (newest first)
usort($notifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
?>

<div class="container-fluid">


    <div class="row">
        <!-- Rest of your dashboard content -->
    <div class="row">


            <main class="col-md-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard </h1>
                    </div>
                <div class="row">

        <div class="col-md-3">

         <!-- Add the notification area here, right after the container-fluid -->
    <?php if (!empty($notifications)): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-bell"></i> Notifications
                        <span class="badge bg-danger ms-2"><?php echo count($notifications); ?></span>
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseNotifications">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                </div>
                <div class="card-body" id="notificationsBody">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="alert alert-<?php echo $notification['type']; ?> d-flex align-items-center mb-2">
                            <div>
                                <?php if ($notification['type'] === 'danger'): ?>
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php elseif ($notification['type'] === 'warning'): ?>
                                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                                <?php elseif ($notification['type'] === 'success'): ?>
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                <?php else: ?>
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                <?php endif; ?>
                                <?php echo $notification['message']; ?>
                            </div>
                            <?php if (isset($notification['id'])): ?>
                            <button type="button" class="btn-close ms-auto mark-read" data-id="<?php echo $notification['id']; ?>"></button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
        <?php if($user_role === 'webmaster'):?>
            <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Most Failing Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="accordion" id="failingItemsAccordion">
                            <?php
                            $stages = ['wp_conversion' => 'WP Conversion',
                                      'page_creation' => 'Page Creation',
                                      'golive' => 'Golive'];

                            foreach ($stages as $stage_key => $stage_name):
                                $items = $failing_items[$stage_key] ?? [];
                            ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#failing<?php echo $stage_key; ?>">
                                            <?php echo $stage_name; ?>
                                            <?php if (!empty($items)): ?>
                                                <span class="badge bg-danger ms-2">
                                                    <?php echo count($items); ?>
                                                </span>
                                            <?php endif; ?>
                                        </button>
                                    </h2>
                                    <div id="failing<?php echo $stage_key; ?>"
                                         class="accordion-collapse collapse"
                                         data-bs-parent="#failingItemsAccordion">
                                        <div class="accordion-body p-2">
                                            <?php if (empty($items)): ?>
                                                <p class="text-muted mb-0">No failing items</p>
                                            <?php else: ?>
                                                <?php foreach ($items as $item): ?>
                                                    <div class="card mb-2">
                                                        <div class="card-body p-2">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <small><?php echo htmlspecialchars($item['title']); ?></small>
                                                                <span class="badge bg-danger">
                                                                    <?php echo $item['fail_count']; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
        <?php endif;?>
        <?php if ($user_role === 'admin'): ?>
        <div class="card mb-4">
                <div class="card-header ">
                    <h5 class="card-title mb-0">Webmaster Projects</h5>
                </div>
                <div class="card-body p-0">
                    <div class="accordion" id="webmasterAccordion">
                        <?php foreach ($webmaster_projects as $webmaster_id => $webmaster): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#webmaster<?php echo $webmaster_id; ?>">
                                        <?php echo htmlspecialchars($webmaster['name']); ?>
                                        <span class="badge bg-<?php echo count($webmaster['projects']) === 0 ? 'danger' : 'primary'; ?> ms-2">
                                            <?php echo count($webmaster['projects']); ?>
                                        </span>
                                    </button>
                                </h2>
                                <div id="webmaster<?php echo $webmaster_id; ?>"
                                     class="accordion-collapse collapse"
                                     data-bs-parent="#webmasterAccordion">
                                    <div class="accordion-body p-2">
                                        <?php if (empty($webmaster['projects'])): ?>
                                            <p class="text-muted mb-0">No active projects</p>
                                        <?php else: ?>
                                            <?php foreach ($webmaster['projects'] as $project): ?>
                                                <div class="card mb-2">
                                                    <div class="card-body p-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small><?php echo htmlspecialchars($project['name']); ?></small>
                                                            <span class="badge bg-<?php
                                                                $status_class = 'secondary'; // Default value
                                                                if ($project['status'] === 'wp_conversion') {
                                                                    $status_class = 'info';
                                                                } elseif ($project['status'] === 'page_creation') {
                                                                    $status_class = 'warning';
                                                                } elseif ($project['status'] === 'golive') {
                                                                    $status_class = 'primary';
                                                                }
                                                                echo $status_class;
                                                            ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php
            endif;

            if (in_array($user_role, ['admin', 'qa_manager'])): ?>

   <div class="card ">
        <div class="card-header">
            <h5 class="card-title mb-0">QA Reporter Projects</h5>
        </div>
        <div class="card-body p-0">
            <div class="accordion" id="qaReporterAccordion">
                <?php foreach ($qa_reporter_projects as $qa_id => $qa): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#qa<?php echo $qa_id; ?>">
                                <?php echo htmlspecialchars($qa['name']); ?>
                                <span class="badge bg-<?php echo count($qa['projects']) === 0 ? 'danger' : 'success'; ?> ms-2">
                                    <?php echo count($qa['projects']); ?>
                                </span>
                            </button>
                        </h2>
                        <div id="qa<?php echo $qa_id; ?>"
                             class="accordion-collapse collapse"
                             data-bs-parent="#qaReporterAccordion">
                            <div class="accordion-body p-2">
                                <?php if (empty($qa['projects'])): ?>
                                    <p class="text-muted mb-0">No active projects</p>
                                <?php else: ?>
                                    <?php foreach ($qa['projects'] as $project): ?>
                                        <div class="card mb-2">
                                            <div class="card-body p-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small><?php echo htmlspecialchars($project['name']); ?></small>
                                                    <span class="badge bg-<?php
                                                        $status_class = 'secondary'; // Default value
                                                        if ($project['status'] === 'wp_conversion') {
                                                            $status_class = 'info';
                                                        } elseif ($project['status'] === 'page_creation') {
                                                            $status_class = 'warning';
                                                        } elseif ($project['status'] === 'golive') {
                                                            $status_class = 'primary';
                                                        }
                                                        echo $status_class;
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
        </div>


        <main class="col-md-9">

<?php if ($user_role === 'webmaster'): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h4>My Projects</h4>
        </div>
        <div class="card-body">
            <?php if (!empty($projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Assigned QA</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            ['wp_conversion'];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['assigned_qa_username']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php else: ?>
                <p class="text-muted">No projects found</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>




                <?php if (in_array($user_role, ['admin', 'qa_manager'])): ?>
    <!-- WP Conversion QA Projects -->
    <div class="card mb-4">
        <div class="card-header">
            <h4>WP Conversion QA Pending
                <span class="badge bg-primary"><?php echo count($wp_conversion_qa_projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (!empty($wp_conversion_qa_projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Assigned QA</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wp_conversion_qa_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            [];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Deleted User'); ?></td>
                                    <td><?php echo htmlspecialchars($project['assigned_qa_username']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No projects pending WP Conversion QA</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Page Creation QA Projects -->
    <div class="card mb-4">
        <div class="card-header">
            <h4>Page Creation QA Pending
                <span class="badge bg-primary"><?php echo count($page_creation_qa_projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (!empty($page_creation_qa_projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Assigned QA</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($page_creation_qa_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            [];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Deleted User'); ?></td>
                                    <td>
                                        <?php if ($project['assigned_qa_username'] === 'Unassigned'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                                <select name="qa_user_id" class="form-select form-select-sm d-inline-block w-auto" required>
                                                    <option value="">Assign QA</option>
                                                    <?php foreach ($qa_reporters as $qa): ?>
                                                        <option value="<?php echo $qa['id']; ?>">
                                                            <?php echo htmlspecialchars($qa['username']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="assign_qa" class="btn btn-primary btn-sm">
                                                    Assign
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($project['assigned_qa_username']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No projects pending Page Creation QA</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Golive QA Projects -->
    <div class="card mb-4">
        <div class="card-header">
            <h4>Golive QA Pending
                <span class="badge bg-primary"><?php echo count($golive_qa_projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (!empty($golive_qa_projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Assigned QA</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($golive_qa_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            [];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Deleted User'); ?></td>
                                    <td><?php echo htmlspecialchars($project['assigned_qa_username']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No projects pending Golive QA</p>
            <?php endif; ?>
        </div>
    </div>

     <!-- Projects with webmasters  -->
     <div class="card mb-4">
        <div class="card-header">
            <h4>Webmaster Projects
                <span class="badge bg-primary"><?php echo count($webmaster_active_projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (!empty($webmaster_active_projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($webmaster_active_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            $status_class = 'secondary'; // Default value
                                            $status = $project['current_status'] ?? '';
                                            if ($status === 'wp_conversion') {
                                                $status_class = 'info';
                                            } elseif ($status === 'page_creation') {
                                                $status_class = 'warning';
                                            } elseif ($status === 'golive') {
                                                $status_class = 'primary';
                                            }
                                            echo $status_class;
                                        ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $project['current_status'] ?? 'Unknown')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No active webmaster projects</p>
            <?php endif; ?>
        </div>
    </div>
    <!-- Completed Projects with Pagination -->
    <div class="card">
        <div class="card-header">
            <h4>Completed Projects
                <span class="badge bg-success"><?php echo count($completed_projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php
            $items_per_page = 10;
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $total_pages = ceil(count($completed_projects) / $items_per_page);
            $offset = ($current_page - 1) * $items_per_page;
            $paginated_projects = array_slice($completed_projects, $offset, $items_per_page);
            ?>

            <?php if (!empty($paginated_projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Assigned QA</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            [];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Deleted User'); ?></td>
                                    <td><?php echo htmlspecialchars($project['assigned_qa_username']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php else: ?>
                <p class="text-muted">No completed projects</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>


<?php if ($user_role === 'qa_reporter'): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h4>My Assigned Projects (Page Creation QA)
                <span class="badge bg-primary"><?php echo count($projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (!empty($projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            [];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Deleted User'); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No projects assigned for Page Creation QA</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>


                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle notifications collapse
            const collapseBtn = document.getElementById('collapseNotifications');
            const notificationsBody = document.getElementById('notificationsBody');

            if (collapseBtn) {
                collapseBtn.addEventListener('click', function() {
                    if (notificationsBody.style.display === 'none') {
                        notificationsBody.style.display = 'block';
                        collapseBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
                    } else {
                        notificationsBody.style.display = 'none';
                        collapseBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
                    }
                });
            }

            // Mark notifications as read
            const markReadButtons = document.querySelectorAll('.mark-read');
            markReadButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const notificationId = this.getAttribute('data-id');
                    const notificationElement = this.closest('.alert');

                    // Send AJAX request to mark as read
                    fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + notificationId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            notificationElement.remove();

                            // Update badge count
                            const badge = document.querySelector('.card-header .badge');
                            if (badge) {
                                let count = parseInt(badge.textContent);
                                badge.textContent = count - 1;

                                // If no more notifications, hide the card
                                if (count - 1 <= 0) {
                                    document.querySelector('.card').style.display = 'none';
                                }
                            }
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>