<?php
if (isset($_POST['assign_qa']) && isset($_POST['project_id']) && isset($_POST['qa_user_id'])) {
    $project_id = (int)$_POST['project_id'];
    $qa_user_id = (int)$_POST['qa_user_id'];

    // Check if project is already assigned
    $check_stmt = $conn->prepare("SELECT id FROM qa_assignments WHERE project_id = ?");
    $check_stmt->bind_param("i", $project_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result();

    if ($existing->num_rows > 0) {
        // Update existing assignment
        $stmt = $conn->prepare("UPDATE qa_assignments SET qa_user_id = ?, assigned_by = ?, assigned_at = NOW() WHERE project_id = ?");
        $stmt->bind_param("iii", $qa_user_id, $user_id, $project_id);
    } else {
        // Create new assignment
        $stmt = $conn->prepare("INSERT INTO qa_assignments (project_id, qa_user_id, assigned_by) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $project_id, $qa_user_id, $user_id);
    }
    $stmt->execute();

    header("Location: dashboard.php");
    exit();
}