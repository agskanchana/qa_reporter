
<?php
function syncProjectChecklist($project_id = null) {
    global $conn;

    $projects_query = $project_id ?
        "SELECT id FROM projects WHERE id = ?" :
        "SELECT id FROM projects";

    if ($project_id) {
        $stmt = $conn->prepare($projects_query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $projects = $stmt->get_result();
    } else {
        $projects = $conn->query($projects_query);
    }

    while ($project = $projects->fetch_assoc()) {
        // Get all checklist items that don't have a status entry for this project
        $query = "SELECT ci.*
                 FROM checklist_items ci
                 LEFT JOIN project_checklist_status pcs
                    ON ci.id = pcs.checklist_item_id
                    AND pcs.project_id = ?
                 WHERE pcs.id IS NULL";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $project['id']);
        $stmt->execute();
        $missing_items = $stmt->get_result();

        // Add missing items with 'idle' status
        while ($item = $missing_items->fetch_assoc()) {
            $insert = $conn->prepare("INSERT INTO project_checklist_status
                                (project_id, checklist_item_id, status, updated_at)
                                VALUES (?, ?, 'idle', CURRENT_TIMESTAMP)");
            $insert->bind_param("ii", $project['id'], $item['id']);
            $insert->execute();
        }
    }
}