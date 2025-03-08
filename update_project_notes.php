<?php
// filepath: /c:/wamp64/www/bug_reporter_jk_poe/update_project_notes.php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int) $_POST['project_id'];
    $note_type = $_POST['note_type'];
    $notes = trim($_POST['notes']);

    // Validate note type
    if ($note_type !== 'admin' && $note_type !== 'webmaster') {
        $_SESSION['error'] = "Invalid note type.";
        header("Location: view_project.php?id=$project_id");
        exit;
    }

    // Check permissions
    if ($note_type === 'admin' && $user_role !== 'admin') {
        $_SESSION['error'] = "You don't have permission to update admin notes.";
        header("Location: view_project.php?id=$project_id");
        exit;
    }

    if ($note_type === 'webmaster') {
        // Check if this webmaster is assigned to this project
        $check_query = "SELECT webmaster_id FROM projects WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0 || $result->fetch_assoc()['webmaster_id'] != $user_id) {
            $_SESSION['error'] = "You don't have permission to update webmaster notes for this project.";
            header("Location: view_project.php?id=$project_id");
            exit;
        }
    }

    // Update the appropriate notes field
    $field = $note_type . '_notes';
    $query = "UPDATE projects SET $field = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $notes, $project_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = ucfirst($note_type) . " notes updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating notes: " . $conn->error;
    }

    header("Location: view_project.php?id=$project_id");
    exit;
} else {
    // Not a POST request
    header("Location: dashboard.php");
    exit;
}
?>