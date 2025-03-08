<?php
/**
 * Script to check for missed deadlines and record them
 * This can be run via cron job or included in dashboard/login flow
 */
require_once 'config.php';
require_once 'functions.php';

// Get current date for comparison
$today = date('Y-m-d');

// Check for missed WP conversion deadlines
$wp_query = "SELECT p.id, p.name, p.webmaster_id, p.wp_conversion_deadline
             FROM projects p
             LEFT JOIN missed_deadlines md ON p.id = md.project_id AND md.deadline_type = 'wp_conversion'
             WHERE p.wp_conversion_deadline < ?
               AND p.wp_conversion_deadline IS NOT NULL
               AND md.id IS NULL";

$stmt = $conn->prepare($wp_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

while ($project = $result->fetch_assoc()) {
    // Insert record for missed deadline
    $insert = "INSERT INTO missed_deadlines
              (project_id, deadline_type, original_deadline, recorded_at)
              VALUES (?, 'wp_conversion', ?, NOW())";
    $stmt = $conn->prepare($insert);
    $stmt->bind_param("is", $project['id'], $project['wp_conversion_deadline']);
    $stmt->execute();

    // Notify admin
    $admin_message = "WP conversion deadline missed for project '{$project['name']}'. The deadline was {$project['wp_conversion_deadline']}.";
    $notify_query = "INSERT INTO notifications
                    (user_id, role, message, type, is_read)
                    VALUES (1, 'admin', ?, 'warning', 0)";
    $stmt = $conn->prepare($notify_query);
    $stmt->bind_param("s", $admin_message);
    $stmt->execute();

    // Notify webmaster - REMOVED the link and text here
    $webmaster_message = "You have missed the WP conversion deadline for project '{$project['name']}'. The deadline was {$project['wp_conversion_deadline']}.";
    $notify_query = "INSERT INTO notifications
                    (user_id, role, message, type, is_read)
                    VALUES (?, 'webmaster', ?, 'warning', 0)";
    $stmt = $conn->prepare($notify_query);
    $stmt->bind_param("is", $project['webmaster_id'], $webmaster_message);
    $stmt->execute();
}

// Check for missed project deadlines - same logic as above but for project_deadline
$project_query = "SELECT p.id, p.name, p.webmaster_id, p.project_deadline
                 FROM projects p
                 LEFT JOIN missed_deadlines md ON p.id = md.project_id AND md.deadline_type = 'project'
                 WHERE p.project_deadline < ?
                   AND p.project_deadline IS NOT NULL
                   AND md.id IS NULL";

$stmt = $conn->prepare($project_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

while ($project = $result->fetch_assoc()) {
    // Insert record for missed deadline
    $insert = "INSERT INTO missed_deadlines
              (project_id, deadline_type, original_deadline, recorded_at)
              VALUES (?, 'project', ?, NOW())";
    $stmt = $conn->prepare($insert);
    $stmt->bind_param("is", $project['id'], $project['project_deadline']);
    $stmt->execute();

    // Notify admin
    $admin_message = "Project deadline missed for project '{$project['name']}'. The deadline was {$project['project_deadline']}.";
    $notify_query = "INSERT INTO notifications
                    (user_id, role, message, type, is_read)
                    VALUES (1, 'admin', ?, 'warning', 0)";
    $stmt = $conn->prepare($notify_query);
    $stmt->bind_param("s", $admin_message);
    $stmt->execute();

    // Notify webmaster - REMOVED the link and text here
    $webmaster_message = "You have missed the project deadline for project '{$project['name']}'. The deadline was {$project['project_deadline']}.";
    $notify_query = "INSERT INTO notifications
                    (user_id, role, message, type, is_read)
                    VALUES (?, 'webmaster', ?, 'warning', 0)";
    $stmt = $conn->prepare($notify_query);
    $stmt->bind_param("is", $project['webmaster_id'], $webmaster_message);
    $stmt->execute();
}

// Instead of echoing a message directly, use a return value or logging
// This prevents the "Deadline check completed at..." message from appearing on the page
// Don't echo anything here!
// error_log("Deadline check completed at " . date('Y-m-d H:i:s'));
?>