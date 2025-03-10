<?php
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