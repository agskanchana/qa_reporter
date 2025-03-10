<?php

// For webmasters, check if they have any missed deadlines needing explanations
if (getUserRole() === 'webmaster') {
    $user_id = $_SESSION['user_id'];

    $query = "SELECT m.*
              FROM missed_deadlines m
              JOIN projects p ON m.project_id = p.id
              WHERE p.webmaster_id = ?
              AND m.reason IS NULL";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $missed_deadlines = $stmt->get_result();

    // If there are any missed deadlines without explanations, show them
    if ($missed_deadlines->num_rows > 0) {
        $deadline = $missed_deadlines->fetch_assoc();
        // Redirect to the explanation form
        header("Location: missed_deadline_reason.php?deadline_id=" . $deadline['id']);
        exit;
    }
}
