<?php

require_once 'includes/config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = (int)$_POST['item_id'];
    $project_id = (int)$_POST['project_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    $comment = isset($_POST['comment']) ? $conn->real_escape_string($_POST['comment']) : '';
    $user_role = getUserRole();

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update checklist item status
        $query = "UPDATE project_checklist_status
                 SET status = ?, updated_at = NOW(), updated_by = ?
                 WHERE project_id = ? AND checklist_item_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("siii", $new_status, $_SESSION['user_id'], $project_id, $item_id);
        $stmt->execute();

        // Add comment if provided
        if ($comment) {
            $query = "INSERT INTO comments (project_id, checklist_item_id, user_id, comment)
                     VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiis", $project_id, $item_id, $_SESSION['user_id'], $comment);
            $stmt->execute();
        }

        // Get the current stage and project status
        $query = "SELECT ci.stage, p.current_status
                 FROM checklist_items ci
                 JOIN projects p ON p.id = ?
                 WHERE ci.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $project_id, $item_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $current_stage = $result['stage'];
        $current_project_status = $result['current_status'];

        // Check status of all items in the current stage
        $query = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN pcs.status = 'fixed' THEN 1 ELSE 0 END) as fixed_count,
                    SUM(CASE WHEN pcs.status = 'passed' THEN 1 ELSE 0 END) as passed_count,
                    SUM(CASE WHEN pcs.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN pcs.status IN ('passed', 'fixed') THEN 1 ELSE 0 END) as resolved_count
                 FROM project_checklist_status pcs
                 JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
                 WHERE pcs.project_id = ? AND ci.stage = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $project_id, $current_stage);
        $stmt->execute();
        $status_counts = $stmt->get_result()->fetch_assoc();

        $new_project_status = null;

        // Complex status progression logic
        if ($user_role === 'webmaster') {
            // If webmaster marks all items as fixed
            if ($status_counts['total'] == $status_counts['fixed_count']) {
                // Move to QA stage
                switch ($current_stage) {
                    case 'wp_conversion':
                        $new_project_status = 'wp_conversion_qa';
                        break;
                    case 'page_creation':
                        $new_project_status = 'page_creation_qa';
                        break;
                    case 'golive':
                        $new_project_status = 'golive_qa';
                        break;
                }
            }
            // If webmaster fixes an item and all other items are either passed or fixed
            else if ($status_counts['total'] == $status_counts['resolved_count']) {
                // Move back to QA stage
                switch ($current_stage) {
                    case 'wp_conversion':
                        $new_project_status = 'wp_conversion_qa';
                        break;
                    case 'page_creation':
                        $new_project_status = 'page_creation_qa';
                        break;
                    case 'golive':
                        $new_project_status = 'golive_qa';
                        break;
                }
            }
        }
        else if (in_array($user_role, ['qa_reporter', 'qa_manager', 'admin'])) {
            // Check if all items in the stage have been reviewed (either passed or failed)
            $all_items_reviewed = ($status_counts['passed_count'] + $status_counts['failed_count']) == $status_counts['total'];

            if ($all_items_reviewed) {
                if ($status_counts['failed_count'] > 0) {
                    // If any items are failed and all items have been reviewed, revert to previous stage
                    switch ($current_stage) {
                        case 'wp_conversion':
                            $new_project_status = 'wp_conversion';
                            break;
                        case 'page_creation':
                            $new_project_status = 'page_creation';
                            break;
                        case 'golive':
                            $new_project_status = 'golive';
                            break;
                    }
                }
                else if ($status_counts['total'] == $status_counts['passed_count']) {
                    // If all items are passed, move to next stage
                    switch ($current_stage) {
                        case 'wp_conversion':
                            $new_project_status = 'page_creation';
                            break;
                        case 'page_creation':
                            $new_project_status = 'golive';
                            break;
                        case 'golive':
                            $new_project_status = 'completed';
                            break;
                    }
                }
            }
            // If not all items have been reviewed yet, maintain current status
            else {
                $new_project_status = $current_project_status;
            }
        }

        // Update project status if it has changed
        if ($new_project_status !== null && $new_project_status !== $current_project_status) {
            $query = "UPDATE projects
                     SET current_status = ?, updated_at = NOW()
                     WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $new_project_status, $project_id);
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'newStatus' => $new_project_status ?? $current_project_status
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}