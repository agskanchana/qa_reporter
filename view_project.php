<?php
// view_project.php
require_once 'includes/config.php';
// require_once 'functions.php';
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$project_id = (int)$_GET['id'];
$user_role = getUserRole();

// Get project details first, before trying to use $project
$query = "SELECT p.*, u.username as webmaster_name,
          COALESCE(p.current_status, 'wp_conversion') as current_status,
          p.project_deadline,
          p.wp_conversion_deadline,
          p.ticket_link,
          p.gp_link,
          p.test_site_link AS test_site_url,
          p.live_site_link AS live_site_url
          FROM projects p
          LEFT JOIN users u ON p.webmaster_id = u.id
          WHERE p.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    header("Location: dashboard.php");
    exit();
}

// Get current status and prepare status flags that will be used later
$statuses = !empty($project['current_status']) ? explode(',', $project['current_status']) : [];

// Get project status history to check if wp_conversion_qa was ever reached
$status_history_query = "SELECT status FROM project_status_history
                        WHERE project_id = ? AND status = 'wp_conversion_qa'
                        LIMIT 1";
$stmt = $conn->prepare($status_history_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$wp_qa_history = $stmt->get_result();

// Check if project has reached WP conversion QA status now OR in the past
$has_wp_qa_status = in_array('wp_conversion_qa', $statuses) || $wp_qa_history->num_rows > 0;

// Check for any status beyond WP conversion (page_creation or golive)
$has_later_status = in_array('page_creation_qa', $statuses) || in_array('golive_qa', $statuses);

// Get WP conversion deadline info - simplified without extension logic
$wp_deadline = !empty($project['wp_conversion_deadline']) ? $project['wp_conversion_deadline'] : '';
$wp_deadline_display = '';

// Now that we have $project, we can check for missed deadlines
if ($user_role === 'webmaster' && $project['webmaster_id'] == $_SESSION['user_id']) {
    // Check for any missed deadlines that need reasons (reason IS NULL is critical here)
    $query = "SELECT * FROM missed_deadlines
              WHERE project_id = ? AND (reason IS NULL OR reason = '')
              ORDER BY deadline_type ASC LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $missed_deadline = $result->fetch_assoc();

    // Debug info
    error_log("Missed deadline check result: " . ($missed_deadline ? "Found ID: {$missed_deadline['id']}" : "None found"));

    // If there's a missed deadline needing a reason, redirect to provide it
    // But first check if we've just returned from that page to avoid infinite loops
    if ($missed_deadline) {
        // Check if we're coming back from submitting a reason
        $session_key = 'extension_submitted_' . $project_id . '_' . $missed_deadline['deadline_type'];
        $skipped_key = 'extension_skipped_' . $project_id . '_' . $missed_deadline['deadline_type'];

        if (!isset($_SESSION[$session_key]) && !isset($_SESSION[$skipped_key])) {
            error_log("Redirecting to missed_deadline_reason.php with deadline_id=" . $missed_deadline['id']);
            header("Location: missed_deadline_reason.php?deadline_id=" . $missed_deadline['id']);
            exit();
        } else {
            error_log("Skipping redirect because we just came from extension form");
            // Reset these session flags after a day to allow future redirects
            if (isset($_SESSION[$session_key]) && $_SESSION[$session_key] < time() - 86400) {
                unset($_SESSION[$session_key]);
            }
            if (isset($_SESSION[$skipped_key]) && $_SESSION[$skipped_key] < time() - 86400) {
                unset($_SESSION[$skipped_key]);
            }
        }
    }

    // Check for new missed deadlines - need to include the function before using it
    require_once 'includes/functions.php';
    $missed_deadlines = checkMissedDeadlines($project_id, $_SESSION['user_id']);

    // Debug info
    error_log("Check for new missed deadlines result: " . ($missed_deadlines ? "Found" : "None found"));

    if ($missed_deadlines) {
        // Get first missed deadline to redirect to
        $first_type = array_key_first($missed_deadlines);
        $deadline_id = $missed_deadlines[$first_type]['id'];

        // Only redirect if we haven't just come from there
        $session_key = 'extension_submitted_' . $project_id . '_' . $first_type;
        $skipped_key = 'extension_skipped_' . $project_id . '_' . $first_type;

        if (!isset($_SESSION[$session_key]) && !isset($_SESSION[$skipped_key])) {
            error_log("Redirecting to missed_deadline_reason.php with deadline_id=$deadline_id");
            header("Location: missed_deadline_reason.php?deadline_id=" . $deadline_id);
            exit();
        }
    }
}

// Add this query to get all active stage statuses
$stages_query = "SELECT stage, status FROM project_stage_status WHERE project_id = ?";
$stmt = $conn->prepare($stages_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

$stage_statuses = [];
while ($row = $result->fetch_assoc()) {
    $stage_statuses[$row['stage']] = $row['status'];
}

require_once 'includes/functions.php';
syncProjectChecklist($project_id);
// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $item_id = (int)$_POST['item_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    $comment = isset($_POST['comment']) ? $conn->real_escape_string($_POST['comment']) : '';

    // Update checklist item status
    $query = "UPDATE project_checklist_status
              SET status = ?
              WHERE project_id = ? AND checklist_item_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $new_status, $project_id, $item_id);
    $stmt->execute();

    // Add comment if provided
    if ($comment) {
        $query = "INSERT INTO comments (project_id, checklist_item_id, user_id, comment)
                 VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiis", $project_id, $item_id, $_SESSION['user_id'], $comment);
        $stmt->execute();
    }

    // Check and update project status
    updateProjectStatus($conn, $project_id);

    // Redirect to refresh the page
    header("Location: view_project.php?id=" . $project_id);
    exit();
}

