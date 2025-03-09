<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Only webmasters can access this page
if ($user_role !== 'webmaster') {
    header("Location: dashboard.php");
    exit;
}

$deadline_id = isset($_GET['deadline_id']) ? (int)$_GET['deadline_id'] : 0;
$success = '';
$error = '';
$deadline = null;

// Handle form submission for reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reason'])) {
    $deadline_id = (int)$_POST['deadline_id'];
    $reason = trim($_POST['reason']);
    $project_id = (int)$_POST['project_id']; // Add this line to capture project_id

    // Update the missed deadline with the reason
    $query = "UPDATE missed_deadlines
              SET reason = ?,
                  reason_provided_by = ?,
                  reason_provided_at = NOW()
              WHERE id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $reason, $user_id, $deadline_id);

    if ($stmt->execute()) {
        // Successful update - redirect back to the project page
        $success = "Reason submitted successfully.";

        // Set a session variable to prevent redirect loop
        $session_key = 'extension_skipped_' . $project_id . '_' . $_POST['deadline_type'];
        $_SESSION[$session_key] = time();

        // Redirect back to project page
        header("Location: view_project.php?id=" . $project_id . "&success=" . urlencode($success));
        exit;
    } else {
        $error = "Error submitting reason: " . $conn->error;
    }
}

// Get the deadline details
$query = "SELECT m.*, p.name as project_name, p.webmaster_id, p.id as project_id,
         p.project_deadline, p.wp_conversion_deadline, m.project_id as missed_project_id,
         m.deadline_type
         FROM missed_deadlines m
         JOIN projects p ON m.project_id = p.id
         WHERE m.id = ? AND p.webmaster_id = ? AND (m.reason IS NULL OR m.reason = '')";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $deadline_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Either deadline doesn't exist, doesn't belong to this webmaster,
    // or reason has already been provided - redirect to dashboard
    header("Location: dashboard.php");
    exit;
}

$deadline = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missed Deadline Explanation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Provide reason for missing deadline -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h4>Deadline Missed: <?php echo htmlspecialchars($deadline['project_name']); ?></h4>
            </div>
            <div class="card-body">
                <p class="lead">The <?php echo $deadline['deadline_type'] === 'project' ? 'Project' : 'WP Conversion'; ?> deadline
                (<?php echo date('F j, Y', strtotime($deadline['original_deadline'])); ?>) was missed.</p>
                <p>Please provide an explanation for missing this deadline:</p>

                <form method="POST">
                    <input type="hidden" name="deadline_id" value="<?php echo $deadline_id; ?>">
                    <input type="hidden" name="project_id" value="<?php echo $deadline['project_id']; ?>">
                    <input type="hidden" name="deadline_type" value="<?php echo $deadline['deadline_type']; ?>">

                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Missing Deadline</label>
                        <textarea class="form-control" id="reason" name="reason" rows="4" required></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="view_project.php?id=<?php echo $deadline['project_id']; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="submit_reason" class="btn btn-primary">Submit Reason</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>