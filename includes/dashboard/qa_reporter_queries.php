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