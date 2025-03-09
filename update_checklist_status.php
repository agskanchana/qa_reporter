<?php

require_once 'includes/config.php';

/**
 * Record project status changes in history table
 * @param mysqli $conn Database connection
 * @param int $project_id Project ID
 * @param array $current_statuses Previous statuses array
 * @param array $new_statuses New statuses array
 * @param int $user_id User who made the change
 */
function recordProjectStatusHistory($conn, $project_id, $current_statuses, $new_statuses, $user_id) {
    // Convert to arrays if they're not already
    if (is_string($current_statuses)) {
        $current_statuses = !empty($current_statuses) ? explode(',', $current_statuses) : [];
    }
    if (is_string($new_statuses)) {
        $new_statuses = !empty($new_statuses) ? explode(',', $new_statuses) : [];
    }

    // Find newly added statuses
    foreach ($new_statuses as $status) {
        if (!in_array($status, $current_statuses)) {
            // This is a new status, record it in the history
            $history_query = "INSERT INTO project_status_history
                            (project_id, status, action, created_by, created_at)
                            VALUES (?, ?, 'updated', ?, NOW())";
            $stmt = $conn->prepare($history_query);

            // Make sure user ID is available
            $stmt->bind_param("isi", $project_id, $status, $user_id);
            $result = $stmt->execute();

            if (!$result) {
                error_log("Failed to record status history: " . $stmt->error);
            } else {
                error_log("Successfully recorded status '{$status}' in project history for project #{$project_id}");
            }
        }
    }
}

