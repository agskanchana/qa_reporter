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
$reason_submitted = false;
$deadline = null;

// Handle form submission for reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reason'])) {
    $deadline_id = (int)$_POST['deadline_id'];
    $reason = trim($_POST['reason']);

    // Update the missed deadline with the reason
    $query = "UPDATE missed_deadlines
              SET reason = ?,
                  reason_provided_by = ?,
                  reason_provided_at = NOW()
              WHERE id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $reason, $user_id, $deadline_id);

    if ($stmt->execute()) {
        $reason_submitted = true;
        $success = "Reason submitted successfully.";

        // Get the deadline information for the extension form
        $query = "SELECT m.*, p.name as project_name, p.id as project_id,
                 p.webmaster_id, p.project_deadline, p.wp_conversion_deadline
                 FROM missed_deadlines m
                 JOIN projects p ON m.project_id = p.id
                 WHERE m.id = ? AND p.webmaster_id = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $deadline_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $deadline = $result->fetch_assoc();
        } else {
            header("Location: dashboard.php");
            exit;
        }
    } else {
        $error = "Error submitting reason: " . $conn->error;
    }
}

// Handle extension request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_extension'])) {
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $deadline_type = isset($_POST['deadline_type']) ? $_POST['deadline_type'] : '';
    $requested_deadline = isset($_POST['requested_deadline']) ? $_POST['requested_deadline'] : '';
    $extension_reason = isset($_POST['extension_reason']) ? trim($_POST['extension_reason']) : '';

    // Ensure this project belongs to the current webmaster
    $check_query = "SELECT * FROM projects WHERE id = ? AND webmaster_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Get the current deadline
        $project = $result->fetch_assoc();
        $current_deadline = $deadline_type === 'project' ? $project['project_deadline'] : $project['wp_conversion_deadline'];

        // Add extension request
        $query = "INSERT INTO deadline_extension_requests
                 (project_id, deadline_type, original_deadline, requested_deadline, reason, requested_by)
                 VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("issssi", $project_id, $deadline_type, $current_deadline, $requested_deadline, $extension_reason, $user_id);

        if ($stmt->execute()) {
            $success = "Extension request submitted successfully.";

            // Redirect back to project page after successful submission
            header("Location: view_project.php?id=" . $project_id);
            exit;
        } else {
            $error = "Error submitting extension request: " . $conn->error;
        }
    } else {
        $error = "You don't have permission to extend deadlines for this project.";
    }
}

// Handle skipping the extension request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_extension'])) {
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

    // Redirect back to project page
    header("Location: view_project.php?id=" . $project_id);
    exit;
}

// If not submitted reason yet, get the deadline details
if (!$reason_submitted) {
    $query = "SELECT m.*, p.name as project_name, p.webmaster_id, p.id as project_id,
             p.project_deadline, p.wp_conversion_deadline, m.project_id as missed_project_id
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $reason_submitted ? "Request Deadline Extension" : "Missed Deadline Explanation"; ?></title>
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

        <?php if (!$reason_submitted): ?>
        <!-- Step 1: Provide reason for missing deadline -->
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

                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Missing Deadline</label>
                        <textarea class="form-control" id="reason" name="reason" rows="4" required></textarea>
                    </div>

                    <button type="submit" name="submit_reason" class="btn btn-primary">Submit Reason</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- Step 2: Request deadline extension -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h4>Request Deadline Extension: <?php echo htmlspecialchars($deadline['project_name']); ?></h4>
            </div>
            <div class="card-body">
                <p>You've explained why the deadline was missed. Now you can request an extension or skip.</p>

                <div class="d-flex justify-content-between mb-3">
                    <!-- This is a separate form for skipping the extension request -->
                    <form method="POST">
                        <input type="hidden" name="project_id" value="<?php echo $deadline['project_id']; ?>">
                        <button type="submit" name="skip_extension" class="btn btn-secondary">Skip Extension Request</button>
                    </form>
                </div>

                <!-- This is a separate form for the extension request -->
                <form method="POST">
                    <input type="hidden" name="project_id" value="<?php echo $deadline['project_id']; ?>">
                    <input type="hidden" name="deadline_type" value="<?php echo $deadline['deadline_type']; ?>">

                    <div class="mb-3">
                        <label class="form-label">Current Deadline</label>
                        <input type="text" class="form-control" value="<?php
                            $current_deadline = $deadline['deadline_type'] === 'project' ?
                                $deadline['project_deadline'] : $deadline['wp_conversion_deadline'];
                            echo date('F j, Y', strtotime($current_deadline));
                        ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="requested_deadline" class="form-label">Requested New Deadline</label>
                        <input type="date" class="form-control" id="requested_deadline" name="requested_deadline"
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="extension_reason" class="form-label">Reason for Extension Request</label>
                        <textarea class="form-control" id="extension_reason" name="extension_reason" rows="3" required></textarea>
                        <div class="form-text">Explain why you need this extension and how you'll meet the new deadline.</div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" name="submit_extension" class="btn btn-primary">Submit Extension Request</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>