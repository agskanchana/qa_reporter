<?php
// Start output buffering to prevent header issues
ob_start();
require_once 'includes/config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Only webmasters can request deadline extensions
if ($user_role !== 'webmaster') {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log the incoming request for debugging
    error_log("Extension request received: " . print_r($_POST, true));

    // Get and validate form data
    $project_id = (int)$_POST['project_id'];
    $deadline_type = $_POST['deadline_type'];
    $requested_deadline = $_POST['requested_deadline'];
    $reason = trim($_POST['reason']);
    $original_deadline = $_POST['original_deadline'];

    // Validate required fields
    if (empty($project_id) || empty($deadline_type) || empty($requested_deadline) || empty($reason)) {
        $error = "All fields are required.";
        error_log("Extension request validation failed: missing required fields");
    } else {
        // Check if requested date is in the future
        $requested_date = new DateTime($requested_deadline);
        $today = new DateTime();

        if ($requested_date <= $today) {
            $error = "Requested deadline must be in the future.";
            error_log("Extension request validation failed: deadline not in future");
        } else {
            // Verify this is the webmaster's project
            $verify_query = "SELECT * FROM projects WHERE id = ? AND webmaster_id = ?";
            $stmt = $conn->prepare($verify_query);
            $stmt->bind_param("ii", $project_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $error = "You don't have permission to request extensions for this project.";
                error_log("Extension request validation failed: not webmaster's project");
            } else {
                // Check for pending requests
                $check_query = "SELECT * FROM deadline_extension_requests
                                WHERE project_id = ? AND deadline_type = ? AND status = 'pending'";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("is", $project_id, $deadline_type);
                $stmt->execute();
                $check_result = $stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $error = "You already have a pending extension request for this deadline.";
                    error_log("Extension request validation failed: pending request exists");
                } else {
                    // Create extension request
                    $insert_query = "INSERT INTO deadline_extension_requests
                                    (project_id, deadline_type, original_deadline, requested_deadline, reason, requested_by)
                                    VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param("issssi", $project_id, $deadline_type, $original_deadline, $requested_deadline, $reason, $user_id);

                    if ($stmt->execute()) {
                        $success = "Extension request submitted successfully and is pending approval.";
                        error_log("Extension request submitted successfully for project #$project_id, type: $deadline_type");

                        // Set session flag to prevent redirect loops
                        $_SESSION['extension_requested_' . $project_id . '_' . $deadline_type] = time();

                        // Redirect to project page with success message
                        header("Location: view_project.php?id=$project_id&success=extension_requested");
                        exit();
                    } else {
                        $error = "Error submitting extension request: " . $conn->error;
                        error_log("Error submitting extension request: " . $conn->error);
                    }
                }
            }
        }
    }
}

// If there was an error, redirect back with error message
if ($error) {
    $project_id = (int)$_POST['project_id'];
    header("Location: view_project.php?id=$project_id&error=" . urlencode($error));
    exit();
}

ob_end_flush();
?>