// Function to update project status based on checklist items
function updateProjectStatus($conn, $project_id) {
    // Get counts for all stages
    $query = "SELECT
        ci.stage,
        COUNT(*) as total_items,
        SUM(CASE WHEN pcs.status = 'fixed' THEN 1 ELSE 0 END) as fixed_count,
        SUM(CASE WHEN pcs.status = 'passed' THEN 1 ELSE 0 END) as passed_count,
        SUM(CASE WHEN pcs.status = 'failed' THEN 1 ELSE 0 END) as failed_count
    FROM project_checklist_status pcs
    JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
    WHERE pcs.project_id = ? AND ci.is_archived = 0
    GROUP BY ci.stage";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Get current statuses
    $current_query = "SELECT current_status FROM projects WHERE id = ?";
    $stmt = $conn->prepare($current_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $current_status = $stmt->get_result()->fetch_assoc()['current_status'] ?? '';

    $current_statuses = !empty($current_status) ? explode(',', $current_status) : [];
    $new_statuses = [];

    // Process each stage
    while ($row = $result->fetch_assoc()) {
        $stage = $row['stage'];
        $qa_status = $stage . '_qa';

        if ($row['total_items'] > 0 && $row['fixed_count'] == $row['total_items']) {
            // All items fixed, add/keep QA status
            if (!in_array($qa_status, $new_statuses)) {
                $new_statuses[] = $qa_status;
            }

            // Update stage_status table
            $upsert_query = "INSERT INTO project_stage_status
                           (project_id, stage, status)
                           VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE status = VALUES(status)";
            $stmt = $conn->prepare($upsert_query);
            $stmt->bind_param("iss", $project_id, $stage, $qa_status);
            $stmt->execute();
        }
    }

    // Update project status
    if (!empty($new_statuses)) {
        sort($new_statuses); // Sort for consistent order
        $combined_status = implode(',', $new_statuses);

        $update_query = "UPDATE projects SET current_status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $combined_status, $project_id);
        $stmt->execute();

        // Debug log
        error_log("Project #{$project_id} status updated to: {$combined_status}");
    }

    // Compare new_statuses with current_statuses to detect changes
    foreach ($new_statuses as $status) {
        // Check if this status is newly added (wasn't in the previous status list)
        if (!in_array($status, $current_statuses)) {
            // This is a new status, record it in the history
            error_log("Recording new status in history: {$status} for project #{$project_id}");

            // The project_status_history table requires an action field according to the SQL schema
            $history_query = "INSERT INTO project_status_history
                            (project_id, status, action, created_by)
                            VALUES (?, ?, 'updated', ?)";
            $stmt = $conn->prepare($history_query);

            // Make sure user ID is available
            $user_id = $_SESSION['user_id'] ?? 1; // Default to 1 if not set

            $stmt->bind_param("isi", $project_id, $status, $user_id);
            $result = $stmt->execute();

            if (!$result) {
                error_log("Failed to record status history: " . $stmt->error);
            } else {
                error_log("Successfully recorded status '{$status}' in history for project #{$project_id}");
            }

            // Add special logging for golive_qa status changes
            if ($status === 'golive_qa') {
                error_log("Project #{$project_id} reached golive_qa status on " . date('Y-m-d H:i:s'));
            }
        }
    }

    return !empty($new_statuses) ? implode(',', $new_statuses) : '';
}

// Get checklist items with their status
$query = "SELECT ci.*, COALESCE(pcs.status, 'idle') as status,
          (SELECT COUNT(*) FROM comments c
           WHERE c.project_id = ? AND c.checklist_item_id = ci.id) as comment_count
          FROM checklist_items ci
          JOIN project_checklist_status pcs  -- Changed LEFT JOIN to JOIN to only show items in this project
              ON ci.id = pcs.checklist_item_id
              AND pcs.project_id = ?
          ORDER BY ci.stage, ci.id";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $project_id, $project_id);
$stmt->execute();
$checklist_items = $stmt->get_result();

require_once 'includes/header.php'

?>

<div class="container mt-4">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php
                if ($_GET['success'] === 'extension_requested') {
                    echo "Extension request submitted successfully and is pending approval.";
                } else {
                    echo htmlspecialchars($_GET['success']);
                }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($_GET['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <div class="row mb-4">
        <div class="col">
            <h2><?php echo htmlspecialchars($project['name']); ?></h2>
            <div class="text-muted mb-3">
                <div class="row mb-2">
                    <div class="col-md-6">
                        <strong>Status:</strong>
                        <span id="project-status-badges">
                        <?php
                        $statuses = !empty($project['current_status']) ? explode(',', $project['current_status']) : [];
                        if (empty($statuses)): ?>
                            <span class="badge bg-secondary me-1">No Status</span>
                        <?php else:
                            sort($statuses); // Ensure consistent order
                            foreach ($statuses as $status):
                                // Replace match expression with if-elseif
                                $status_class = 'secondary'; // Default value
                                if (strpos($status, 'wp_conversion') !== false) {
                                    $status_class = 'info';
                                } elseif (strpos($status, 'page_creation') !== false) {
                                    $status_class = 'warning';
                                } elseif (strpos($status, 'golive') !== false) {
                                    $status_class = 'success';
                                }
                        ?>
                                <span class="badge bg-<?php echo $status_class; ?> me-1">
                                    <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                </span>
                        <?php
                            endforeach;
                        endif;
                        ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <!-- <strong>Webmaster:</strong> -->
                        <?php
                        /*
                        if ($project['webmaster_name']) {
                            echo htmlspecialchars($project['webmaster_name']);
                        } else {
                            echo '<span class="badge bg-danger">Deleted User</span>';
                        }*/
                        ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <strong>WP Conversion Deadline:</strong>
                        <?php
// Replace the WP conversion deadline display section (around line 400-445)

// WP Conversion deadline display
if (!empty($wp_deadline)) {
    $wp_deadline_obj = new DateTime($wp_deadline);
    // Set to end of day to consider full deadline day
    $wp_deadline_obj->setTime(23, 59, 59);

    $today = new DateTime();
    $interval = $today->diff($wp_deadline_obj);
    $days_remaining = $interval->days;

    // Always display the date
    echo date('F j, Y', strtotime($wp_deadline));

    // First check status history for ANY wp_conversion_qa record
    $has_ever_reached_wp_qa = false;
    $first_wp_qa_date = null;

    $wp_status_history_query = "SELECT created_at FROM project_status_history
                              WHERE project_id = ? AND status = 'wp_conversion_qa'
                              ORDER BY created_at ASC LIMIT 1";
    $stmt = $conn->prepare($wp_status_history_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $wp_qa_history = $stmt->get_result();

    if ($wp_qa_history->num_rows > 0) {
        $has_ever_reached_wp_qa = true;
        $row = $wp_qa_history->fetch_assoc();
        $first_wp_qa_date = new DateTime($row['created_at']);
        error_log("Project #{$project_id} first reached wp_conversion_qa on: " . $first_wp_qa_date->format('Y-m-d H:i:s'));
    }

    // Also consider current status or statuses beyond wp_conversion_qa
    $has_wp_qa_now = in_array('wp_conversion_qa', $statuses) ||
                    in_array('page_creation_qa', $statuses) ||
                    in_array('golive_qa', $statuses);

    // Display the appropriate badge based on history
    if ($has_ever_reached_wp_qa) {
        // Project has reached wp_qa at least once
        if ($first_wp_qa_date <= $wp_deadline_obj) {
            echo ' <span class="badge bg-success ms-2">Deadline Achieved</span>';
        } else {
            echo ' <span class="badge bg-danger ms-2">Deadline Missed</span>';
        }
    } else {
        // Project has never reached wp_conversion_qa status
        if ($today <= $wp_deadline_obj) {
            // Future date - deadline has not passed yet
            $days_text = $days_remaining . ' days remaining';
            $badge_class = $days_remaining <= 3 ? 'bg-warning' : 'bg-info';
            echo ' <span class="badge ' . $badge_class . '">' . $days_text . '</span>';
        } else {
            // Past date - deadline has passed but project not in WP QA status
            echo ' <span class="badge bg-danger ms-2">Deadline Missed</span>';
        }
    }
} else {
    echo '<span class="text-muted">Not set</span>';
}
?>
                    </div>
                    <div class="col-md-6">
                        <strong>Project Deadline:</strong>
                        <?php
// Replace the project deadline display section (around line 445-560)

// Project deadline is strict and can't be extended
$project_deadline = !empty($project['project_deadline']) ? $project['project_deadline'] : '';

// First check status history for ANY golive_qa record
$has_ever_reached_golive_qa = false;
$first_golive_qa_date = null;

$status_history_query = "SELECT created_at FROM project_status_history
                        WHERE project_id = ? AND status = 'golive_qa'
                        ORDER BY created_at ASC LIMIT 1";
$stmt = $conn->prepare($status_history_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$golive_history = $stmt->get_result();

if ($golive_history->num_rows > 0) {
    $has_ever_reached_golive_qa = true;
    $row = $golive_history->fetch_assoc();
    $first_golive_qa_date = new DateTime($row['created_at']);
    error_log("Project #{$project_id} first reached golive_qa on: " . $first_golive_qa_date->format('Y-m-d H:i:s'));
} else {
    error_log("Project #{$project_id} has never reached golive_qa according to history");
}

// Add this debugging code right after checking for golive_qa history (around line 440)

// Add debug logging to check if SQL query is working
if ($golive_history->num_rows == 0) {
    // Check if the table has any data at all
    $check_query = "SELECT COUNT(*) as count FROM project_status_history";
    $check_result = $conn->query($check_query);
    $total_count = $check_result->fetch_assoc()['count'];

    error_log("DEBUG: project_status_history table has {$total_count} total records");

    // Check project specific records
    $check_project_query = "SELECT * FROM project_status_history WHERE project_id = ?";
    $stmt = $conn->prepare($check_project_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $project_history = $stmt->get_result();

    error_log("DEBUG: Found " . $project_history->num_rows . " history records for project #{$project_id}");

    // Check if there are any golive_qa records in the entire table
    $check_golive_query = "SELECT COUNT(*) as count FROM project_status_history WHERE status = 'golive_qa'";
    $golive_count_result = $conn->query($check_golive_query);
    $golive_count = $golive_count_result->fetch_assoc()['count'];

    error_log("DEBUG: There are {$golive_count} golive_qa records in the entire history table");
}

// Also check if it's currently in a completed status
$has_completed_status = in_array('completed', $statuses);
$has_golive_qa_status = in_array('golive_qa', $statuses);

// Determine deadline met status for project
if (!empty($project_deadline)) {
    // Add 23:59:59 to the deadline date to properly consider entire day
    $project_deadline_obj = new DateTime($project_deadline);
    $project_deadline_obj->setTime(23, 59, 59);

    $today = new DateTime();
    $interval = $today->diff($project_deadline_obj);
    $days_remaining = $interval->days;

    // Always display the deadline date
    echo date('F j, Y', strtotime($project_deadline));

    // First determine if the project ever reached golive_qa status
    if ($has_ever_reached_golive_qa) {
        // Project has reached golive_qa at least once, check if it was before the deadline
        if ($first_golive_qa_date && $project_deadline_obj) {
            if ($first_golive_qa_date <= $project_deadline_obj) {
                echo ' <span class="badge bg-success ms-2">Deadline Achieved</span>';
                error_log("Project #{$project_id}: Deadline achieved (golive_qa on " .
                          $first_golive_qa_date->format('Y-m-d') . ", deadline was " .
                          $project_deadline_obj->format('Y-m-d') . ")");
            } else {
                echo ' <span class="badge bg-danger ms-2">Deadline Missed</span>';
                error_log("Project #{$project_id}: Deadline missed (golive_qa on " .
                          $first_golive_qa_date->format('Y-m-d') . ", deadline was " .
                          $project_deadline_obj->format('Y-m-d') . ")");
            }
        } else {
            // If for some reason we couldn't determine exact date of first golive_qa
            // but we know it happened (from has_ever_reached_golive_qa flag)
            echo ' <span class="badge bg-success ms-2">Deadline Status: Unknown</span>';
            error_log("Project #{$project_id}: Couldn't determine exact date of first golive_qa status");
        }
    } else {
        // Project has never reached golive_qa status
        if ($today <= $project_deadline_obj) {
            // Future date - deadline not passed yet
            $days_text = $days_remaining . ' days remaining';
            $badge_class = $days_remaining <= 7 ? 'bg-warning' : 'bg-info';
            echo ' <span class="badge ' . $badge_class . '">' . $days_text . '</span>';
        } else {
            // Past date - deadline has passed but project not in golive_qa status
            echo ' <span class="badge bg-danger ms-2">Deadline Missed</span>';

            // Check if we need to ask for a reason
            $reason_query = "SELECT id FROM missed_deadlines
                             WHERE project_id = ? AND deadline_type = 'project'
                             AND reason IS NULL";
            $stmt = $conn->prepare($reason_query);  // This was missing before
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $missed_result = $stmt->get_result();

            // If no reason record exists, create one
            if ($missed_result->num_rows === 0) {
                $check_query = "SELECT COUNT(*) as count FROM missed_deadlines
                              WHERE project_id = ? AND deadline_type = 'project'";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc()['count'] > 0;

                if (!$exists) {
                    // Insert missed deadline record
                    $insert_query = "INSERT INTO missed_deadlines
                                   (project_id, deadline_type, original_deadline)
                                   VALUES (?, 'project', ?)";
                    $stmt->prepare($insert_query);
                    $stmt->bind_param("is", $project_id, $project_deadline);
                    $stmt->execute();
                }
            }
        }
    }
} else {
    echo '<span class="text-muted">Not set</span>';
}
?>
                    </div>
                </div>

                <!-- Project Links Section (Compact Version) -->
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="d-flex flex-wrap">
                            <!-- Ticket Link -->
                            <div class="me-4 mb-2">
                                <i class="bi bi-ticket-detailed text-primary"></i>
                                <?php if (!empty($project['ticket_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($project['ticket_link']); ?>" target="_blank" class="ms-1" title="<?php echo htmlspecialchars($project['ticket_link']); ?>">
                                        Ticket Link
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted ms-1">No Ticket Link</span>
                                <?php endif; ?>
                            </div>

                            <!-- Test Site Link -->
                            <div class="me-4 mb-2">
                                <i class="bi bi-browser-chrome text-success"></i>
                                <?php if (!empty($project['test_site_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($project['test_site_link']); ?>" target="_blank" class="ms-1" title="<?php echo htmlspecialchars($project['test_site_link']); ?>">
                                        Test Site
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted ms-1">No Test Site</span>
                                <?php endif; ?>
                            </div>

                            <!-- GP Link -->
                            <div class="me-4 mb-2">
                                <i class="bi bi-link-45deg text-info"></i>
                                <?php if (!empty($project['gp_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($project['gp_link']); ?>" target="_blank" class="ms-1" title="<?php echo htmlspecialchars($project['gp_link']); ?>">
                                        GP Link
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted ms-1">No GP Link</span>
                                <?php endif; ?>
                            </div>

                            <!-- Live Site Link -->
                            <div class="mb-2">
                                <i class="bi bi-globe text-warning"></i>
                                <?php if (!empty($project['live_site_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($project['live_site_link']); ?>" target="_blank" class="ms-1" title="<?php echo htmlspecialchars($project['live_site_link']); ?>">
                                        Live Site
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted ms-1">No Live Site</span>
                                <?php endif; ?>
                            </div>

                            <!-- Edit Links Button (for webmaster and admin) -->
                            <?php if ($user_role === 'admin' || ($user_role === 'webmaster' && $project['webmaster_id'] == $_SESSION['user_id'])): ?>
                            <div class="ms-auto">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editLinksModal">
                                    <i class="bi bi-pencil-square"></i> Edit Links
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs" id="stageTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="wp-tab" data-bs-toggle="tab" href="#wp" role="tab">
                        WP Conversion
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="page-tab" data-bs-toggle="tab" href="#page" role="tab">
                        Page Creation
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="golive-tab" data-bs-toggle="tab" href="#golive" role="tab">
                        Golive
                    </a>
                </li>
            </ul>

            <div class="tab-content mt-3" id="stageTabContent">
                <?php
                $stages = ['wp_conversion', 'page_creation', 'golive'];
                foreach ($stages as $stage):
                    $active = $stage == 'wp_conversion' ? 'show active' : '';
                ?>
                <div class="tab-pane fade <?php echo $active; ?>"
                     id="<?php echo explode('_', $stage)[0]; ?>" role="tabpanel">
                    <div class="accordion" id="<?php echo $stage; ?>Accordion">
                        <?php
                        $checklist_items->data_seek(0);
                        while ($item = $checklist_items->fetch_assoc()):
                            if ($item['stage'] == $stage):
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#item<?php echo $item['id']; ?>">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                    <?php if ($item['is_archived']): ?>
    <span class="badge bg-secondary ms-2" title="This item has been archived">Archived</span>
<?php endif; ?>
                                   <span class="badge bg-<?php
                                        // Replace match with if-elseif
                                        $status_class = 'secondary'; // Default value
                                        if ($item['status'] === 'passed') {
                                            $status_class = 'success';
                                        } elseif ($item['status'] === 'failed') {
                                            $status_class = 'danger';
                                        } elseif ($item['status'] === 'fixed') {
                                            $status_class = 'warning';
                                        }
                                        echo $status_class;
                                    ?> ms-2">
                                        <?php echo ucfirst((string)$item['status']); ?>
                                    </span>
                                    <?php if ($item['comment_count'] > 0): ?>
                                        <span class="badge bg-info ms-2">
                                            <i class="bi bi-chat"></i> <?php echo $item['comment_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                            </h2>
                            <div id="item<?php echo $item['id']; ?>" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>How to Check:</h6>
                                            <p><?php echo nl2br(htmlspecialchars($item['how_to_check'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>How to Fix:</h6>
                                            <p><?php echo nl2br(htmlspecialchars($item['how_to_fix'])); ?></p>
                                        </div>
                                    </div>

                                    <!-- Status Update Form -->
                                    <!-- In the checklist items loop in view_project.php -->
                                    <?php
                                    $isDisabled = false;
                                    $current_status = $project['current_status'];
                                    $user_role = getUserRole();

                                    // Only disable for QA roles when not in QA stage
                                    if (in_array($user_role, ['qa_reporter', 'qa_manager']) && strpos($current_status, '_qa') === false) {
                                        $isDisabled = true;
                                    }
                                    ?>

                                    <form method="POST" data-status-form class="mt-3">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">

                                        <div class="row">
                                            <div class="col-md-6">
                                                <select name="status" class="form-select" required <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                                    <option value="">Select Status</option>
                                                    <?php if ($user_role == 'webmaster'): ?>
                                                        <option value="fixed" <?php echo $item['status'] == 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                                    <?php endif; ?>
                                                    <?php if (in_array($user_role, ['qa_reporter', 'qa_manager', 'admin'])): ?>
                                                        <option value="passed" <?php echo $item['status'] == 'passed' ? 'selected' : ''; ?>>Passed</option>
                                                        <option value="failed" <?php echo $item['status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <button type="submit" class="btn btn-primary" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                                    Update Status
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-3 mb-3">
                                            <textarea name="comment" class="form-control" placeholder="Add a comment (optional)"
                                                    <?php echo $isDisabled ? 'disabled' : ''; ?>></textarea>
                                        </div>
                                    </form>

                                    <!-- Comments Section -->

                                    <div class="comments-section">
                                        <?php
                                        $comments_query = "SELECT c.*, u.username, u.role FROM comments c
                                                        JOIN users u ON c.user_id = u.id
                                                        WHERE c.project_id = ? AND c.checklist_item_id = ?
                                                        ORDER BY c.created_at DESC";
                                        $stmt = $conn->prepare($comments_query);
                                        $stmt->bind_param("ii", $project['id'], $item['id']);
                                        $stmt->execute();
                                        $comments = $stmt->get_result();

                                        while ($comment = $comments->fetch_assoc()):
                                            // Set alert class based on user role
                                            $alertClass = 'alert-secondary'; // Default value
                                            if ($comment['role'] === 'webmaster') {
                                                $alertClass = 'alert-primary';
                                            } elseif ($comment['role'] === 'qa_reporter' || $comment['role'] === 'qa_manager') {
                                                $alertClass = 'alert-warning';
                                            } elseif ($comment['role'] === 'admin') {
                                                $alertClass = 'alert-info';
                                            }
                                        ?>
                                            <div class="alert <?php echo $alertClass; ?> mb-2">
                                                <p class="mb-1"><?php echo htmlspecialchars($comment['comment']); ?></p>
                                                <small>
                                                    By <?php echo htmlspecialchars($comment['username']); ?>
                                                    (<?php echo ucfirst($comment['role']); ?>) -
                                                    <?php echo date('Y-m-d H:i:s', strtotime($comment['created_at'])); ?>
                                                </small>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>


                                </div>
                            </div>
                        </div>
                        <?php
                            endif;
                        endwhile;
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Replace the current Admin/Webmaster Notes display section -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Admin Notes</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($project['admin_notes'])): ?>
                    <div class="rich-text-content">
                        <?php echo $project['admin_notes']; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No admin notes available.</p>
                <?php endif; ?>

                <?php if ($user_role === 'admin'): ?>
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#editAdminNotesModal">
                    Edit Admin Notes
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Webmaster Notes</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($project['webmaster_notes'])): ?>
                    <div class="rich-text-content">
                        <?php echo $project['webmaster_notes']; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No webmaster notes available.</p>
                <?php endif; ?>

                <?php if ($user_role === 'webmaster' && $project['webmaster_id'] == $_SESSION['user_id']): ?>
                <button type="button" class="btn btn-sm btn-outline-success"
                        data-bs-toggle="modal" data-bs-target="#editWebmasterNotesModal">
                    Edit Webmaster Notes
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
<?php
$current_user_query = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($current_user_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$current_user_result = $stmt->get_result();
$current_user = $current_user_result->fetch_assoc();
?>

<!-- Admin Notes Modal -->
<?php if ($user_role === 'admin'): ?>
<div class="modal fade" id="editAdminNotesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Admin Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="update_project_notes.php">
                <div class="modal-body">
                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                    <input type="hidden" name="note_type" value="admin">

                    <!-- Hidden textarea to store HTML content -->
                    <textarea class="d-none" id="admin_notes" name="notes"><?php echo $project['admin_notes'] ?? ''; ?></textarea>

                    <!-- Quill editor container -->
                    <div id="admin_notes_editor" class="quill-editor"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="admin-notes-submit">Save Notes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Webmaster Notes Modal -->
<?php if ($user_role === 'webmaster' && $project['webmaster_id'] == $_SESSION['user_id']): ?>
<div class="modal fade" id="editWebmasterNotesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Webmaster Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="update_project_notes.php">
                <div class="modal-body">
                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                    <input type="hidden" name="note_type" value="webmaster">

                    <!-- Hidden textarea to store HTML content -->
                    <textarea class="d-none" id="webmaster_notes" name="notes"><?php echo $project['webmaster_notes'] ?? ''; ?></textarea>

                    <!-- Quill editor container -->
                    <div id="webmaster_notes_editor" class="quill-editor"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="webmaster-notes-submit">Save Notes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Project Links Edit Modal -->
<?php if ($user_role === 'admin' || ($user_role === 'webmaster' && $project['webmaster_id'] == $_SESSION['user_id'])): ?>
<div class="modal fade" id="editLinksModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Project Links</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="update_project_links.php">
                <div class="modal-body">
                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

                    <div class="mb-3">
                        <label for="ticket_link" class="form-label">Ticket Link</label>
                        <input type="url" class="form-control" id="ticket_link" name="ticket_link"
                               value="<?php echo htmlspecialchars($project['ticket_link'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="test_site_link" class="form-label">Test Site Link</label>
                        <input type="url" class="form-control" id="test_site_link" name="test_site_link"
                               value="<?php echo htmlspecialchars($project['test_site_link'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="gp_link" class="form-label">GP Link</label>
                        <input type="url" class="form-control" id="gp_link" name="gp_link"
                               value="<?php echo htmlspecialchars($project['gp_link'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="live_site_link" class="form-label">Live Site Link</label>
                        <input type="url" class="form-control" id="live_site_link" name="live_site_link"
                               value="<?php echo htmlspecialchars($project['live_site_link'] ?? ''); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Links</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Quill editor CSS in the head section -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<!-- Add these styles to the existing style section -->
<style>
    /* Add this to your existing styles */
    .quill-editor {
        height: 300px;
        margin-bottom: 15px;
    }
    .rich-text-content {
        font-family: inherit;
    }
    .rich-text-content h1,
    .rich-text-content h2,
    .rich-text-content h3,
    .rich-text-content h4,
    .rich-text-content h5,
    .rich-text-content h6 {
        margin-top: 0.5em;
        margin-bottom: 0.5em;
    }
    .rich-text-content p {
        margin-bottom: 1em;
    }
    .rich-text-content ul,
    .rich-text-content ol {
        margin-top: 0;
        margin-bottom: 1em;
        padding-left: 2em;
    }
    .rich-text-content img {
        max-width: 100%;
        height: auto;
    }
    .rich-text-content a {
        color: #0d6efd;
    }
    .rich-text-content blockquote {
        border-left: 4px solid #dee2e6;
        padding-left: 1em;
        margin-left: 0;
        color: #6c757d;
    }
</style>

<!-- Then continue with the existing script tags -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const currentUserName = <?php echo json_encode($current_user['username']); ?>;
document.querySelectorAll('form[data-status-form]').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const statusSelect = this.querySelector('select[name="status"]');
        const submitButton = this.querySelector('button[type="submit"]');
        const projectId = formData.get('project_id');

        // Disable form elements during submission
        statusSelect.disabled = true;
        submitButton.disabled = true;

        fetch('update_checklist_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(data);
                // Update the status badge
                const itemRow = this.closest('.accordion-item');
                const statusBadge = itemRow.querySelector('.badge');
                const newStatus = statusSelect.value;

                // Update badge text and class
                statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                statusBadge.className = `badge ms-2 bg-${
                    newStatus === 'passed' ? 'success' :
                    newStatus === 'failed' ? 'danger' :
                    newStatus === 'fixed' ? 'warning' : 'secondary'
                }`;

                // Update project status badges if it changed
                if (data.newStatus) {
                    // Get the status badges container by ID
                    const statusBadgesContainer = document.getElementById('project-status-badges');

                    if (statusBadgesContainer) {
                        // Clear existing badges
                        statusBadgesContainer.innerHTML = '';

                        // Process the new status string into an array of statuses
                        const newStatuses = data.newStatus.split(',').filter(s => s.trim() !== '');

                        if (newStatuses.length === 0) {
                            // No statuses
                            statusBadgesContainer.innerHTML = '<span class="badge bg-secondary me-1">No Status</span>';
                        } else {
                            // Add each status as a badge
                            newStatuses.forEach(status => {
                                // Determine badge color based on status
                                let badgeClass = 'secondary';
                                if (status.includes('wp_conversion')) {
                                    badgeClass = 'info';
                                } else if (status.includes('page_creation')) {
                                    badgeClass = 'warning';
                                } else if (status.includes('golive')) {
                                    badgeClass = 'success';
                                } else if (status === 'completed') {
                                    badgeClass = 'primary';
                                }

                                // Create badge HTML
                                const formattedStatus = status.replace(/_/g, ' ')
                                                            .replace(/\b\w/g, l => l.toUpperCase());
                                statusBadgesContainer.innerHTML += `<span class="badge bg-${badgeClass} me-1">${formattedStatus}</span>`;
                            });
                        }

                        console.log(`Updated project status to: ${data.newStatus}`);
                    }
                }

                // If WP conversion items prompt
                if (data.showTestSitePrompt) {
                    // Create bootstrap modal for test site link
                    const modalHtml = `
                        <div class="modal fade" id="testSiteModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Test Site Link Required</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>All WP Conversion items are now fixed. Please provide a test site link:</p>
                                        <div class="mb-3">
                                            <label for="test-site-input" class="form-label">Test Site URL</label>
                                            <input type="url" class="form-control" id="test-site-input" placeholder="https://..." required>
                                            <div class="invalid-feedback">Please enter a valid URL</div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-primary" id="save-test-site-btn">Save</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    // Append modal to body
                    const modalContainer = document.createElement('div');
                    modalContainer.innerHTML = modalHtml;
                    document.body.appendChild(modalContainer);

                    // Show the modal
                    const testSiteModal = new bootstrap.Modal(document.getElementById('testSiteModal'));
                    testSiteModal.show();

                    // Handle save button
                    document.getElementById('save-test-site-btn').addEventListener('click', function() {
                        const testSiteInput = document.getElementById('test-site-input');
                        if (testSiteInput.checkValidity()) {
                            const testSiteUrl = testSiteInput.value;

                            // Save test site URL via AJAX
                            fetch('save_test_site_url.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ project_id: projectId, test_site_url: testSiteUrl })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Close the modal
                                    testSiteModal.hide();
                                    // Remove modal from DOM
                                    modalContainer.remove();
                                } else {
                                    // Show error message
                                    testSiteInput.classList.add('is-invalid');
                                    testSiteInput.nextElementSibling.textContent = data.error || 'An error occurred while saving the test site URL.';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                // Show error message
                                testSiteInput.classList.add('is-invalid');
                                testSiteInput.nextElementSibling.textContent = 'An error occurred while saving the test site URL.';
                            });
                        } else {
                            testSiteInput.classList.add('is-invalid');
                        }
                    });
                }

                // If Golive items prompt
                if (data.showLiveSitePrompt) {
                    // Create bootstrap modal for live site link
                    const modalHtml = `
                        <div class="modal fade" id="liveSiteModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Live Site Link Required</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Please provide the live site link:</p>
                                        <div class="mb-3">
                                            <label for="live-site-input" class="form-label">Live Site URL</label>
                                            <input type="url" class="form-control" id="live-site-input" placeholder="https://..." required>
                                            <div class="invalid-feedback">Please enter a valid URL</div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-primary" id="save-live-site-btn">Save</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    // Append modal to body
                    const modalContainer = document.createElement('div');
                    modalContainer.innerHTML = modalHtml;
                    document.body.appendChild(modalContainer);

                    // Show the modal
                    const liveSiteModal = new bootstrap.Modal(document.getElementById('liveSiteModal'));
                    liveSiteModal.show();

                    // Handle save button
                    document.getElementById('save-live-site-btn').addEventListener('click', function() {
                        const liveSiteInput = document.getElementById('live-site-input');
                        if (liveSiteInput.checkValidity()) {
                            const liveSiteUrl = liveSiteInput.value;

                            // Save live site URL via AJAX
                            fetch('save_live_site_url.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ project_id: projectId, live_site_url: liveSiteUrl })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Close the modal
                                    liveSiteModal.hide();
                                    // Remove modal from DOM
                                    modalContainer.remove();
                                } else {
                                    // Show error message
                                    liveSiteInput.classList.add('is-invalid');
                                    liveSiteInput.nextElementSibling.textContent = data.error || 'An error occurred while saving the live site URL.';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                // Show error message
                                liveSiteInput.classList.add('is-invalid');
                                liveSiteInput.nextElementSibling.textContent = 'An error occurred while saving the live site URL.';
                            });
                        } else {
                            liveSiteInput.classList.add('is-invalid');
                        }
                    });
                }

                // Add success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success mt-2';
                alert.textContent = 'Status updated successfully!';
                this.appendChild(alert);

                // Remove alert after 3 seconds
                setTimeout(() => alert.remove(), 3000);

                // If comment was added, append it to comments section
                const commentText = this.querySelector('textarea[name="comment"]');
                if (commentText.value.trim()) {
                    const commentsSection = itemRow.querySelector('.comments-section');
                    if (commentsSection) {
                        const newComment = document.createElement('div');
                        newComment.className = 'card mb-2 mt-2';
                        newComment.innerHTML = `
                            <div class="card-body">
                                <p class="card-text">${commentText.value}</p>
                                <small class="text-muted">
                                    By ${currentUserName} just now
                                </small>
                            </div>
                        `;
                        commentsSection.insertBefore(newComment, commentsSection.firstChild);
                        commentText.value = '';
                    }
                }
            } else {
                // Show error message
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger mt-2';
                alert.textContent = data.error || 'An error occurred while updating status.';
                this.appendChild(alert);

                // Remove alert after 3 seconds
                setTimeout(() => alert.remove(), 3000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Show error message
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger mt-2';
            alert.textContent = 'An error occurred while updating status.';
            this.appendChild(alert);

            // Remove alert after 3 seconds
            setTimeout(() => alert.remove(), 3000);
        })
        .finally(() => {
            // Re-enable form elements
            statusSelect.disabled = false;
            submitButton.disabled = false;
        });
    });
});
</script>

<!-- Add Quill.js before the closing body tag -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Only initialize if admin modal exists
        if (document.getElementById('admin_notes_editor')) {
            // Initialize editor for admin notes
            const adminEditor = new Quill('#admin_notes_editor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{'color': []}, {'background': []}],
                        [{'list': 'ordered'}, {'list': 'bullet'}],
                        [{'align': []}],
                        ['link'],
                        ['clean']
                    ]
                },
                placeholder: 'Add administrator notes here...'
            });

            // Set initial content from textarea
            const adminNotesContent = document.getElementById('admin_notes').value;
            if (adminNotesContent) {
                adminEditor.root.innerHTML = adminNotesContent;
            }

            // Update hidden textarea before form submission
            document.getElementById('admin-notes-submit').addEventListener('click', function() {
                document.getElementById('admin_notes').value = adminEditor.root.innerHTML;
            });
        }

        // Only initialize if webmaster modal exists
        if (document.getElementById('webmaster_notes_editor')) {
            // Initialize editor for webmaster notes
            const webmasterEditor = new Quill('#webmaster_notes_editor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{'color': []}, {'background': []}],
                        [{'list': 'ordered'}, {'list': 'bullet'}],
                        [{'align': []}],
                        ['link'],
                        ['clean']
                    ]
                },
                placeholder: 'Add webmaster notes here...'
            });

            // Set initial content from textarea
            const webmasterNotesContent = document.getElementById('webmaster_notes').value;
            if (webmasterNotesContent) {
                webmasterEditor.root.innerHTML = webmasterNotesContent;
            }

            // Update hidden textarea before form submission
            document.getElementById('webmaster-notes-submit').addEventListener('click', function() {
                document.getElementById('webmaster_notes').value = webmasterEditor.root.innerHTML;
            });
        }
    });
</script>
</body>
</html>