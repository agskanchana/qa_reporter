<?php
function syncProjectChecklist($project_id = null) {
    global $conn;

    if ($project_id) {
        // Ensure project has initial status
        $status_query = "UPDATE projects SET current_status = 'wp_conversion'
                        WHERE id = ? AND (current_status IS NULL OR current_status = '')";
        $stmt = $conn->prepare($status_query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
    }

    $projects_query = $project_id ?
        "SELECT id, created_at FROM projects WHERE id = ?" :
        "SELECT id, created_at FROM projects";

    if ($project_id) {
        $stmt = $conn->prepare($projects_query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $projects = $stmt->get_result();
    } else {
        $projects = $conn->query($projects_query);
    }

    while ($project = $projects->fetch_assoc()) {
        // For new syncs, only get non-archived items that don't have a status
        $query = "SELECT ci.*
                 FROM checklist_items ci
                 LEFT JOIN project_checklist_status pcs
                    ON ci.id = pcs.checklist_item_id
                    AND pcs.project_id = ?
                 WHERE pcs.id IS NULL
                 AND (ci.is_archived = 0 OR ci.archived_at > ?)"; // Include items archived after project creation

        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $project['id'], $project['created_at']);
        $stmt->execute();
        $missing_items = $stmt->get_result();

        while ($item = $missing_items->fetch_assoc()) {
            // Remove created_at from the INSERT statement since it doesn't exist
            $insert = $conn->prepare("INSERT INTO project_checklist_status
                                    (project_id, checklist_item_id, status, updated_at)
                                    VALUES (?, ?, 'idle', NOW())");
            $insert->bind_param("ii", $project['id'], $item['id']);
            $insert->execute();
        }
    }
}

function removeChecklistItemFromProjects($item_id) {
    global $conn;

    $conn->begin_transaction();
    try {
        // First, delete any comments associated with this checklist item
        $query = "DELETE FROM comments WHERE checklist_item_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();

        // Delete the status entries for this checklist item
        $query = "DELETE FROM project_checklist_status WHERE checklist_item_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();

        // Finally, delete the checklist item itself
        $query = "DELETE FROM checklist_items WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Add or update the getDetailedWebmasterProjects function

function getDetailedWebmasterProjects($conn, $start_date, $end_date) {
    // Modify end_date to include the entire day
    $end_date = date('Y-m-d 23:59:59', strtotime($end_date));

    $query = "SELECT
        u.id as webmaster_id,
        u.username as webmaster_name,
        p.id as project_id,
        p.name as project_name,
        p.current_status,
        p.created_at,

        (SELECT COUNT(*)
         FROM project_checklist_status pcs
         JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
         WHERE pcs.project_id = p.id
         AND ci.stage = 'wp_conversion'
         AND ci.is_archived = 0
         AND pcs.is_archived = 0) as total_wp_items,

        (SELECT COUNT(*)
         FROM project_checklist_status pcs
         JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
         WHERE pcs.project_id = p.id
         AND ci.stage = 'wp_conversion'
         AND pcs.status IN ('passed', 'fixed')
         AND ci.is_archived = 0
         AND pcs.is_archived = 0) as wp_items_completed,

        -- Similar modifications for page_creation and golive items
        (SELECT COUNT(*)
         FROM project_checklist_status pcs
         JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
         WHERE pcs.project_id = p.id
         AND ci.stage = 'page_creation'
         AND ci.is_archived = 0
         AND pcs.is_archived = 0) as total_page_items,

        (SELECT COUNT(*)
         FROM project_checklist_status pcs
         JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
         WHERE pcs.project_id = p.id
         AND ci.stage = 'page_creation'
         AND pcs.status IN ('passed', 'fixed')
         AND ci.is_archived = 0
         AND pcs.is_archived = 0) as page_items_completed,

        (SELECT COUNT(*)
         FROM project_checklist_status pcs
         JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
         WHERE pcs.project_id = p.id
         AND ci.stage = 'golive'
         AND ci.is_archived = 0
         AND pcs.is_archived = 0) as total_golive_items,

        (SELECT COUNT(*)
         FROM project_checklist_status pcs
         JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
         WHERE pcs.project_id = p.id
         AND ci.stage = 'golive'
         AND pcs.status IN ('passed', 'fixed')
         AND ci.is_archived = 0
         AND pcs.is_archived = 0) as golive_items_completed

    FROM users u
    LEFT JOIN projects p ON u.id = p.webmaster_id
        AND DATE(p.created_at) >= ?
        AND DATE(p.created_at) <= DATE(?)
    WHERE u.role = 'webmaster'
    ORDER BY u.username, p.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result();
}

function removeChecklistItemSafely($item_id) {
    global $conn;

    $conn->begin_transaction();
    try {
        // First, get the item's stage to handle status transitions
        $query = "SELECT stage FROM checklist_items WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stage = $stmt->get_result()->fetch_assoc()['stage'];

        // Get all projects using this checklist item
        $query = "SELECT DISTINCT project_id, status
                 FROM project_checklist_status
                 WHERE checklist_item_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $affected_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // For each affected project, check if this was the last item in its stage
        foreach ($affected_projects as $project) {
            $query = "SELECT COUNT(*) as remaining_items
                     FROM project_checklist_status pcs
                     JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
                     WHERE pcs.project_id = ?
                     AND ci.stage = ?
                     AND ci.id != ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isi", $project['project_id'], $stage, $item_id);
            $stmt->execute();
            $remaining_items = $stmt->get_result()->fetch_assoc()['remaining_items'];

            // If this was the last item and it was passed/fixed, ensure project can progress
            if ($remaining_items == 0 && in_array($project['status'], ['passed', 'fixed'])) {
                // Update project status to next stage if needed
                $query = "UPDATE projects
                         SET current_status = CASE
                             WHEN current_status = 'wp_conversion' THEN 'page_creation'
                             WHEN current_status = 'page_creation' THEN 'golive'
                             WHEN current_status = 'golive' THEN 'completed'
                             ELSE current_status
                         END
                         WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $project['project_id']);
                $stmt->execute();
            }
        }

        // Archive the checklist item instead of deleting it
        // This maintains historical data while removing it from active use
        $query = "UPDATE checklist_items
                 SET is_archived = 1, archived_at = NOW(), archived_by = ?
                 WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $_SESSION['user_id'], $item_id);
        $stmt->execute();

        // Mark related status entries as archived
        $query = "UPDATE project_checklist_status
                 SET is_archived = 1
                 WHERE checklist_item_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Add a notification for a user or role
 *
 * @param string $message The notification message
 * @param string $type The notification type (info, warning, success, danger)
 * @param int|null $user_id The user ID (null for role-based notifications)
 * @param string|null $role The role (null for user-specific notifications)
 * @return bool True on success, false on failure
 */
function addNotification($message, $type = 'info', $user_id = null, $role = null) {
    global $conn;

    // Validate inputs
    if (empty($message)) {
        return false;
    }

    // Validate type
    $valid_types = ['info', 'warning', 'success', 'danger'];
    if (!in_array($type, $valid_types)) {
        $type = 'info'; // Default to info if invalid
    }

    // Validate role if provided
    if ($role !== null) {
        $valid_roles = ['admin', 'qa_manager', 'qa_reporter', 'webmaster'];
        if (!in_array($role, $valid_roles)) {
            $role = null; // Set to null if not a valid role
        }
    }

    // For role-based notifications with no specific user, set user_id to NULL
    if ($role !== null && $user_id === null) {
        $query = "INSERT INTO notifications (user_id, role, message, type, is_read)
                  VALUES (NULL, ?, ?, ?, 0)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $role, $message, $type);
    } else {
        // For specific user notifications
        $query = "INSERT INTO notifications (user_id, role, message, type, is_read)
                  VALUES (?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isss", $user_id, $role, $message, $type);
    }

    return $stmt->execute();
}

/**
 * Check for missed project deadlines and send notifications
 */
function check_wp_conversion_deadlines() {
    global $conn;

    // Get current date in Sri Lanka time (UTC+5:30)
    $timezone = new DateTimeZone('Asia/Colombo');
    $today = new DateTime('now', $timezone);
    $today_date = $today->format('Y-m-d');
    $current_time = $today->format('H:i:s');

    // Only run this check at/after midnight
    if ($current_time < '00:00:05') {
        return; // Not midnight yet
    }

    // Find projects where:
    // 1. WP conversion deadline is today or in the past
    // 2. Current status is still 'wp_conversion'
    // 3. Not already marked as missed
    $query = "SELECT p.*, u.email as webmaster_email, u.username as webmaster_name
              FROM projects p
              LEFT JOIN users u ON p.webmaster_id = u.id
              WHERE p.wp_conversion_deadline < ?
              AND p.current_status = 'wp_conversion'
              AND p.id NOT IN (
                  SELECT project_id FROM missed_deadlines
                  WHERE deadline_type = 'wp_conversion'
              )";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $today_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($project = $result->fetch_assoc()) {
        // Record this missed deadline
        record_missed_deadline($project['id'], 'wp_conversion', $project['wp_conversion_deadline']);

        // Send notification to admin users
        send_admin_notifications($project);

        // Send notification to the assigned webmaster
        if ($project['webmaster_id']) {
            send_webmaster_notification($project);
        }
    }
}

/**
 * Record a missed deadline in the database
 */
function record_missed_deadline($project_id, $deadline_type, $original_deadline) {
    global $conn;

    $query = "INSERT INTO missed_deadlines
              (project_id, deadline_type, original_deadline, recorded_at)
              VALUES (?, ?, ?, NOW())";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $project_id, $deadline_type, $original_deadline);
    $stmt->execute();
}

/**
 * Send notifications to all admin users
 */
function send_admin_notifications($project) {
    global $conn;

    // Get all admin users
    $query = "SELECT id FROM users WHERE role = 'admin'";
    $result = $conn->query($query);

    while ($admin = $result->fetch_assoc()) {
        $message = "WP conversion deadline missed for project '{$project['name']}'. The deadline was {$project['wp_conversion_deadline']}.";

        // Use addNotification function if available
        if (function_exists('addNotification')) {
            addNotification($message, 'warning', $admin['id']);
        } else {
            $insert_query = "INSERT INTO notifications
                           (user_id, message, type, is_read)
                           VALUES (?, ?, 'warning', 0)";

            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("is", $admin['id'], $message);
            $stmt->execute();
        }
    }
}

/**
 * Send notification to the webmaster assigned to the project
 */
function send_webmaster_notification($project) {
    global $conn;

    $message = "You have missed the WP conversion deadline for project '{$project['name']}'. " .
               "The deadline was {$project['wp_conversion_deadline']}. " .
               "Please provide a reason and request an extension if needed.";

    // Use addNotification function if available
    if (function_exists('addNotification')) {
        addNotification($message, 'warning', $project['webmaster_id']);
    } else {
        $insert_query = "INSERT INTO notifications
                       (user_id, message, type, is_read)
                       VALUES (?, ?, 'warning', 0)";

        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("is", $project['webmaster_id'], $message);
        $stmt->execute();
    }
}