function getAdminUserId($conn) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    return $admin ? $admin['id'] : null;
}

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

        // Get status of all checklist items across all stages
        $all_stages_query = "SELECT
            ci.stage,
            COUNT(*) as total,
            SUM(CASE WHEN pcs.status = 'passed' THEN 1 ELSE 0 END) as passed_count,
            SUM(CASE WHEN pcs.status = 'fixed' THEN 1 ELSE 0 END) as fixed_count,
            SUM(CASE WHEN pcs.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN pcs.status = 'idle' THEN 1 ELSE 0 END) as idle_count
        FROM project_checklist_status pcs
        JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
        WHERE pcs.project_id = ?
        GROUP BY ci.stage";

        $stmt = $conn->prepare($all_stages_query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $all_stages_result = $stmt->get_result();

        $stages_status = [];
        while ($row = $all_stages_result->fetch_assoc()) {
            $stages_status[$row['stage']] = $row;
        }

        if ($user_role === 'webmaster') {
            // Get current project status
            $current_statuses = !empty($current_project_status) ?
                array_filter(explode(',', $current_project_status)) :
                ['wp_conversion'];

            // Get completion status for all stages
            $stages_query = "SELECT
                ci.stage,
                COUNT(*) as total,
                SUM(CASE WHEN pcs.status = 'fixed' THEN 1 ELSE 0 END) as fixed_count,
                SUM(CASE WHEN pcs.status = 'passed' THEN 1 ELSE 0 END) as passed_count
            FROM project_checklist_status pcs
            JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
            WHERE pcs.project_id = ? AND ci.is_archived = 0
            GROUP BY ci.stage";

            $stmt = $conn->prepare($stages_query);
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $stages_result = $stmt->get_result();

            $new_statuses = $current_statuses;
            $show_test_site_prompt = false;
            $show_live_site_prompt = false;

            // Check if this checklist item is in wp_conversion stage and is being marked as fixed
            if ($current_stage === 'wp_conversion' && $new_status === 'fixed') {
                // Check if the test_site_link is empty
                $test_site_query = "SELECT test_site_link FROM projects WHERE id = ?";
                $stmt->prepare($test_site_query);
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                $test_site_result = $stmt->get_result();
                $test_site_data = $test_site_result->fetch_assoc();

                // Show prompt if test site link is empty
                $show_test_site_prompt = empty($test_site_data['test_site_link']);
            }

            // Check if this checklist item is in golive stage and is being marked as fixed
            if ($current_stage === 'golive' && $new_status === 'fixed') {
                // Check if the live_site_link is empty
                $live_site_query = "SELECT live_site_link FROM projects WHERE id = ?";
                $stmt->prepare($live_site_query);
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                $live_site_result = $stmt->get_result();
                $live_site_data = $live_site_result->fetch_assoc();

                // Show prompt if live site link is empty
                $show_live_site_prompt = empty($live_site_data['live_site_link']);
            }

            // Process each stage
            while ($stage_row = $stages_result->fetch_assoc()) {
                $stage = $stage_row['stage'];
                $qa_status = $stage . '_qa';

                if ($stage === $current_stage) {
                    // Check if all items are either fixed or passed
                    if ($stage_row['fixed_count'] + $stage_row['passed_count'] == $stage_row['total'] &&
                        $stage_row['fixed_count'] > 0) {
                        // If there's at least one fixed item and all others are passed/fixed
                        // Remove non-QA status
                        $new_statuses = array_filter($new_statuses, function($status) use ($stage) {
                            return $status !== $stage;
                        });

                        // Add QA status if not present
                        if (!in_array($qa_status, $new_statuses)) {
                            $new_statuses[] = $qa_status;
                        }
                    }
                }
            }

            // Ensure we have at least wp_conversion status
            if (empty($new_statuses)) {
                $new_statuses[] = 'wp_conversion';
            }

            // Update project status
            sort($new_statuses);
            $new_project_status = implode(',', array_unique($new_statuses));

            // Record status changes in history table before updating project
            recordProjectStatusHistory($conn, $project_id, $current_statuses, $new_statuses, $_SESSION['user_id']);

            $update_project = "UPDATE projects SET current_status = ? WHERE id = ?";
            $stmt = $conn->prepare($update_project);
            $stmt->bind_param("si", $new_project_status, $project_id);
            $stmt->execute();

        } else if (in_array($user_role, ['qa_reporter', 'qa_manager', 'admin'])) {
            $all_items_reviewed = ($status_counts['passed_count'] + $status_counts['failed_count']) == $status_counts['total'];

            if ($all_items_reviewed) {
                $current_statuses = !empty($current_project_status) ? explode(',', $current_project_status) : [];

                if ($status_counts['failed_count'] > 0) {
                    // Remove QA status for current stage if any items failed
                    $qa_status = $current_stage . '_qa';
                    $current_statuses = array_filter($current_statuses, function($status) use ($qa_status) {
                        return $status !== $qa_status;
                    });

                    // Set status back to non-QA stage
                    if (!in_array($current_stage, $current_statuses)) {
                        $current_statuses[] = $current_stage;
                    }
                } else if ($status_counts['passed_count'] == $status_counts['total']) {
                    // All items passed, progress to next stage
                    if ($current_stage === 'wp_conversion') {
                        // Remove wp_conversion statuses
                        $current_statuses = array_filter($current_statuses, function($status) {
                            return $status !== 'wp_conversion' && $status !== 'wp_conversion_qa';
                        });

                        // Add page_creation if not in page_creation_qa
                        if (!in_array('page_creation_qa', $current_statuses)) {
                            $current_statuses[] = 'page_creation';
                        }
                    } else if ($current_stage === 'page_creation') {
                        // Remove page_creation statuses
                        $current_statuses = array_filter($current_statuses, function($status) {
                            return $status !== 'page_creation' && $status !== 'page_creation_qa';
                        });

                        // Add golive if not in golive_qa
                        if (!in_array('golive_qa', $current_statuses)) {
                            $current_statuses[] = 'golive';
                        }
                    } else if ($current_stage === 'golive') {
                        // Check if all items in all stages are either passed or fixed
                        $all_stages_complete = true;
                        $stages_query = "SELECT
                            COUNT(*) as total,
                            SUM(CASE WHEN pcs.status = 'passed' THEN 1 ELSE 0 END) as passed_count,
                            SUM(CASE WHEN pcs.status IN ('idle', 'failed') THEN 1 ELSE 0 END) as pending_count
                        FROM project_checklist_status pcs
                        JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
                        WHERE pcs.project_id = ? AND ci.is_archived = 0";

                        $stmt = $conn->prepare($stages_query);
                        $stmt->bind_param("i", $project_id);
                        $stmt->execute();
                        $all_stages = $stmt->get_result()->fetch_assoc();

                        if ($all_stages['pending_count'] == 0 && $all_stages['passed_count'] == $all_stages['total']) {
                            // All items in all stages are passed
                            $current_statuses = ['completed'];
                        }
                    }
                }

                // Update project status
                if (!empty($current_statuses)) {
                    sort($current_statuses);
                    $new_project_status = implode(',', array_unique($current_statuses));

                    // Record status history before updating
                    recordProjectStatusHistory($conn, $project_id, explode(',', $current_project_status), $current_statuses, $_SESSION['user_id']);

                    $update_project = "UPDATE projects SET current_status = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_project);
                    $stmt->bind_param("si", $new_project_status, $project_id);
                    $stmt->execute();
                }
            }
        }

        $conn->commit();
        // Include both prompt flags in the response
        echo json_encode([
            'success' => true,
            'newStatus' => $new_project_status ?? $current_project_status,
            'showTestSitePrompt' => isset($show_test_site_prompt) && $show_test_site_prompt === true,
            'showLiveSitePrompt' => isset($show_live_site_prompt) && $show_live_site_prompt === true
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}