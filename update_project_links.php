<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_role = getUserRole();
$user_id = $_SESSION['user_id'];

// Only allow appropriate users to update project links
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)$_POST['project_id'];

    // Verify user permissions (admin or webmaster assigned to this project)
    $can_edit = false;

    if ($user_role === 'admin') {
        $can_edit = true;
    } else if ($user_role === 'webmaster') {
        // Check if this webmaster is assigned to the project
        $query = "SELECT id FROM projects WHERE id = ? AND webmaster_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $project_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $can_edit = $result->num_rows > 0;
    }

    if ($can_edit) {
        // Sanitize inputs
        $ticket_link = filter_var($_POST['ticket_link'], FILTER_SANITIZE_URL);
        $test_site_link = filter_var($_POST['test_site_link'], FILTER_SANITIZE_URL);
        $gp_link = filter_var($_POST['gp_link'], FILTER_SANITIZE_URL);
        $live_site_link = filter_var($_POST['live_site_link'], FILTER_SANITIZE_URL);

        // Update project links
        $query = "UPDATE projects
                  SET ticket_link = ?,
                      test_site_link = ?,
                      gp_link = ?,
                      live_site_link = ?
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssi", $ticket_link, $test_site_link, $gp_link, $live_site_link, $project_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Project links updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update project links: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to edit this project.";
    }

    // Redirect back to the project page
    header("Location: view_project.php?id=" . $project_id);
    exit();
}

// If not a POST request, redirect to dashboard
header("Location: dashboard.php");
exit();
?>
