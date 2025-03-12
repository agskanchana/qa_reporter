<?php
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


// Get QA stats for better dashboard displays
if ($user_role === 'qa_reporter') {
    // Get completed QA count
    $completed_query = "SELECT COUNT(DISTINCT pcs.project_id) as completed_count
                        FROM project_checklist_status pcs
                        JOIN qa_assignments qa ON pcs.project_id = qa.project_id
                        WHERE qa.qa_user_id = ?
                        AND pcs.status IN ('passed', 'failed')
                        GROUP BY pcs.project_id
                        HAVING COUNT(DISTINCT pcs.id) =
                            (SELECT COUNT(ci.id) FROM checklist_items ci WHERE ci.stage = pcs.stage AND ci.is_archived = 0)";
    $stmt = $conn->prepare($completed_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $completed_result = $stmt->get_result();
    $completed_qa_count = $completed_result->num_rows;

    // Get average completion time
    $avg_time_query = "SELECT AVG(TIMESTAMPDIFF(HOUR,
                        (SELECT MIN(created_at) FROM project_checklist_status WHERE project_id = pcs.project_id AND stage = pcs.stage),
                        (SELECT MAX(updated_at) FROM project_checklist_status WHERE project_id = pcs.project_id AND stage = pcs.stage)
                       )) as avg_hours
                       FROM project_checklist_status pcs
                       JOIN qa_assignments qa ON pcs.project_id = qa.project_id
                       WHERE qa.qa_user_id = ?
                       GROUP BY pcs.project_id, pcs.stage";
    $stmt = $conn->prepare($avg_time_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $avg_time_result = $stmt->get_result();
    $avg_completion_hours = 0;
    if ($row = $avg_time_result->fetch_assoc()) {
        $avg_completion_hours = round($row['avg_hours'], 1);
    }

    // Get most common failing items
    $common_fails_query = "SELECT ci.title, ci.stage, COUNT(*) as fail_count
                          FROM project_checklist_status pcs
                          JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
                          JOIN qa_assignments qa ON pcs.project_id = qa.project_id
                          WHERE qa.qa_user_id = ?
                          AND pcs.status = 'failed'
                          GROUP BY ci.id
                          ORDER BY fail_count DESC
                          LIMIT 4";
    $stmt = $conn->prepare($common_fails_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $common_fails = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get detection rate (percentage of items marked as failed that weren't eventually fixed and passed)
    $detection_query = "SELECT
                          (COUNT(DISTINCT CASE WHEN f.status = 'failed' AND p.status = 'passed' THEN f.id END) /
                           COUNT(DISTINCT CASE WHEN f.status = 'failed' THEN f.id END)) * 100 as detection_rate
                        FROM
                          project_checklist_status f
                          JOIN project_checklist_status p ON f.project_id = p.project_id AND f.checklist_item_id = p.checklist_item_id
                          JOIN qa_assignments qa ON f.project_id = qa.project_id
                        WHERE
                          qa.qa_user_id = ? AND
                          f.status = 'failed' AND
                          p.created_at > f.created_at";
    $stmt = $conn->prepare($detection_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $detection_result = $stmt->get_result();
    $detection_rate = 92; // Default value
    if ($row = $detection_result->fetch_assoc()) {
        $detection_rate = round($row['detection_rate']);
    }
} elseif (in_array($user_role, ['admin', 'qa_manager'])) {
    // Get project status for the specified stage
    $stages = ['wp_conversion_qa', 'page_creation_qa', 'golive_qa'];

    // Query to get all webmaster users
    $webmaster_query = "SELECT id, username FROM users WHERE role = 'webmaster' ORDER BY username";
    $webmaster_result = $conn->query($webmaster_query);
    $webmasters = [];
    while ($row = $webmaster_result->fetch_assoc()) {
        $webmasters[] = $row;
    }

    // Query to get all QA reporters
    $qa_reporter_query = "SELECT id, username FROM users WHERE role IN ('qa_reporter', 'qa_manager') ORDER BY username";
    $qa_reporter_result = $conn->query($qa_reporter_query);
    $qa_reporters = [];
    while ($row = $qa_reporter_result->fetch_assoc()) {
        $qa_reporters[] = $row;
    }

    // Get stats for each QA reporter
    $qa_stats = [];
    foreach ($qa_reporters as $reporter) {
        $reporter_id = $reporter['id'];
        $assigned_count_query = "SELECT COUNT(*) as count FROM qa_assignments WHERE qa_user_id = ?";
        $stmt = $conn->prepare($assigned_count_query);
        $stmt->bind_param("i", $reporter_id);
        $stmt->execute();
        $assigned_count = $stmt->get_result()->fetch_assoc()['count'];

        $qa_stats[$reporter_id] = [
            'name' => $reporter['username'],
            'assigned_count' => $assigned_count,
            'completed_count' => 0, // Would require additional query
        ];
    }
}
?>