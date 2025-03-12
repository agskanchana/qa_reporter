<?php
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